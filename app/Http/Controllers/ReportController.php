<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Report;
use App\SavedReport;
use App\Institution;
use App\InstitutionGroup;
use App\Provider;
use App\Platform;
use App\Publisher;
use App\DataType;
use App\SectionType;
use App\AccessType;
use App\AccessMethod;
use App\TitleReport;

//Enables us to output flash messaging
use Session;

class ReportController extends Controller
{
    private static $input_filters;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        self::$input_filters=[];
        // $this->middleware(['auth','role:Admin']);
    }

    /**
     * Setup dashboard for generating usage report summaries
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $platforms = Platform::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $publishers = Publisher::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $accesstypes = AccessType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $accessmethods = AccessMethod::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $datatypes = DataType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $sectiontypes = SectionType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $master_reports = Report::orderBy('name', 'asc')->where('parent_id', '=', 0)->get(['name','id'])->toArray();
        if (auth()->user()->hasRole("Admin") || auth()->user()->hasRole("Viewer")) {
            $inst_groups = InstitutionGroup::get(['name', 'id'])->toArray();
            $institutions = Institution::where('is_active',true)->orderBy('name', 'ASC')->get();
            $providers = Provider::where('is_active',true)->orderBy('name', 'ASC')->get();
        } else {
            $inst_groups = array();
            $institutions = Institution::where('id', auth()->user()->inst_id)->get();
            $providers = Provider::where('is_active',true)
                                 ->where(function($qry) {
                                     $qry->where('inst_id', 1)
                                         ->orWhere('inst_id', auth()->user()->inst_id);
                                 })
                                 ->orderBy('name', 'ASC')->get();
        }
        return view('reports.usage', compact('platforms','publishers','accesstypes','accessmethods',
                                             'datatypes', 'sectiontypes','master_reports','inst_groups',
                                             'institutions', 'providers'));
    }

    /**
     * View defined reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function view(Request $request)
    {
        $master_reports = Report::orderBy('name', 'asc')->where('parent_id', '=', 0)->get();
        $user_reports = SavedReport::orderBy('title', 'asc')->where('user_id', '=', auth()->id())->get();

        return view('reports.view', compact('master_reports', 'user_reports'));
    }

    /**
     * Display a specific report
     *
     * @param  \App\Report  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $report = Report::findOrFail($id);
        $fields = $report->reportFields();
        return view('reports.show', compact('report', 'fields'));
    }

    /**
     * Update usage report filter options based on Vue state date-range and/or active columns/filters
     *
     * @param  \Illuminate\Http\Request  $request
     *         Expects $request to be a JSON object holding the Vue state.filter_by object and master_id
     * @return \Illuminate\Http\Response
     */
    public function getReportData(Request $request)
    {
        // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input']);
        }
        if (!isset($input['filters']) || !isset($input['master_id'])) {
            return response()->json(['result' => false, 'msg' => 'One or more inputs are missing!']);
        }

        // update the private global
        self::$input_filters = $input['filters'];

        // Set prefixes for databases
        $global_db = config('database.connections.globaldb.database');
        $conso_db = config('database.connections.consodb.database');

        // Get Report model, set report table target
        $report = Report::where('id',$input['master_id'])->where('parent_id','0')->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Master Report is undefined!']);
        }
        $report_table = $conso_db . '.' . strtolower($report->name) . '_report_data';

        // Setup institution limiter array for whereIn clause later
        $limit_to_insts = self::limitToInstitutions();

        // Build where clause conditions for this report based on $input_filters
        $conditions = self::filterOnConditions($report);

// NEED TO DO THIS....
        // Get fields for this report
        // $report_fields = $report->reportFields();

        // Run the query
        if ($input['master_id']==1) {

            // Build a list of columns to be included/returned by the query
            // $get_columns = array();
            // $get_columns = [titleInfo()->Title . ' as name'];

            // Setup an array of "eager loaded" relationships
            $relationships = ['institution:id,name','provider:id,name','publisher:id,name','platform:id,name',
                              'datatype:id,name','accesstype:id,name','accessmethod:id,name','sectiontype:id,name',
                              'title'];

            // Run the query
            if (!empty($limit_to_insts)) {
                $records = TitleReport::with($relationships)->where($conditions)
                                      ->whereIn('inst_id', $limit_to_insts)->limit(100)->get();
            } else {
                $records = TitleReport::with($relationships)->where($conditions)->limit(100)->get();
            }
            return response()->json(['usage' => $records],200);

        }
    }

    /**
     * Update usage report filter options based on Vue state date-range and/or active columns/filters
     *
     * @param  \Illuminate\Http\Request  $request
     *         Expects $request to be a JSON object holding the Vue state.filter_by object and master_id
     * @return \Illuminate\Http\Response
     */
    public function updateFilters(Request $request)
    {
        // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input']);
        }
        if (!isset($input['filters']) || !isset($input['master_id'])) {
            return response()->json(['result' => false, 'msg' => 'One or more inputs are missing!']);
        }

        // update the private global
        self::$input_filters = $input['filters'];

        // Get Report model, set database table target
        $report = Report::where('id',$input['master_id'])->where('parent_id','0')->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Master Report is undefined!']);
        }
        $report_table = config('database.connections.consodb.database') . "." .
                        strtolower($report->name) . '_report_data';

        // Setup institution limiter array for whereIn clause later
        $limit_to_insts = self::limitToInstitutions();

        // Build where clause conditions for this report based on $input_filters
        $conditions = self::filterOnConditions($report);

        // Query the XX_report_data table to build select options for all filters connected
        // to active columns (id >= 0) regardless of whether the filter is actively limiting.
        $return_values = array();
        foreach ($report->reportFilters as $filt) {
            if (self::$input_filters[$filt->report_column] < 0) { // Don't query if column is inactive
                continue;
            }
            // Skip getting inst-ids if we're limiting institution
            if ($filt->report_column == 'inst_id' && !empty($limit_to_insts)) {
                $_ids = $limit_to_insts;
            } else {
                $_ids = array();
                if (!empty($limit_to_insts)) {
                    $_ids = DB::table($report_table)
                              ->whereIn('inst_id', $limit_to_insts)
                              ->where($conditions)
                              ->distinct()
                              ->pluck($filt->report_column);
                } else {
                    $_ids = DB::table($report_table)->where($conditions)->distinct()->pluck($filt->report_column);
                }
            }

            // Setup an array of ID+name pairs for the filter, append it to $return_values
            $_db = ($filt->is_global) ? config('database.connections.globaldb.database') . "."
                                      : config('database.connections.consodb.database') . ".";
            ${$filt->table_name} = DB::table($_db . $filt->table_name)
                                     ->whereIn('id',$_ids)
                                     ->get(['id','name'])
                                     ->toArray();
            array_unshift(${$filt->table_name}, ['id' => 0, 'name' => 'ALL']);
            $return_values[$filt->table_name] = ${$filt->table_name};
        }
        return response()->json(['result' => true, 'filters' => $return_values]);
    }

    /**
     * Build a mysql where clause (as an array for Eloquent) based on $input_filters
     *   input_filter < 0 : means column is inactive, exclude it
     *   input_filter = 0 : means column is active, no filter applied
     *   input_filter > 0 : means column is being filtered by the given ID
     * Inst and Inst-groups are ignored and expected to be handled via a "whereIn" clause
     *
     * @param  Report $report
     * @return Array  $conditions
     */
    private function filterOnConditions($report)
// -----------------------------------------------------------------
// IDEA : Make sure this will set/update options for a single filter
//        based on the others that are active...
// -----------------------------------------------------------------
    {
        $conditions = array();
        foreach ($report->reportFilters as $filt) {
            // Skip inst & inst-group and any unmatched filter
            if ((!isset(self::$input_filters[$filt->report_column])) ||       // unmatched?
                ($filt->report_column == "inst_id" || $filt->report_column == "institutiongroup_id")) {
                continue;
            }
            if (self::$input_filters[$filt->report_column] > 0) {
                $conditions[] = array($filt->report_column,self::$input_filters[$filt->report_column]);
            }
        }

        // Add date range as a condition if they're set
//Note:: Error testing should happen in the UI before we get here ---
        if (isset(self::$input_filters['from_yearmon']) && isset(self::$input_filters['to_yearmon'])){
            if (self::$input_filters['from_yearmon'] != '') {
                $conditions[] = array('yearmon','>=',self::$input_filters['from_yearmon']);
                $conditions[] = array('yearmon','<=',self::$input_filters['to_yearmon']);
            }
        }

        return $conditions;
    }

    /**
     * Build an an array of institutions we want to limit-by based on the inst and int-group filters
     *
     * @return Array $limit_to_insts
     */
    private function limitToInstitutions()
    {
        // Setup limit(s) on which institution(s) we're looking at. Groups are an aggregate connected
        // to the interface that affect the inst filter, but are NOT in the report->reportFilters()
        // If user is not an "admin" or "viewer", return only their own inst.
        $return_values = array();
        if (!auth()->user()->hasAnyRole(['Admin','Viewer'])) {
            array_push($return_values,auth()->user()->inst_id);
        } else {
            if (isset(self::$input_filters['inst_id']) || isset(self::$input_filters['institutiongroup_id'])) {
                if (self::$input_filters['inst_id'] > 0) {
                    array_push($return_values,self::$input_filters['inst_id']);
                } else if (self::$input_filters['institutiongroup_id'] > 0) {
                    $group = InstitutionGroup::find(self::$input_filters['institutiongroup_id']);
                    $return_values = $group->institutions->pluck('id')->toArray();
                }
            }
        }
        return $return_values;
    }

}
