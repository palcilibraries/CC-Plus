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
            if ($_role->name == "Manager") $_role->name = "Local Admin";
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
        $conso_providers = Provider::with('institution:id,name','sushiSettings:id,prov_id,last_harvest','reports:id,name',
                                        'globalProv')
                                 ->orderBy('name','ASC')->get();

        // Build list of providers, based on globals, that includes extra mapped in consorium-specific data
        $global_providers = GlobalProvider::orderBy('name', 'ASC')->get();
        $providers = $global_providers->map( function ($rec) use ($master_reports, $conso_providers) {
            $rec->global_prov = $rec->toArray();
            $rec->connectors = $rec->connectionFields();
            $rec->connected = $conso_providers->where('global_id',$rec->id)->pluck('institution')->toArray();
            $rec->connection_count = count($rec->connected);
            $conso_connection = $conso_providers->where('global_id',$rec->id)->where('inst_id',1)->first();
            $rec->can_edit = ($conso_connection) ? true : false;
            $rec->can_connect = ($conso_connection) ? false : true;
            // Setup default values for the columns in the U/I
            $rec->conso_id = null;
            $rec->inst_name = null;
            $rec->active = ($rec->is_active) ? 'Active' : 'Inactive';
            $rec->day_of_month = null;
            $rec->can_delete = false;
            $reports_string = ($rec->master_reports) ?
                               $this->makeReportString($rec->master_reports, $master_reports) : '';
            $report_state = $this->reportState($rec->master_reports, $master_reports);
            // Global provider is attached
            if ($rec->connection_count > 0) {
                // get the provider record
                if ($rec->connection_count > 1 && !$conso_connection) {
                    $rec->inst_name = $rec->connection_count . " Institutions";
                } else {
                    $prov_data = ($conso_connection) ? $conso_connection : $conso_providers->where('global_id',$rec->id)->first();
                    if ($prov_data) {
                        $rec->conso_id = $prov_data->id;
                        $rec->inst_id = $prov_data->institution->id;
                        $rec->inst_name = ($prov_data->inst_id == 1) ? 'Entire Consortium' : $prov_data->institution->name;
                        $rec->is_active = $prov_data->is_active;
                        $rec->active = ($prov_data->is_active) ? 'Active' : 'Inactive';
                        $rec->day_of_month = $prov_data->day_of_month;
                        $rec->last_harvest = $prov_data->sushiSettings->max('last_harvest');
                        $rec->restricted = $prov_data->restricted;
                        $rec->allow_inst_specific = $prov_data->allow_inst_specific;
                        // $rec->can_edit = true;
                        $rec->can_delete = (is_null($rec->last_harvest)) ? true : false;
                        if ($prov_data->reports) {
                            $report_ids = $prov_data->reports->pluck('id')->toArray();
                            $reports_string = $this->makeReportString($report_ids, $master_reports);
                            $report_state = $this->reportState($report_ids, $master_reports);
                        }
                    }
                }
            }
            $rec->report_state = $report_state;
            $rec->reports_string = ($reports_string == '') ? "None" : $reports_string;
            return $rec;
        })->toArray();

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
