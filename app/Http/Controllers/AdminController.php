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
        $roles = Role::orderBy('id', 'ASC')->get(['name', 'id']);
        $viewRoleId = $roles->where('name', 'Viewer')->first()->id;

        // Get institutions and groups
        $institutions = Institution::orderBy('name', 'ASC')->get(['id','name'])->toArray();
        $groups = InstitutionGroup::orderBy('name', 'ASC')->get(['id','name'])->toArray();

        // Get master report definitions
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('name','ASC')->get(['id','name']);

        // Get all providers
        $provider_data = Provider::with('institution:id,name','sushiSettings:id,prov_id,last_harvest','reports:id,name',
                                        'globalProv')
                                 ->orderBy('name','ASC')->get();

        // Setup columns for the datatable
        $providers = $provider_data->map( function ($rec) use ($master_reports) {
            $rec->active = ($rec->is_active) ? 'Active' : 'Inactive';
            $rec->inst_name = ($rec->institution->id == 1) ? 'Entire Consortium' : $rec->institution->name;
            $rec->day_of_month = $rec->day_of_month;
            $last_harvest = $rec->sushiSettings->max('last_harvest');
            $rec->can_delete = (is_null($last_harvest)) ? true : false;
            if ($rec->reports) {
                $report_string = '';
                $report_ids = $rec->reports->pluck('id')->toArray();
                $report_names = $master_reports->whereIn('id',$report_ids)->pluck('name')->toArray();
                foreach ($report_names as $name) {
                    $report_string .= ($report_string=='') ? $name : ', ' . $name;
                }
                $rec->reports_string = $report_string;
            } else {
                $rec->reports_string = "None";
            }
            return $rec;
        })->toArray();

        // Get global provider definitions for unconnected providers
        $existingIds = $provider_data->pluck('global_id')->toArray();
        $unset_global = GlobalProvider::whereNotIn('id',$existingIds)->orderBy('name', 'ASC')->get();

        // Load the view with the data
        return view('admin.home', compact('conso_name','roles','institutions','groups','providers','master_reports',
                                          'unset_global'));
    }
}
