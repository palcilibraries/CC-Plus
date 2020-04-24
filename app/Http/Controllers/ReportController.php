<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Report;
use App\ReportField;
use App\ReportFilter;
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
     * View defined reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
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
     * Setup wizard for creating usage report summaries
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if (auth()->user()->hasAnyRole(['Admin','Viewer'])) {
            $institutions = Institution::orderBy('name', 'ASC')->where('id','<>',1)->get(['id','name'])->toArray();
            array_unshift($institutions, ['id' => 0, 'name' => 'Entire Consortium']);
            $inst_groups = InstitutionGroup::get(['name', 'id'])->toArray();
            array_unshift($inst_groups, ['id' => 0, 'name' => 'Choose a Group']);
            $providers = Provider::with('reports')->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        } else {    // limited view
            $user_inst = auth()->user()->inst_id;
            $institutions = Institution::where('id', '=', $user_inst)->get(['id','name'])->toArray();
            $inst_groups = array();
            $providers = Provider::with('reports')
                                 ->where(function ($query) use ($user_inst) {
                                     $query->where('inst_id',1)->orWhere('inst_id',$user_inst);
                                 })
                                 ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        }
        array_unshift($providers, ['id' => 0, 'name' => 'All Providers']);
        $reports = Report::with('reportFields','children')->orderBy('id', 'asc')->get()->toArray();
        $fields = ReportField::orderBy('id', 'asc')->get()->toArray();

        return view('reports.create',
                    compact('institutions','inst_groups','providers','reports','fields'));
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
    public function preview(Request $request)
    {
        // Get filters as incoming Json (expects the full filter_by object from the datastore)
        $this->validate($request, ['filters' => 'required']);
        $preset_filters = json_decode($request->filters, true);

        // Get the report model and all rows of the reportFilter model
        $report = Report::where('id',$preset_filters['report_id'])->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Report ID: ' . $report_id . ' is undefined']);
        }
        $all_filters = ReportFilter::all();

        // Get (master) fields
        if ($report->parent_id ==0) {   // previewing a master report?
            $field_data = $report->reportfields;
        } else {
            // Build field array from inherited fields
            $master_fields = $report->parent->reportfields;
            $inherited = preg_split('/,/',$report->inherited_fields);   //should be a CSV list of fields
            $child_fields = array();
            foreach ($inherited as $field) {
                $split_field = preg_split('/:/',$field);
                $_field = $master_fields->find($split_field[0]);
                $child_fields[] = $_field;

                // If the field carries a filter-preset update the filters array
                $_filter = $all_filters->find($_field->report_filter_id);
                if ($_filter && isset($split_field[2])) {
                    $preset_filters[$_filter->report_column] = $split_field[2];
                }
            }
            $field_data = collect($child_fields);
        }

        // Turn the field-map into the columns-map the component expects
        $columns = array();
        foreach($field_data as $fld) {
            $_qry = (is_null($fld->qry_as)) ? $fld->qry : $fld->qry_as;
            $columns[] = array('text' => $fld->legend, 'value' => $_qry, 'active' => $fld->active,
                               'reload' => $fld->reload);
        }

        return view('reports.preview',compact('preset_filters','columns'));
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
        $fields = $report->reportFields;
        return view('reports.show', compact('report', 'fields'));
    }

    /**
     * Return dates-available for each master report type, within contraints
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function getAvailable(Request $request)
    {
        $this->validate($request, ['filters' => 'required']);
        $_filters = json_decode($request->filters, true);

        // update the private global
        self::$input_filters = $_filters;

        // Setup institution limiter array for whereIn clause later
        $limit_to_insts = self::limitToInstitutions();

        // Setup where clause conditions for this report based on $input_filters
        $conditions = array();
        if (self::$input_filters['prov_id'] > 0) {
            $conditions[] = array('prov_id',self::$input_filters['prov_id']);
        }

        // Get counts and min/max yearmon for each master report
        $output = array();
        $models = ['TR' => '\\App\\TitleReport',    'DR' => '\\App\\DatabaseReport',
                   'PR' => '\\App\\PlatformReport', 'IR' => '\\App\\ItemReport'];
        $raw_query = "Count(*) as  count, min(yearmon) as YM_min, max(yearmon) as YM_max";
        foreach ($models as $key => $model) {
            $result = $model::when($limit_to_insts, function ($query, $limit_to_insts) {
                                return $query->whereIn('inst_id', $limit_to_insts);
                              })
                            ->when($conditions, function ($query, $conditions) {
                                return $query->where($conditions);
                            })
                            ->selectRaw($raw_query)
                            ->get()
                            ->toArray();
            $output[$key] = $result[0];
        }
        return response()->json(['reports' => $output],200);
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
         $this->validate($request, ['report_id' => 'required', 'columns' => 'required', 'filters' => 'required']);
         $report_id = $request->report_id;
         $columns = json_decode($request->columns, true);
         $_filters = json_decode($request->filters, true);
         $rows = (isset($request->itemsPerPage)) ? $request->itemsPerPage : 20;
         $preview = (isset($request->preview)) ? $request->preview : 0;

         // Get/set global things
         self::$input_filters = $_filters;
         $global_db = config('database.connections.globaldb.database');
         $conso_db = config('database.connections.consodb.database');

        // Get Report model, set report table target
        $report = Report::where('id',$report_id)->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Report ID: ' . $report_id . ' is undefined']);
        }

        if ($report->parent_id == 0) {
            $master_name = $report->name;
            $report_fields = $report->reportFields;
        } else {
            $master_name = $report->parent->name;
            $report_fields = $report->parent->reportFields;
        }
        $report_table = $conso_db . '.' . strtolower($master_name) . '_report_data';

        // Setup joins, fields to select, and group_by based on active columns
        self::setupQueryFields($report_fields, $columns);

        // Setup institution limiter array for whereIn clause later
        $limit_to_insts = self::limitToInstitutions();

        // Build where clause conditions for this report based on $input_filters
        $conditions = self::filterOnConditions();

        // Run the query
        $sortDir = ($request->sortDesc) ? 'desc' : 'asc';
        if ($master_name == "TR") {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Title';
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
                          return $query->whereIn('TR.inst_id', $limit_to_insts);
                      })
                      ->where($conditions)
                      ->groupBy($group_by)
                      ->orderBy($sortBy, $sortDir)
                      ->when($preview, function ($query, $preview) {
                          return $query->limit($preview)->get();
                      }, function ($query) {
                          return $query->get()->paginate($rows);
                      });
        } else if ($master_name == "DR") {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Dbase';
            $records = DB::table($report_table . ' as DR')
                      ->join($global_db . '.databases as DB', 'DR.db_id', 'DB.id')
                      ->when($joins['institution'], function ($query, $join) {
                          return $query->join($join, 'DR.inst_id', 'INST.id');
                      })
                      ->when($joins['provider'], function ($query, $join) {
                          return $query->join($join, 'DR.prov_id', 'PROV.id');
                      })
                      ->when($joins['platform'], function ($query, $join) {
                          return $query->join($join, 'DR.plat_id', 'PLAT.id');
                      })
                      ->when($joins['publisher'], function ($query, $join) {
                          return $query->join($join, 'DR.publisher_id', 'PUBL.id');
                      })
                      ->when($joins['datatype'], function ($query, $join) {
                          return $query->join($join, 'DR.datatype_id', 'DTYP.id');
                      })
                      ->when($joins['accessmethod'], function ($query, $join) {
                          return $query->join($join, 'DR.accessmethod_id', 'AMTH.id');
                      })
                      ->selectRaw($raw_fields)
                      ->when($limit_to_insts, function ($query, $limit_to_insts) {
                          return $query->whereIn('DR.inst_id', $limit_to_insts);
                      })
                      ->where($conditions)
                      ->groupBy($group_by)
                      ->orderBy($sortBy, $sortDir)
                      ->when($preview, function ($query, $preview) {
                          return $query->limit($preview)->get();
                      }, function ($query) {
                          return $query->get()->paginate($rows);
                      });
        } else if ($master_name == "IR") {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Title';
            $records = DB::table($report_table . ' as IR')
                      ->join($global_db . '.items as Item', 'IR.item_id', 'Item.id')
                      ->join($global_db . '.titles as TI', 'Item.title_id', 'TI.id')
                      ->when($joins['institution'], function ($query, $join) {
                          return $query->join($join, 'IR.inst_id', 'INST.id');
                      })
                      ->when($joins['provider'], function ($query, $join) {
                          return $query->join($join, 'IR.prov_id', 'PROV.id');
                      })
                      ->when($joins['platform'], function ($query, $join) {
                          return $query->join($join, 'IR.plat_id', 'PLAT.id');
                      })
                      ->when($joins['publisher'], function ($query, $join) {
                          return $query->join($join, 'IR.publisher_id', 'PUBL.id');
                      })
                      ->when($joins['accesstype'], function ($query, $join) {
                          return $query->join($join, 'IR.accesstype_id', 'ATYP.id');
                      })
                      ->when($joins['accessmethod'], function ($query, $join) {
                          return $query->join($join, 'IR.accessmethod_id', 'AMTH.id');
                      })
                      ->selectRaw($raw_fields)
                      ->when($limit_to_insts, function ($query, $limit_to_insts) {
                          return $query->whereIn('IR.inst_id', $limit_to_insts);
                      })
                      ->where($conditions)
                      ->groupBy($group_by)
                      ->orderBy($sortBy, $sortDir)
                      ->when($preview, function ($query, $preview) {
                          return $query->limit($preview)->get();
                      }, function ($query) {
                          return $query->get()->paginate($rows);
                      });
        } else {    // Run PR
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Platform';
            $records = DB::table($report_table . ' as PR')
                      ->when($joins['institution'], function ($query, $join) {
                          return $query->join($join, 'PR.inst_id', 'INST.id');
                      })
                      ->when($joins['provider'], function ($query, $join) {
                          return $query->join($join, 'PR.prov_id', 'PROV.id');
                      })
                      ->when($joins['platform'], function ($query, $join) {
                          return $query->join($join, 'PR.plat_id', 'PLAT.id');
                      })
                      ->when($joins['datatype'], function ($query, $join) {
                          return $query->join($join, 'PR.datatype_id', 'DTYP.id');
                      })
                      ->when($joins['accessmethod'], function ($query, $join) {
                          return $query->join($join, 'PR.accessmethod_id', 'AMTH.id');
                      })
                      ->selectRaw($raw_fields)
                      ->when($limit_to_insts, function ($query, $limit_to_insts) {
                          return $query->whereIn('PR.inst_id', $limit_to_insts);
                      })
                      ->where($conditions)
                      ->groupBy($group_by)
                      ->orderBy($sortBy, $sortDir)
                      ->when($preview, function ($query, $preview) {
                          return $query->limit($preview)->get();
                      }, function ($query) {
                          return $query->get()->paginate($rows);
                      });
        }
        return response()->json(['usage' => $records],200);
    }

    /**
     * Update usage report filter options based on Vue state date-range and/or active columns/filters
     *
     * @param  \Illuminate\Http\Request  $request
     *         Expects $request to be a JSON object holding the Vue state.filter_by object and report_id
     * @return \Illuminate\Http\Response
     */
    public function updateFilters(Request $request)
    {
        global $conso_db;
        $conso_db = config('database.connections.consodb.database');

        // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input']);
        }
        if (!isset($input['filters'])) {
            return response()->json(['result' => false, 'msg' => 'One or more inputs are missing!']);
        }

        // Put the input filters into a temporary array
        $_filters = $input['filters'];
        $report_id = $_filters['report_id'];

        // Get Report model, set report table target
        $report = Report::where('id',$report_id)->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Report ID: ' . $report_id . ' is undefined']);
        }

        // Get all known filters and reportfields
        $all_filters = ReportFilter::all();
        if ($report->parent_id == 0) {
            $master_name = $report->name;
            $report_fields = $report->reportFields;
            // $report_filters = $report->reportFilters;
        } else {
            $master_name = $report->parent->name;
            $report_fields = $report->parent->reportFields;
            // $report_filters = $report->parent->reportFilters;
        }
        $report_table = $conso_db . '.' . strtolower($master_name) . '_report_data';

        // Update filters to get rid of filters that don't apply to this report
        $active_ids = $report_fields->where('report_filter_id','<>',null)->pluck('report_filter_id')->toArray();
        $active_filters =  $all_filters->whereIn('id', $active_ids)->pluck('report_column')->toArray();
        foreach ($_filters as $key => $value) {
            if ($key == 'inst_id' || $key == 'institutiongroup_id' || $key == 'fromYM' || $key == 'toYM' ||
                $key == 'report_id' || $key == 'plat_id' || $key == 'prov_id' || in_array($key,$active_filters)) {
                self::$input_filters[$key] = $value;
            }
        }

        // Setup institution limiter array for whereIn clause later
        $limit_to_insts = self::limitToInstitutions();

        // Build where clause conditions for this report based on $input_filters
        // WITHOUT date-limiters since we're after the min/max available dates
        $conditions = self::filterOnConditions(false);

        // Query for min and max yearmon values
        $raw_query = "Count(*) as count, min(yearmon) as YM_min, max(yearmon) as YM_max";
        $result = DB::table($report_table)
                    ->when($limit_to_insts, function ($query, $limit_to_insts) {
                        return $query->whereIn('inst_id', $limit_to_insts);
                    })
                    ->when($conditions, function ($query, $conditions) {
                        return $query->where($conditions);
                    })
                    ->selectRaw($raw_query)
                    ->get()
                    ->toArray();
        $bounds = $result[0];

        // Query the XX_report_data table to build select options for all filters connected
        // to active columns (id >= 0) regardless of whether the filter is actively limiting.

        // Rebuild conditions to include date-range
        $conditions = self::filterOnConditions();

        $filter_data = array();
        // foreach ($report_filters as $filt) {
        foreach ($all_filters as $filt) {
            if (!isset(self::$input_filters[$filt->report_column])) {
                continue;
            }
            if (self::$input_filters[$filt->report_column] < 0) { // Don't query if column is inactive
                continue;
            }
            // Don't need to query to limit by institution
            if ($filt->report_column == 'inst_id' && !empty($limit_to_insts)) {
                $_ids = $limit_to_insts;
            } else {
                // Get distinct ids for the column from the report
                $_ids = DB::table($report_table)
                          ->when($limit_to_insts, function ($query, $limit_to_insts) {
                              return $query->whereIn('inst_id', $limit_to_insts);
                          })
                          ->where($conditions)
                          ->distinct()
                          ->pluck($filt->report_column);
            }
            // Setup an array of ID+name pairs for the filter, append it to $filter_data
            $_db = ($filt->is_global) ? config('database.connections.globaldb.database') . "."
                                      : config('database.connections.consodb.database') . ".";
            ${$filt->table_name} = DB::table($_db . $filt->table_name)
                                     ->whereIn('id',$_ids)
                                     ->get(['id','name'])
                                     ->toArray();
            array_unshift(${$filt->table_name}, ['id' => 0, 'name' => 'ALL']);
            $_key = rtrim($filt->table_name, "s");
            $filter_data[$_key] = ${$filt->table_name};
        }

        return response()->json(['result' => true, 'filters' => $filter_data, 'bounds' => $bounds]);
    }

    /**
     * Get usage report data records date-range, columns/filters, and sorting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function saveReportConfig(Request $request)
    {
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
        // $raw_fields = 'yearmon,';
        // $group_by = ['yearmon'];

        foreach ($fields as $field) {
            if (!isset($field->qry)) {
                continue;
            }
            $joins[$field->qry_as] = "";

            // If the column is active
            if (isset($columns[$field->qry_as])) {
                if ($columns[$field->qry_as]['active']) {
                    // set join if needed
                    if (!is_null($field->joins)) {
                        if (preg_match('/_conso_/',$field->joins)) {
                            $_join = preg_replace('/_conso_/', $conso_db, $field->joins);
                        }
                        if (preg_match('/_global_/',$field->joins)) {
                            $_join = preg_replace('/_global_/', $global_db, $field->joins);
                        }
                        $joins[$field->qry_as] = $_join;
                    }
                    // Add column to raw-list and update filter to column setting
                    $raw_fields .= $field->qry . ' as ' . $field->qry_as . ',';
                    if (isset($columns[$field->qry_as]['filter'])) {
                        $input_filters[$field->qry_as] = $columns[$field->qry_as]['filter'];
                    }
                    // Add column to group-by
                    if ($field->group_it) {
                        $group_by[] = $field->qry;
                    }
                }
            }
        }
        $raw_fields = rtrim($raw_fields, ',');
        return;
    }

    /**
     * Build an eloquent Where-Array based on $input_filters
     *   input_filter < 0 : means column is inactive, exclude it
     *   input_filter = 0 : means column is active, no filter applied
     *   input_filter > 0 : means column is being filtered by the given ID
     * Inst and Inst-groups are ignored and expected to be handled via a "whereIn" clause
     *
     * @return Array  $conditions
     */
    private function filterOnConditions($with_dates=true)
    {
        $conditions = array();
        foreach (self::$input_filters as $filt => $value) {

            // Skip report_id, date-fields, inst, and inst-group
            if ($filt == "report_id" ||
                $filt == "inst_id" || $filt == "institutiongroup_id" ||
                $filt == 'fromYM' || $filt == 'toYM') {
                continue;
            }

            // Set the where-condition
            if ($value > 0) {
                $conditions[] = array($filt,$value);
            }
        }

        // Add date range as a condition if they're *both* set
        if ($with_dates) {
            if (isset(self::$input_filters['fromYM']) && isset(self::$input_filters['toYM'])){
                if (self::$input_filters['fromYM'] != '' && self::$input_filters['toYM'] != '') {
                    $conditions[] = array('yearmon','>=',self::$input_filters['fromYM']);
                    $conditions[] = array('yearmon','<=',self::$input_filters['toYM']);
                }
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
            if (isset(self::$input_filters['inst_id'])) {
                if (self::$input_filters['inst_id'] > 0) {
                    array_push($return_values,self::$input_filters['inst_id']);
                }
            }
            if (isset(self::$input_filters['institutiongroup_id'])) {
                if (self::$input_filters['institutiongroup_id'] > 0) {
                    $group = InstitutionGroup::find(self::$input_filters['institutiongroup_id']);
                    $return_values = $group->institutions->pluck('id')->toArray();
                }
            }
        }
        return $return_values;
    }

}
