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
    private $group_by;
    private $raw_fields;
    private $joins;
    private $global_db;
    private $conso_db;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        self::$input_filters=[];
        $group_by=[];
        $raw_fields='';
        $joins = [];

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
        return view('reports.usage');
        // $platforms = Platform::orderBy('name', 'asc')->get(['name','id'])->toArray();
        // $publishers = Publisher::orderBy('name', 'asc')->get(['name','id'])->toArray();
        // $accesstypes = AccessType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        // $accessmethods = AccessMethod::orderBy('name', 'asc')->get(['name','id'])->toArray();
        // $datatypes = DataType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        // $sectiontypes = SectionType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        // $master_reports = Report::orderBy('name', 'asc')->where('parent_id', '=', 0)->get(['name','id'])->toArray();
        // if (auth()->user()->hasRole("Admin") || auth()->user()->hasRole("Viewer")) {
        //     $inst_groups = InstitutionGroup::get(['name', 'id'])->toArray();
        //     $institutions = Institution::where('is_active',true)->orderBy('name', 'ASC')->get();
        //     $providers = Provider::where('is_active',true)->orderBy('name', 'ASC')->get();
        // } else {
        //     $inst_groups = array();
        //     $institutions = Institution::where('id', auth()->user()->inst_id)->get();
        //     $providers = Provider::where('is_active',true)
        //                          ->where(function($qry) {
        //                              $qry->where('inst_id', 1)
        //                                  ->orWhere('inst_id', auth()->user()->inst_id);
        //                          })
        //                          ->orderBy('name', 'ASC')->get();
        // }
        // return view('reports.usage', compact('platforms','publishers','accesstypes','accessmethods',
        //                                      'datatypes', 'sectiontypes','master_reports','inst_groups',
        //                                      'institutions', 'providers'));
    }

    /**
     * Setup view for displaying usage
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function display(Request $request)
    {
        return view('reports.display');
    }
    /**

     * Setup view for exporting usage
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        return view('reports.export');
    }

    /**
     * View defined reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function view(Request $request)
    {
        // $master_reports = Report::orderBy('name', 'asc')->where('parent_id', '=', 0)->get();
        $master_reports = Report::with('reportFields','children')
                                ->orderBy('name', 'asc')
                                ->where('parent_id', '=', 0)
                                ->get();
        $user_reports = SavedReport::orderBy('title', 'asc')
                                   ->where('user_id', '=', auth()
                                   ->id())
                                   ->get();

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
        // $fields = $report->reportFields();
        $fields = $report->reportFields;
        return view('reports.show', compact('report', 'fields'));
    }

    /**
     * Get usage report data records date-range, columns/filters, and sorting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function getReportData(Request $request)
    {
         global $joins, $raw_fields, $group_by, $global_db, $conso_db;

         // Validate and deal w/ inputs
         $this->validate($request, ['master_id' => 'required', 'columns' => 'required',
                                    'YM_from' => 'required', 'YM_to' => 'required']);
         $master_id = $request->master_id;
         $columns = json_decode($request->columns, true);
         $rows = (isset($request->itemsPerPage)) ? $request->itemsPerPage : 20;
         $preview = (isset($request->preview)) ? $request->preview : 0;

         // Get/set global things
         self::$input_filters['YM_from'] = $request->YM_from;
         self::$input_filters['YM_to'] = $request->YM_to;
         $global_db = config('database.connections.globaldb.database');
         $conso_db = config('database.connections.consodb.database');

        // Get Report model, set report table target
        $report = Report::where('id',$master_id)->where('parent_id','0')->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Master Report is undefined!']);
        }
        $report_table = $conso_db . '.' . strtolower($report->name) . '_report_data';

        // Setup institution limiter array for whereIn clause later
        $limit_to_insts = self::limitToInstitutions();

        // Setup joins, fields to select, and group_by based on active columns
        $report_fields = $report->reportFields;
        self::setupQueryFields($report_fields, $columns);

        // Build where clause conditions for this report based on $input_filters
        $conditions = self::filterOnConditions($report);

        // Set/get sort order
        $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Title';
        $sortDir = ($request->sortDesc) ? 'desc' : 'asc';

        // Run the query
        if ($master_id==1) {    // TR report
            $records = DB::table($report_table . ' as TR')
                      ->join($global_db . '.titles as TI', 'TR.title_id', 'TI.id')
                      ->when($joins['institution'], function ($query, $join) {
                          return $query->join($join, 'TR.inst_id', 'INST.id');
                      })
                      ->when($joins['provider'], function ($query, $join) {
                          return $query->join($join, 'TR.prov_id', 'PROV.id');
                      })
                      ->when($joins['platform'], function ($query, $join) {
                          return $query->join($join, 'TR.plat_id', 'PLAT.id');
                      })
                      ->when($joins['publisher'], function ($query, $join) {
                          return $query->join($join, 'TR.publisher_id', 'PUBL.id');
                      })
                      ->when($joins['datatype'], function ($query, $join) {
                          return $query->join($join, 'TR.datatype_id', 'DTYP.id');
                      })
                      ->when($joins['accesstype'], function ($query, $join) {
                          return $query->join($join, 'TR.accesstype_id', 'ATYP.id');
                      })
                      ->when($joins['accessmethod'], function ($query, $join) {
                          return $query->join($join, 'TR.accessmethod_id', 'AMTH.id');
                      })
                      ->when($joins['sectiontype'], function ($query, $join) {
                          return $query->join($join, 'TR.sectiontype_id', 'STYP.id');
                      })
                      ->selectRaw($raw_fields)
                      ->when($limit_to_insts, function ($query, $limit_to_insts) {
                          return $query->whereIn('inst_id', $limit_to_insts);
                      })
                      ->where($conditions)
                      ->groupBy($group_by)
                      ->orderBy($sortBy, $sortDir)
                      ->when($preview, function ($query, $preview) {
                          return $query->limit($preview)->get();
                      }, function ($query) {
                          return $query->get()->paginate($rows);
                      });

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

    // Not sure this is really necessary...
        // // Query for min and max yearmon values, store in the return_values array
        // $return_values = array();
        // if (!empty($limit_to_insts)) {
        //     $ym_range = DB::table($report_table)
        //                   ->whereIn('inst_id', $limit_to_insts)
        //                   ->where($conditions)
        //                   ->selectRaw('min(yearmon) as YM_min, max(yearmon) as YM_max')
        //                   ->get();
        // } else {
        //     $ym_range = DB::table($report_table)
        //                   ->where($conditions)
        //                   ->selectRaw('min(yearmon) as YM_min, max(yearmon) as YM_max')
        //                   ->get();
        // }
        //
        // Query the XX_report_data table to build select options for all filters connected
        // to active columns (id >= 0) regardless of whether the filter is actively limiting.
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
     * Set joins, the raw-select string, and group_by array based on fields and Columns
     *
     * @param  ReportField $fields, $columns
     * @return
     */
    private function setupQueryFields($fields, $columns)
    {
        global $joins, $raw_fields, $group_by, $global_db, $conso_db;
        $raw_fields = 'yearmon,';
        $group_by = ['yearmon'];
        foreach ($fields as $field) {
            if (!isset($field->qry)) {
                continue;
            }
            $split = preg_split('/ /',$field->qry); // for when qry has ' as <something>''
            if (isset($split[1]) && isset($split[2])) {
                $_qry = (strtoupper($split[1]) == 'AS') ? $split[2] : $split[0];
            } else {
                $_qry = $split[0];
            }
            $joins[$_qry] = "";
            // If the column is active
            if (isset($columns[$_qry])) {
                if ($columns[$_qry]['active']) {
                    // set join if needed
                    if (!is_null($field->joins)) {
                        if (preg_match('/_conso_/',$field->joins)) {
                            $_join = preg_replace('/_conso_/', $conso_db, $field->joins);
                        }
                        if (preg_match('/_global_/',$field->joins)) {
                            $_join = preg_replace('/_global_/', $global_db, $field->joins);
                        }
                        $joins[$_qry] = $_join;
                    }
                    // Add column to raw-list
                    $raw_fields .= $field->qry . ',';
                    if (isset($columns[$_qry]['filter'])) {
                        $input_filters[$_qry] = $columns[$_qry]['filter'];
                    }
                    // Add column to group-by
                    if ($field->group_it) {
                        $group_by[] = $split[0];
                    }
                }
            } else {
                $joins[$_qry] = "";
            }
        }

        $raw_fields = rtrim($raw_fields, ',');
        return;
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
        if (isset(self::$input_filters['YM_from']) && isset(self::$input_filters['YM_to'])){
            if (self::$input_filters['YM_from'] != '') {
                $conditions[] = array('yearmon','>=',self::$input_filters['YM_from']);
                $conditions[] = array('yearmon','<=',self::$input_filters['YM_to']);
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
