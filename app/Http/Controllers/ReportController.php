<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Storage;
use Session;
use App\Consortium;
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
use App\HarvestLog;
use League\Csv\Writer;
use SplTempFileObject;

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
        global $raw_fields, $group_by, $joins;

        self::$input_filters = [];
        $raw_fields = '';
        $group_by = [];
        $joins = ['institution' => "", 'provider' => "", 'platform' => "", 'publisher' => "",
                  'datatype' => "", 'accesstype' => "", 'accessmethod' => "", 'sectiontype' => ""];
    }

    /**
     * View defined reports
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Get and map the standard Counter reports
        $master_reports = Report::with('reportFields', 'children')
                                ->orderBy('id', 'asc')
                                ->where('parent_id', '=', 0)
                                ->get();
        $counter_reports = array();
        foreach ($master_reports as $master) {
            $counter_reports[] = array('id' => $master->id, 'name' => $master->name, 'legend' => $master->legend,
                                       'master' => "--Master--", 'field_count' => $master->reportFields->count());
            foreach ($master->children as $child) {
                $counter_reports[] = array('id' => $child->id, 'name' => $child->name, 'legend' => $child->legend,
                                           'master' => $master->name, 'field_count' => $child->fieldCount());
            }
        }

        // Get and map the user-defined reports
        $user_report_data = SavedReport::with('master')->orderBy('title', 'asc')
                                       ->where('user_id', '=', auth()->id())
                                       ->get();
        if ($user_report_data) {
            $user_reports = $user_report_data->map(function ($record) {
                                $record['field_count'] = sizeof(preg_split('/,/', $record->inherited_fields));
                if ($record->date_range == 'latestMonth') {
                    $record['months'] = 'Most recent one';
                } elseif ($record->date_range == 'latestYear') {
                    $record['months'] = 'Most recent 12';
                } else {
                    $record['months'] = 'Custom: ' . $record->ym_from . ' to ' . $record->ym_to;
                }
                                return $record;
            });
        } else {
            $user_reports = null;
        }
        return view('reports.view', compact('counter_reports', 'user_reports'));
    }

    /**
     * Setup wizard for creating usage report summaries
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        // Get an array of providers with successful harvests (to limit choices below)
        $provs_with_data = self::hasHarvests('prov_id');

        // Setup arrays for the report creator
        if (auth()->user()->hasAnyRole(['Admin','Viewer'])) {
            $insts_with_data = self::hasHarvests('inst_id');
            $institutions = Institution::whereIn('id', $insts_with_data)->orderBy('name', 'ASC')->where('id', '<>', 1)
                                       ->get(['id','name'])->toArray();
            $inst_groups = InstitutionGroup::get(['name', 'id'])->toArray();
            $providers = Provider::with('reports')->whereIn('id', $provs_with_data)->orderBy('name', 'ASC')
                                 ->get(['id','name'])->toArray();
        } else {    // limited view
            $user_inst = auth()->user()->inst_id;
            $institutions = Institution::where('id', '=', $user_inst)->get(['id','name'])->toArray();
            $inst_groups = array();
            $providers = Provider::with('reports')->whereIn('id', $provs_with_data)
                                 ->where(function ($query) use ($user_inst) {
                                     $query->where('inst_id', 1)->orWhere('inst_id', $user_inst);
                                 })
                                 ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        }
        $reports = Report::with('reportFields', 'children')->orderBy('id', 'asc')->get()->toArray();
        $fields = ReportField::orderBy('id', 'asc')->get()->toArray();

        return view(
            'reports.create',
            compact('institutions', 'inst_groups', 'providers', 'reports', 'fields')
        );
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
     * Setup preview of usage data
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        // Start by getting a full filter-set (all elements from the datastore)
        // Saved reports have the active fields and filters built-in
        $title = "";
        if (isset($request->saved_id)) {
            $saved_report = SavedReport::with('master', 'master.reportFields')->findOrFail($request->saved_id);
            if (!$saved_report->canManage()) {
                return response()->json(['result' => false, 'msg' => 'Access Forbidden (403)']);
            }
            $title = $saved_report->title;
            $preset_filters = $saved_report->filterBy();
            $inherited = preg_split('/,/', $saved_report->inherited_fields);
            $field_data = ReportField::with('reportFilter')->where('report_id', '=', $saved_report->master_id)->get();
            $field_data->whereIn('id', $inherited)->transform(function ($record) {
                                                        $record['active'] = 1;
                                                        return $record;
            });
            $rangetype = $saved_report->date_range;

            // update the private global filters and get available data bounds
            self::$input_filters = $preset_filters;
            $model = $saved_report->master->name;
            $data = self::queryAvailable($model);

            // update preset_filters date values based on data available
            if ($rangetype == 'latestMonth') {
                $preset_filters['fromYM'] = $data[0]['YM_max'];
                $preset_filters['toYM'] = $data[0]['YM_max'];
            } elseif ($rangetype == 'latestYear') {
                $want_min = strtotime('-11 months', strtotime($data[0]['YM_max']));
                $have_min = strtotime($data[0]['YM_min']);
                $from = ($have_min > $want_min) ? date("Y-m", $have_min) : date("Y-m", $want_min);
                $preset_filters['fromYM'] = $from;
                $preset_filters['toYM'] = $data[0]['YM_max'];
            } else {    // Custom
                $preset_filters['fromYM'] = $saved_report->ym_from;
                $preset_filters['toYM'] = $saved_report->ym_to;
            }

        // If not previewing a saved report the filters should arrive via $request as Json
        } else {
            $this->validate($request, ['filters' => 'required']);
            $preset_filters = json_decode($request->filters, true);
            $rangetype = (isset($preset_filters['dateRange'])) ? $preset_filters['dateRange'] : 'Custom';
        }

        // update the private global
        self::$input_filters = $preset_filters;

        // Get the report model and all rows of the reportFilter model
        $report = Report::where('id', $preset_filters['report_id'])->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Report ID: ' . $report_id . ' is undefined']);
        }
        $all_filters = ReportFilter::all();

        // Create arrays holding all filter-options; handle institutions and providers separately
        $filter_options = array();

        // Providers and insts inclusion as options depend on successful harvests
        $show_all = (auth()->user()->hasAnyRole(['Admin','Viewer']));
        $provs_with_data = self::hasHarvests('prov_id');
        if ($show_all) {
            $insts_with_data = self::hasHarvests('inst_id');
            $filter_options['institution'] = Institution::whereIn('id', $insts_with_data)->where('id', '>', 1)
                                                        ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            $filter_options['provider'] = Provider::whereIn('id', $provs_with_data)->orderBy('name', 'ASC')
                                                  ->get(['id','name'])->toArray();
        } else {  // Managers and Users are limited their own inst
            $filter_options['institution'] = Institution::where('id', '=', auth()->user()->inst_id)
                                                        ->get(['id','name'])->toArray();

            $filter_options['provider'] = Provider::with('reports')->whereIn('id', $provs_with_data)
                                                  ->where(function ($query) {
                                                      $query->where('inst_id', 1)
                                                            ->orWhere('inst_id', auth()->user()->inst_id);
                                                  })
                                                  ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        }

        // Set options for the other filters
        foreach ($all_filters as $filter) {
            $_key = rtrim($filter->table_name, "s");
            // if ($_key != 'institution' && $_key != 'provider' && $_key != 'platform') {
            if ($_key != 'institution' && $_key != 'provider') {
                $result = $filter->model::orderBy('name', 'ASC')->get(['id','name'])->toArray();
                $filter_options[$_key] = $result;
            }
        }

        // Get fields if we're not loading a saved report
        if (!isset($request->saved_id)) {
            if ($report->parent_id == 0) {   // previewing a master report?
                $master_id = $report->id;
                $field_data = $report->reportFields;
            } else {
                // Build field array from inherited fields
                $master_fields = $report->parent->reportFields;
                // Turn report->inherited_fields into key=>value array
                $inherited = $report->parsedInherited();
                $child_fields = array();
                foreach ($inherited as $key => $value) {
                    $field = $master_fields->find($key);
                    if (!$field) {
                        continue;
                    }
                    $child_fields[] = $field;

                    // If the field has a filter, update the filters array with report-specific limits
                    $filter = $all_filters->find($field->report_filter_id);
                    if ($filter && !is_null($value)) {
                        $preset_filters[$filter->report_column] = $value;
                    }
                }
                $field_data = collect($child_fields);
            }
        }

        // Create fields and columns arrays for the component based on $field_data and preset filters
        $fields = array();
        $columns = array();
        $year_mons = self::createYMarray();
        foreach ($field_data as $fld) {
            $key = (is_null($fld->qry_as)) ? $fld->qry : $fld->qry_as;
            $field = array('id' => $key, 'text' => $fld->legend, 'active' => $fld->active, 'reload' => $fld->reload);

            // Activate any field w/ an filter preset defined
            if (!$fld->active && $fld->reportFilter) {
                if (isset($preset_filters[$fld->reportFilter->report_column])) {
                    $report_column = $fld->reportFilter->report_column;
                    if ($fld->qry_as == 'institution' || $fld->qry_as == 'provider' || $fld->qry_as == 'platform') {
                        if (sizeof($preset_filters[$report_column]) > 1 || $preset_filters[$report_column][0] > 0) {
                            $field['active'] = 1;
                        }
                    } else {
                        if ($preset_filters[$report_column] > 0) {
                            $field['active'] = 1;
                        }
                    }
                }
            }
            $fields[] = $field;

            // If this is a summing-metric field, add a column for each month
            if (preg_match('/^sum/', $fld->qry)) {
                foreach ($year_mons as $ym) {
                    $columns[] = array('text' => $fld->legend, 'field' => $key, 'active' => $fld->active,
                                       'value' => $fld->qry_as . '_' . self::prettydate($ym));
                }

            // Otherwise add a single column to the map
            } else {
                $columns[] = array('text' => $fld->legend,'field' => $key,'active' => $field['active'],'value' => $key);
            }
        }

        // Get list of saved reports for this user
        $saved_reports = SavedReport::where('user_id', auth()->id())->get(['id','title'])->toArray();
        return view(
            'reports.preview',
            compact('preset_filters', 'fields', 'columns', 'saved_reports', 'title', 'filter_options')
        );
    }

    /**
     * Setup export file: name, headers and info and return an active handle for writing data records
     *
     * @param Array $fields
     * @return League\Csv\Writer $writer
     */
    public function prepareExport($report, $fields)
    {
        // Get/set global things
        $filters = self::$input_filters;
        $con_key = Session::get('ccp_con_key');
        $con_name = Consortium::where('ccp_key', '=', $con_key)->value('name');

        // Setup the output stream for sending info and header records
        $writer = Writer::createFromFileObject(new SplTempFileObject());

        // Build some info to precede headers
        $rpt_info = array();
        $rpt_info[] = array("CC-Plus " . $report->legend, " Summary Report Created: " . date("d-M-Y G:i"));
        $rpt_info[] = array("Consortium: " . $con_name,
                            "Date Range: " . self::$input_filters['fromYM'] . " to " . self::$input_filters['toYM']);

        // Turn filter settings into an output line and/or as part of the output filename
        $limits = "";
        $out_file = "CCPLUS";
        $all_filters = ReportFilter::all();
        foreach (self::$input_filters as $key => $value) {
            $filt = $all_filters->where('report_column', '=', $key)->first();
            if ($filt) {
                if ($value <= 0) {  // skip if filter is off
                    continue;
                }
                if (
                    $filt->report_column == 'inst_id'
                    || $filt->report_column == 'institutiongroup_id'
                    || $filt->report_column == 'prov_id'
                ) {
                    $out_file .= "_" . preg_replace('/ /', '', $filt->model::where('id', $value)->value('name'));
                } else {
                    $limits .= ($limits == "") ? '' : ', ';
                    $limits .= rtrim($filt->table_name, "s") . "=";
                    $limits .= $filt->model::where('id', $value)->value('name');
                }
            }
        }
        if ($limits != "") {
            $rpt_info[] = array("Limited By: " . $limits);
        }
        $out_file .= "_" . $report->name . "_";
        $out_file .= self::$input_filters['fromYM'] . "_" . self::$input_filters['toYM'] . ".csv";

    // Check for ACTIVE alerts  and add a summary row linked to the alerts page/dashboard.
    //
    // if ( $alert_counts['Active'] > 0 ) {
    //   $warning  = "Warning! - At least one active alert is set for data in this report";
    //   $warning .= " details are here: /alerts\n\n";
    //   $rpt_info .= array($warning);
    // }

        // Setup header row(s)
        $left_head = array();
        $upper_right_head = array();
        $lower_right_head = array();
        $year_mons = self::createYMarray();
        $num_months = sizeof($year_mons);
        $has_metrics = false;

        // Get non-metric columns first (these are same across yearmons)
        // (this assumes that the caller ordered the $fields as: basics->metrics)
        foreach ($fields as $key => $data) {
            // "basic" column
            if (!preg_match('/^(searches_|total_|unique_|limit_|no_lic)/', $key)) {
                $has_metrics = true;
                $left_head[] = $data['legend'];
                if ($num_months > 1) {
                    $upper_right_head[] = '';
                }

            // "metric" column?
            } else {
                $upper_right_head[] = $data['legend'];
                if ($num_months > 1) {
                    foreach ($year_mons as $ym) {
                        $lower_right_head[] = $ym;
                    }
                    for ($m = 1; $m < $num_months; $m++) {
                        $upper_right_head[] = '';
                    }
                } else {
                    $lower_right_head[] = $data['legend'];
                }
            }
        }

        // Tack on metric totals as right-most columns
        if ($num_months > 1) {
            if ($has_metrics) {
                $upper_right_head[] = 'Reporting Period Total';
                foreach ($fields as $key => $data) {
                    if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/', $key)) {
                        $lower_right_head[] = $data['legend'];
                    }
                }
            }
        }

        // Send info and header records to the stream
        foreach ($rpt_info as $arr) {
            $writer->insertOne($arr);
        }
        if ($num_months > 1) {
            $writer->insertOne($upper_right_head);
        }
        $writer->insertOne(array_merge($left_head, $lower_right_head));

        // Return the handle
        return array('writer' => $writer, 'filename' => $out_file);
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

        // Get report fields
        if ($report->parent_id == 0) {
            $fields = $report->reportFields;

        // Build field array from inherited fields; ignore values... for now
        } else {
            $master_fields = $report->parent->reportFields;

            // Turn report->inherited_fields into key=>value array
            $inherited = $report->parsedInherited();
            $child_fields = array();
            foreach ($inherited as $key => $value) {
                $field = $master_fields->find($key);
                if (!$field) {
                    continue;
                }
                $child_fields[] = $field;
            }
            $fields = collect($child_fields);
        }

        return view('reports.show', compact('report', 'fields'));
    }

    /**
     * Return an array IDs of institutions or providers with successful harvests
     *
     * @param  String  $column :  "prov_id" or "inst_id"
     * @return \Illuminate\Http\Response
     */
    public function hasHarvests($column)
    {
        // Setup the query
        $raw_query = $column . ",count(*) as count";
        $_join = config('database.connections.consodb.database') . '.sushisettings as Set';

        //Run it
        $ids_with_data = HarvestLog::join($_join, 'harvestlogs.sushisettings_id', 'Set.id')
                                   ->selectRaw($raw_query)
                                   ->where('status', 'Success')
                                   ->groupBy($column)
                                   ->pluck($column)->toArray();
        // Return the IDs
        return $ids_with_data;
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

        $data = self::queryAvailable();
        return response()->json(['reports' => $data], 200);
    }

    /**
     * Build an an array of counts to limit-by based on set filters
     *
     * @return Array $limit_to_insts
     */
    private function queryAvailable($model = '')
    {
        // Setup query limiters based on self::$input_filters
        $limit_to_insts = self::limitToIds('inst_id');
        $limit_to_provs = self::limitToIds('prov_id');
        $limit_to_plats = self::limitToIds('plat_id');

        // Get counts and min/max yearmon for each master report
        $output = array();
        $all_models = ['TR' => '\\App\\TitleReport',    'DR' => '\\App\\DatabaseReport',
                   'PR' => '\\App\\PlatformReport', 'IR' => '\\App\\ItemReport'];
        $raw_query = "Count(*) as  count, min(yearmon) as YM_min, max(yearmon) as YM_max";
        $models = ($model == '') ? $all_models : array($all_models[$model]);

        foreach ($models as $key => $model) {
            $result = $model::when($limit_to_insts, function ($query, $limit_to_insts) {
                                return $query->whereIn('inst_id', $limit_to_insts);
            })
                            ->when($limit_to_provs, function ($query, $limit_to_provs) {
                                return $query->whereIn('prov_id', $limit_to_provs);
                            })
                            ->when($limit_to_plats, function ($query, $limit_to_plats) {
                                return $query->whereIn('plat_id', $limit_to_plats);
                            })
                            ->selectRaw($raw_query)
                            ->get()
                            ->toArray();
            $output[$key] = $result[0];
        }
        return $output;
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
         $this->validate($request, ['report_id' => 'required', 'fields' => 'required', 'filters' => 'required']);
         $report_id = $request->report_id;
         $selected_fields = json_decode($request->fields, true);
         $_filters = json_decode($request->filters, true);
         $runtype = (isset($request->runtype)) ? $request->runtype : 'preview';
         $preview = (isset($request->preview) && $runtype == 'preview') ? $request->preview : 0;

         // Get/set global things
         self::$input_filters = $_filters;
         $global_db = config('database.connections.globaldb.database');
         $conso_db = config('database.connections.consodb.database');

        // Get Report model, set report table target
        $report = Report::where('id', $report_id)->first();
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
        $report_table = $conso_db . '.' . strtolower($master_name) . '_report_data as ' . $master_name;
    // $report_table = $conso_db . '.' . strtolower($master_name) . '_report_data';

        // If we're running an export
        if ($runtype == 'export') {
            // Build an organized field list and separate the "basic" fields from the "metric" ones
            $basic_fields = array();
            $metric_fields = array();
            foreach ($selected_fields as $key => $data) {
                if (!$data['active']) {
                    continue;
                }
                $data = $report_fields->where('qry_as', '=', $key)->first();
                $legend = ($data) ? $data->legend : $key;

                // If metric field...
                if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/', $key)) {
                    $metric_fields[$key] = $data;
                    $metric_fields[$key]['legend'] = $legend;
                // treat as basic
                } else {
                    $basic_fields[$key] = $data;
                    $basic_fields[$key]['legend'] = $legend;
                }
            }

            // Call prepareExport tp setup the output stream with headers
            $export_settings = self::prepareExport($report, array_merge($basic_fields, $metric_fields));
            $csv_file = $export_settings['filename'];
            $writer = $export_settings['writer'];
        }

        // Setup joins, fields to select, and group_by based on active columns
        self::setupQueryFields($report_fields, $selected_fields);

        // Setup arrays for institution, provider, and platform whereIn clauses
        $limit_to_insts = self::limitToIds('inst_id');
        $limit_to_provs = self::limitToIds('prov_id');
        $limit_to_plats = self::limitToIds('plat_id');

        // Build where clause conditions for this report based on the other filters
        $conditions = self::filterOnConditions();

        // Set sorting based on report-type
        $sortBy = $master_name . ".yearmon";    // default to ... something
        $sortDir = ($request->sortDesc) ? 'DESC' : 'ASC';
        if ($master_name == 'TR' || $master_name == 'IR') {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Title';
        } elseif ($master_name == 'DR') {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Dbase';
        } elseif ($master_name == 'PR') {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Platform';
        }

        // Run the query
        $records = DB::table($report_table)
                  ->when($master_name == "TR", function ($query, $join) use ($master_name, $global_db) {
                      return $query->join($global_db . '.titles as TI', $master_name . '.title_id', 'TI.id');
                  })
                  ->when($master_name == "DR", function ($query, $join) use ($master_name, $global_db) {
                      return $query->join($global_db . '.databases as DB', $master_name . '.db_id', 'DB.id');
                  })
                  ->when($master_name == "IR", function ($query, $join) use ($master_name, $global_db) {
                      return $query->join($global_db . '.items as Item', $master_name . '.item_id', 'Item.id')
                                   ->join($global_db . '.titles as TI', 'Item.title_id', 'TI.id');
                  })
                  ->when($joins['institution'], function ($query, $join) use ($master_name) {
                      return $query->join($join, $master_name . '.inst_id', 'INST.id');
                  })
                  ->when($joins['provider'], function ($query, $join) use ($master_name) {
                      return $query->join($join, $master_name . '.prov_id', 'PROV.id');
                  })
                  ->when($joins['platform'], function ($query, $join) use ($master_name) {
                      return $query->join($join, $master_name . '.plat_id', 'PLAT.id');
                  })
                  ->when($joins['publisher'], function ($query, $join) use ($master_name) {
                      return $query->join($join, $master_name . '.publisher_id', 'PUBL.id');
                  })
                  ->when($joins['datatype'], function ($query, $join) use ($master_name) {
                      return $query->join($join, $master_name . '.datatype_id', 'DTYP.id');
                  })
                  ->when($joins['accesstype'], function ($query, $join) use ($master_name) {
                      return $query->join($join, $master_name . '.accesstype_id', 'ATYP.id');
                  })
                  ->when($joins['accessmethod'], function ($query, $join) use ($master_name) {
                      return $query->join($join, $master_name . '.accessmethod_id', 'AMTH.id');
                  })
                  ->when($joins['sectiontype'], function ($query, $join) use ($master_name) {
                      return $query->join($join, $master_name . '.sectiontype_id', 'STYP.id');
                  })
                  ->selectRaw($raw_fields)
                  ->when($limit_to_insts, function ($query, $limit_to_insts) use ($master_name) {
                      return $query->whereIn($master_name . '.inst_id', $limit_to_insts);
                  })
                  ->when($limit_to_provs, function ($query, $limit_to_provs) use ($master_name) {
                      return $query->whereIn($master_name . '.prov_id', $limit_to_provs);
                  })
                  ->when($limit_to_plats, function ($query, $limit_to_plats) use ($master_name) {
                      return $query->whereIn($master_name . '.plat_id', $limit_to_plats);
                  })
                  ->where($conditions)
                  ->groupBy($group_by)
                  ->orderBy($sortBy, $sortDir)
                  ->when($preview, function ($query, $preview) {
                      return $query->limit($preview)->get();
                  }, function ($query) {
                      // return $query->get()->paginate($rows);
                      return $query->get();
                  });

        // If not exporting, return the records as JSON
        if ($runtype != 'export') {
            return response()->json(['usage' => $records], 200);
        }

        // Export the records
        foreach ($records as $rec) {
            $values = array_values((array) $rec);
            $writer->insertOne($values);
        }
        $writer->output($csv_file);
        if (auth()->user()->email == 'Administrator') {
            $_user =  session('ccp_con_key', '') . "_" . "Administrator";
        } else {
            $_user = auth()->user()->email;
        }
        $logrec = date("Y-m-d H:m") . " : " . $_user . " : " . $export_settings['filename'];
        Storage::append('exports.log', $logrec);
    }

    /**
     * Update usage report preview columns
     *
     * @param  \Illuminate\Http\Request  $request
     *         Expects $request to be a JSON object holding the Vue state.filter_by object and report_id
     * @return \Illuminate\Http\Response
     */
    public function updateReportColumns(Request $request)
    {
        // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input']);
        }
        if (!isset($input['filters']) || !isset($input['fields'])) {
            return response()->json(['result' => false, 'msg' => 'One or more inputs are missing!']);
        }

        // Put the input filters into a temporary array and get Report model
        $_filters = $input['filters'];
        $report_id = $_filters['report_id'];
        $report = Report::where('id', $report_id)->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Report ID: ' . $report_id . ' is undefined']);
        }

        // Get all reportFields
        if ($report->parent_id == 0) {
            $master_name = $report->name;
            $report_fields = $report->reportFields;
        } else {
            $master_name = $report->parent->name;
            $report_fields = $report->parent->reportFields;
        }

        // Update filters to remove filters that don't apply to this report
        $all_filters = ReportFilter::all();
        $active_ids = $report_fields->where('report_filter_id', '<>', null)->pluck('report_filter_id')->toArray();
        $active_filters =  $all_filters->whereIn('id', $active_ids)->pluck('report_column')->toArray();
        foreach ($_filters as $key => $value) {
            if (
                $key == 'inst_id'
                || $key == 'institutiongroup_id'
                || $key == 'fromYM'
                || $key == 'toYM'
                || $key == 'report_id'
                || $key == 'plat_id'
                || $key == 'prov_id'
                || in_array($key, $active_filters)
            ) {
                self::$input_filters[$key] = $value;
            }
        }

        // Build columns array based on fields and date-range
        $columns = array();
        $input_fields = $input['fields'];
        $year_mons = self::createYMarray();
        if (sizeof($year_mons) > 1) {
            $metric_totals = array();
        }
        foreach ($input_fields as $fld) {
            $col = array('active' => $fld['active'], 'field' => $fld['id']);

            // If this is a summing-metric field, add a column for each month
            if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/', $fld['id'])) {
                foreach ($year_mons as $ym) {
                    $col['value'] = $fld['id'] . '_' . self::prettydate($ym);
                    $col['text'] = $fld['text'] . ' - ' . self::prettydate($ym);
                    $columns[] = $col;
                }

                // If we're spanning multiple months, put the totals column into a separate array
                if (sizeof($year_mons) > 1) {
                    $col['value'] = "RP_" . $fld['id'];
                    $col['text'] = $fld['text'] . " - " . "Reporting Period Total";
                    $metric_totals[] = $col;
                }

            // Otherwise add a single column to the map
            } else {
                $col['value'] = $fld['id'];
                $col['text'] = $fld['text'];
                $columns[] = $col;
            }
        }

        // Tack on totals columns
        if (sizeof($year_mons) > 1) {
            $columns = array_merge($columns, $metric_totals);
        }

        return response()->json(['result' => true, 'columns' => $columns]);
    }

    /**
     * Set joins, the raw-select string, and group_by array based on fields and Columns
     *
     * @param  ReportField $all_fields
     * @param  Array $selected_fields
     * @return
     */
    private function setupQueryFields($all_fields, $selected_fields)
    {
        global $joins, $raw_fields, $group_by, $global_db, $conso_db;
        $year_mons = self::createYMarray();
        $total_fields = "";

        // Loop through all the fields
        foreach ($selected_fields as $key => $field) {
            if ($field['active']) {
                $data = $all_fields->where('qry_as', '=', $key)->first();
                if (!$data) {
                    continue;
                }

                // set join if needed
                if (!is_null($data->joins)) {
                    if (preg_match('/_conso_/', $data->joins)) {
                        $_join = preg_replace('/_conso_/', $conso_db, $data->joins);
                    }
                    if (preg_match('/_global_/', $data->joins)) {
                        $_join = preg_replace('/_global_/', $global_db, $data->joins);
                    }
                    $joins[$key] = $_join;
                }

                // Add column to the raw-list
                // If the field is a metric that sums-by-yearmon, assign metric-by-year as query fields
                if (preg_match('/^sum/', $data->qry)) {
                    foreach ($year_mons as $ym) {
                        $raw_fields .= preg_replace('/@YM@/', $ym, $data->qry) . ' as ';
                        $raw_fields .= $data->qry_as . '_' . self::prettydate($ym) . ',';
                    }
                    // (if we're spanning multiple months,extend the reporting-period-total string)
                    if (sizeof($year_mons) > 1) {
                        $total_fields .= "sum(" . $data->qry_as . ") as RP_" . $data->qry_as . ',';
                    }
                } else {
                    if ($data->qry != $data->qry_as) {
                        $raw_fields .= $data->qry . ' as ' . $data->qry_as . ',';
                    } else {
                        $raw_fields .= $data->qry_as . ',';
                    }

                    // update filter based on column setting
                    if (isset($field['limit'])) {
                        $input_filters[$key] = $field['limit'];
                    }

                    // Add column to group-by
                    if ($data->group_it) {
                        $group_by[] = $data->qry;
                    }
                }
            }
        }

        $raw_fields = $raw_fields . $total_fields;
        $raw_fields = rtrim($raw_fields, ',');
        return;
    }

    /**
     * Build an eloquent Where-Array based on $input_filters
     *   filter value > 0 : means column should be filtered by the given ID
     *   Inst, Prov, and Inst-groups are ignored and expected to be handled via a "whereIn" clause
     *
     * @return Array  $conditions
     */
    private function filterOnConditions($with_dates = true)
    {
        $conditions = array();
        foreach (self::$input_filters as $filt => $value) {
            // Skip report_id, date-fields, inst, prov, plat, and inst-group
            if (
                $filt == "report_id" ||
                $filt == "inst_id" ||
                $filt == "prov_id" ||
                $filt == "plat_id" ||
                $filt == "institutiongroup_id" ||
                $filt == 'fromYM' ||
                $filt == 'toYM'
            ) {
                continue;
            }

            // Set the where-condition
            if ($value > 0) {
                $conditions[] = array($filt,$value);
            }
        }

        // Add date range as a condition if they're *both* set
        if ($with_dates) {
            if (isset(self::$input_filters['fromYM']) && isset(self::$input_filters['toYM'])) {
                if (self::$input_filters['fromYM'] != '' && self::$input_filters['toYM'] != '') {
                    $conditions[] = array('yearmon','>=',self::$input_filters['fromYM']);
                    $conditions[] = array('yearmon','<=',self::$input_filters['toYM']);
                }
            }
        }

        return $conditions;
    }

    /**
     * Build an an array of IDs we want to limit-by based on the input filters
     *
     * @param  String $column
     * @return Array $limit_to_IDs
     */
    private function limitToIds($column)
    {
        $return_values = array();

        // Handle institution cases first
        if ($column == 'inst_id') {
            // If user is not an "admin" or "viewer", return only their own inst.
            if (!auth()->user()->hasAnyRole(['Admin','Viewer'])) {
                array_push($return_values, auth()->user()->inst_id);
                return $return_values;

            // If both inst_id and group_id are set, return all inst_ids from the group
            } elseif (isset(self::$input_filters['institutiongroup_id'])) {
                if (self::$input_filters['institutiongroup_id'] > 0) {
                    $group = InstitutionGroup::find(self::$input_filters['institutiongroup_id']);
                    $return_values = $group->institutions->pluck('id')->toArray();
                }
                return $return_values;
            }
        }
        if (isset(self::$input_filters[$column])) {
            if (self::$input_filters[$column] > 0) {
                $return_values = self::$input_filters[$column];
            }
        }
        return $return_values;
    }

    // Turn a fromYM/toYM range into an array of yearmon strings
    private function createYMarray()
    {
        $range = array();
        $start = strtotime(self::$input_filters['fromYM']);
        $end = strtotime(self::$input_filters['toYM']);
        if ($start > $end) {
            return $range;
        }
        while ($start <= $end) {
            $range[] = date('Y-m', $start);
            $start = strtotime("+1 month", $start);
        }
        return $range;
    }

    // Reformat a date string
    private function prettydate($date)
    {
        list($yyyy, $mm) = explode("-", $date);
        return date("M_Y", mktime(0, 0, 0, $mm, 1, $yyyy));
    }
}
