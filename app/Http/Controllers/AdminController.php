<?php

namespace App\Http\Controllers;

use App\Consortium;
use App\User;
use App\Role;
use App\Institution;
use App\InstitutionGroup;
use App\Provider;
use App\GlobalProvider;
use App\SushiSettings;
use App\ConnectionField;
use App\Report;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin');
    }

    //Index method for Admin Controller
    public function index()
    {
        // Get the consortium name
        $cur_instance = Consortium::where('ccp_key', session('ccp_con_key'))->first();
        $conso_name = ($cur_instance) ? $cur_instance->name : "Template";

        // Get all roles
        $role_data = Role::orderBy('id', 'ASC')->get(['name', 'id']);
        $roles = array();
        foreach ($role_data as $_role) {
            if ($_role->id > auth()->user()->maxRole()) continue;
            if ($_role->name == "Manager") $_role->name = "Local Admin";
            if ($_role->name == 'Admin') $_role->name = "Consortium Admin";
            if ($_role->name == 'Viewer') $_role->name = "Consortium Viewer";
            $roles[] = $_role;
        }

        // Get institutions
        $institutionData = Institution::orderBy('name', 'ASC')->get(['id','name']);

        // Get institution groups , include members and non-members
        $data = InstitutionGroup::with('institutions:id,name')->orderBy('name', 'ASC')->get();
        $groups = array();
        foreach ($data as $group) {
            $group->count = $group->institutions->count();
            if ($group->count > 0) {
                $members = $group->institutions->pluck('id')->toArray();
                $group->not_members = $institutionData->except($members);
            } else {
                $group->not_members = [];
            }
            $groups[] = $group->toArray();
        }

        // Get master report definitions
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('name','ASC')->get(['id','name']);

        // Get all (consortium) providers, extract array of global IDs
        $conso_providers = Provider::with('sushiSettings:id,prov_id,last_harvest','reports:id,name','globalProv',
                                          'institution:id,name')->orderBy('name','ASC')->get();

        // Build list of providers, based on globals, that includes extra mapped in consorium-specific data
        $global_providers = GlobalProvider::orderBy('name', 'ASC')->get();

        $output_providers = [];
        foreach ($global_providers as $rec) {
            $rec->global_prov = $rec->toArray();
            $rec->connectors = $rec->connectionFields();
            $rec->can_edit = false;   // default value for unconnected global provider
            $rec->can_connect = true; //    "      "    "       "         "       "
            $rec->conso_id = null;
            $rec->inst_id = null;
            $rec->inst_name = null;
            $rec->active = ($rec->is_active) ? 'Active' : 'Inactive';
            $rec->day_of_month = null;
            $rec->can_delete = false;
            $reports_string = ($rec->master_reports) ?
                                   $this->makeReportString($rec->master_reports, $master_reports) : '';
            $rec->report_state = $this->reportState($rec->master_reports, $master_reports);

            // Remap master reports to just the globally available ones and add names
            $_reports = [];
            foreach ($master_reports as $rpt) {
                if (in_array($rpt->id, $rec->master_reports)) {
                    $_reports[] = array('id' => $rpt->id, 'name' => $rpt->name);
                }
            }
            $rec->master_reports = $_reports;
            $rec->reports_string = ($reports_string == '') ? "None" : $reports_string;

            // Setup connected institution data for all outpute records
            $connected_insts = array();
            $connected_providers = $conso_providers->where('global_id',$rec->id);
            foreach ($connected_providers as $prov_data) {
                $_name = ($prov_data->inst_id == 1) ? 'Entire Consortium' : $prov_data->institution->name;
                $connected_insts[] = array('id' => $prov_data->inst_id, 'name' => $_name);
            }

            // Include globals not connected to the consortium in the array
            $conso_connection = $connected_providers->where('inst_id',1)->first();
            if (!$conso_connection) {
                $rec->connected = array();
                $rec->connection_count = 0;
                $output_providers[] = $rec->toArray();
            }

            // Include all providers connected to the global in the array
            foreach ($connected_providers as $prov_data) {
                $rec->inst_id = $prov_data->inst_id;
                $rec->inst_name = $prov_data->institution->name;
                // inst-specific providers show only one connection; consortium providers include all
                $rec->connected = ($rec->inst_id==1) ? $connected_insts
                                                     : array( array('id' => $rec->inst_id, 'name' => $rec->inst_name) );
                $rec->connection_count = count($rec->connected);
                $rec->can_edit = true;
                $rec->conso_id = $prov_data->id;
                $rec->is_active = $prov_data->is_active;
                $rec->active = ($prov_data->is_active) ? 'Active' : 'Inactive';
                $rec->day_of_month = $prov_data->day_of_month;
                $rec->last_harvest = $prov_data->sushiSettings->max('last_harvest');
                $rec->restricted = $prov_data->restricted;
                $rec->allow_inst_specific = $prov_data->allow_inst_specific;
                if ($conso_connection) {
                    $rec->can_connect = ($conso_connection->allow_inst_specific && $rec->inst_id == 1) ? true : false;
                } else {
                    $rec->can_connect = ($rec->inst_id == 1) ? true : false;
                }
                $rec->can_delete = (is_null($rec->last_harvest)) ? true : false;
                if ($prov_data->reports) {
                    $report_ids = $prov_data->reports->pluck('id')->toArray();
                    $rec->reports_string = $this->makeReportString($report_ids, $master_reports);
                    $rec->report_state = $this->reportState($report_ids, $master_reports);
                }
                $output_providers[] = $rec->toArray();
            }
        }
        $providers = array_values($output_providers);

        // Get global provider definitions for unconnected providers
        $existingIds = $conso_providers->pluck('global_id')->toArray();
        $unset_global = GlobalProvider::whereNotIn('id',$existingIds)->orderBy('name', 'ASC')->get();
        $institutions = $institutionData->toArray();

        // Load the view with the data
        return view('admin.home', compact('conso_name','roles','institutions','groups','providers','master_reports',
                                          'unset_global'));
    }

    /**
     * Build string representation of master_reports array
     *
     * @param  Array  $reports
     * @param  Collection  $master_reports
     * @return String
     */
    private function makeReportString($reports, $master_reports) {
        $report_string = '';
        foreach ($master_reports as $mr) {
            if (in_array($mr->id,$reports)) {
                $report_string .= ($report_string == '') ? '' : ', ';
                $report_string .= $mr->name;
            }
        }
        return $report_string;
    }

    /**
     * Return an array of booleans for report-state from provider reports columns
     *
     * @param  Array  $reports
     * @param  Collection  $master_reports
     * @return Array  $report-state
     */
    private function reportState($reports, $master_reports) {
        $rpt_state = array();
        foreach ($master_reports as $rpt) {
            $rpt_state[$rpt->name] = (in_array($rpt->id, $reports)) ? true : false;
        }
        return $rpt_state;
    }

}
