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
    private $format;
    private $raw_fields;
    private $raw_where;
    private $subq_fields;
    private $subq_where;
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
        global $raw_fields, $group_by, $joins, $raw_where;

        self::$input_filters = [];
        $raw_fields = '';
        $raw_where = '';
        $subq_raw_fields = '';
        $subq_raw_where = '';
        $group_by = [];
        $format = 'Compact';
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
        return view('reports.view', compact('counter_reports'));
    }

    /**
     * Setup wizard for creating usage report summaries
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $thisUser = auth()->user();

        // Get an array of providers with successful harvests (to limit choices below)
        $provs_with_data = self::hasHarvests('prov_id');

        // Setup arrays for the report creator
        if ($thisUser->hasAnyRole(['Admin','Viewer'])) {
            $insts_with_data = self::hasHarvests('inst_id');
            $institutions = Institution::whereIn('id', $insts_with_data)->orderBy('name', 'ASC')->where('id', '<>', 1)
                                       ->get(['id','name'])->toArray();
            $inst_groups = InstitutionGroup::orderBy('name', 'ASC')->get(['name', 'id'])->toArray();
            $providers = Provider::with('reports')->whereIn('id', $provs_with_data)->orderBy('name', 'ASC')
                                 ->get(['id','name'])->toArray();
        } else {    // limited view
            $user_inst = $thisUser->inst_id;
            $institutions = Institution::where('id', '=', $user_inst)->get(['id','name'])->toArray();
            $inst_groups = array();
            $providers = Provider::with('reports')->whereIn('id', $provs_with_data)
                                 ->where(function ($query) use ($user_inst) {
                                     $query->where('inst_id', 1)->orWhere('inst_id', $user_inst);
                                 })
                                 ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        }
        $reports = Report::with('children')->orderBy('id', 'asc')->get()->toArray();
        $field_data = ReportField::orderBy('id', 'asc')->with('reportFilter')->get();
        $fields = array();
        foreach ($field_data as $rec) {
            $column = ($rec->reportFilter) ? $rec->reportFilter->report_column : null;
            $fields[] = ['id' => $rec->id, 'qry' => $rec->qry_as, 'report_id' => $rec->report_id, 'column' => $column];
        }
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
        $thisUser = auth()->user();

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
            $preset_filters['dateRange'] = $rangetype;

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
            return response()
                ->json(['result' => false, 'msg' => 'Report ID: ' . $preset_filters['report_id'] . ' is undefined']);
        }
        $all_filters = ReportFilter::all();

        // Create arrays holding all filter-options; handle institutions and providers separately
        $filter_options = array();

        // Providers and insts inclusion as options depend on successful harvests
        $show_all = ($thisUser->hasAnyRole(['Admin','Viewer']));
        $provs_with_data = self::hasHarvests('prov_id');
        if ($show_all) {
            $insts_with_data = self::hasHarvests('inst_id');
            $filter_options['institution'] = Institution::whereIn('id', $insts_with_data)->where('id', '>', 1)
                                                        ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            $filter_options['provider'] = Provider::whereIn('id', $provs_with_data)->orderBy('name', 'ASC')
                                                  ->get(['id','name'])->toArray();
        } else {  // Managers and Users are limited their own inst
            $filter_options['institution'] = Institution::where('id', '=', $thisUser->inst_id)
                                                        ->get(['id','name'])->toArray();

            $filter_options['provider'] = Provider::with('reports')->whereIn('id', $provs_with_data)
                                                  ->where(function ($query) use ($thisUser) {
                                                      $query->where('inst_id', 1)
                                                            ->orWhere('inst_id', $thisUser->inst_id);
                                                  })
                                                  ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        }

        // Set options for the other filters
        foreach ($all_filters as $filter) {
            if (is_null($filter->table_name)) { // yop
                continue;
            }
            $_key = rtrim($filter->table_name, "s");
            if ($_key != 'institution' && $_key != 'provider') {
                $result = $filter->model::orderBy('name', 'ASC')->where('name','<>',' ')->get(['id','name'])->toArray();
                $filter_options[$_key] = $result;
            }
        }

        // Get all master-fields
        if ($report->parent_id == 0) {
            $master_fields = $report->reportFields;
        } else {
            $master_fields = $report->parent->reportFields;
        }

        // If previewing a savedreport, the $inherited defines which columns are enabled
        if (isset($request->saved_id)) {
            foreach ($master_fields as $fld) {
                $fld->active = (in_array($fld->id,$inherited)) ?  true : false;
            }

        // not loading a saved report
        } else  {
            // If we're previewing a subview, the inherited fields determine which fields are active intially.
            // All will still be available to allow filtering or activation during the preview.
            if ($report->parent_id > 0) {
                // Turn report->inherited_fields into key=>value array
                $inherited = $report->parsedInherited();
                foreach ($master_fields as $field) {
                    $field->active = (array_key_exists($field->id, $inherited)) ? 1 : 0;
                    if ($field->active) {
                        $filter = $all_filters->find($field->report_filter_id);
                        if ($filter && !is_null($inherited[$field->id])) {
                            $preset_filters[$filter->report_column] = $inherited[$field->id];
                        }
                    }
                }
            }
        }

        // Create fields and columns arrays for the component based on $master_fields and preset filters
        $fields = array();
        $columns = array();
        $year_mons = self::createYMarray();
        foreach ($master_fields as $fld) {
            $key = (is_null($fld->qry_as)) ? $fld->qry : $fld->qry_as;
            $field = array('id' => $key,'text' => $fld->legend,'active' => $fld->active,'is_metric' => $fld->is_metric);

            // Activate any field w/ an filter preset defined
            if (!$fld->active && $fld->reportFilter) {
                if (isset($preset_filters[$fld->reportFilter->report_column])) {
                    $report_column = $fld->reportFilter->report_column;
                    if (is_array($preset_filters[$report_column])) {
                        $_count = sizeof($preset_filters[$report_column]);
                        if ($_count >= 1) {
                            if ($preset_filters[$report_column][0] > 0 || $_count > 1) {
                                $field['active'] = 1;
                            }
                        }
                    } else {    // maybe unnecessary... just-in-case
                        if ($preset_filters[$report_column] > 0) {
                            $field['active'] = 1;
                        }
                    }
                }
            }
            $fields[] = $field;

            // If this is a summing-metric field, add a column for each month
            if ($fld->is_metric) {
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
            compact('preset_filters', 'fields', 'columns', 'saved_reports', 'title', 'filter_options', 'rangetype')
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
        global $format;
        $thisUser = auth()->user();

        // Get/set global things
        $filters = self::$input_filters;
        $con_key = Session::get('ccp_con_key');
        $con_name = Consortium::where('ccp_key', '=', $con_key)->value('name');
        $all_filters = ReportFilter::all();

        // Setup the output stream for sending info and header records
        $writer = Writer::createFromFileObject(new SplTempFileObject());

        // Setup Report Header rows
        $multiple_insts = false;
        $group_name = '';
        $header_rows = array(["Report_Name",$report->legend]);
        $header_rows[] = array("Report_ID",$report->name);
        $header_rows[] = array("Release","5");
        if (isset(self::$input_filters['institutiongroup_id'])) {
            if (self::$input_filters['institutiongroup_id'] > 0) {
                $multiple_insts = true;
                $group_name = InstitutionGroup::where('id', self::$input_filters['institutiongroup_id'])->value('name');
                $header_rows[] = array("Institution_Group",$group_name);
            }
        }
        if (!$multiple_insts) {
            if (isset(self::$input_filters['inst_id'])) {
                $filt = $all_filters->where('report_column', '=', 'inst_id')->first();
                if (sizeof(self::$input_filters['inst_id']) == 0) {
                    $header_rows[] = array("Institution_Name","All");
                } elseif (sizeof(self::$input_filters['inst_id']) > 1) {
                    $multiple_insts = true;
                    $header_rows[] = array("Institution_Name","Multiple");
                } else {
                    $_name = Institution::where('id', self::$input_filters['inst_id'])->value('name');
                    $header_rows[] = array("Institution_Name",$_name);
                }
            } else {
                $header_rows[] = array("Institution_Name",
                                       Institution::where('id', $thisUser->inst_id)->value('name'));
            }
        }
        $header_rows[] = array("Institution_ID","");
        $_data = "";
        foreach ($fields as $fld) {
            if ($fld->is_metric) {
                $_data .= ($_data == "") ? ucwords($fld->qry_as, "_") : "; " . ucwords($fld->qry_as, "_");
            }
        }
        $header_rows[] = array("Metric_Types",$_data);
        $yops = "";
        $_data = "";
        // Loop across input_filters to build output filename at same time
        // as setting data strings for the Report_Filters and yops header rows
        $out_file = "CCPLUS";
        foreach (self::$input_filters as $key => $value) {
            $filt = $all_filters->where('report_column', '=', $key)->first();
            if ($filt) {
                if (!in_array($key, ['inst_id','institutiongroup_id','prov_id','plat_id','yop'])) {
                    // if the filter is not limiting, ignore for filename and Report_Filters
                    if (is_array($value)) {
                        if (sizeof($value) == 0) {
                            continue;
                        }
                        // Add to $_data for Report_Filters
                        if ($value[0] == 0) {
                            $_data .= ($_data == "") ? "" : "; ";
                            $_data .= $filt->attrib . ":All";
                        } elseif ($value[0] > 0) {
                            $_data .= ($_data == "") ? $filt->attrib : "; " . $filt->attrib;
                            $_data .= ":" . $filt->model::where('id', $value[0])->value('name');
                        }
                    } else {
                        if ($value == 0) {
                            $_data .= ($_data == "") ? "" : "; ";
                            $_data .= $filt->attrib . ":All";
                        } elseif ($value > 0) {
                            $_data .= ($_data == "") ? $filt->attrib : "; " . $filt->attrib;
                            $_data .= ":" . $filt->model::where('id', $value)->value('name');
                        }
                    }
                // YOP is in the filters... make the header row data for it here
                } elseif ($key == "yop") {
                    if (sizeof($value) == 2) {
                        $yops = "YOP:" . $value[0] . ' - ' . $value[1];
                    }
                // These filter values used to define the filename, but are not included in Report_Filters
                } elseif ($key == 'institutiongroup_id' && $group_name != '') {
                    $out_file .= "_" . $group_name;
                } elseif ($key == 'inst_id' || $key == 'prov_id' || $key == 'plat_id') {
                    if (sizeof($value) > 1) {
                        $out_file .= "_Multiple_" . $filt->table_name;
                    } elseif (sizeof($value) == 1) {
                        $out_file .= "_" . preg_replace('/ /', '', $filt->model::where('id', $value[0])->value('name'));
                    }
                }
            }
        }
        $header_rows[] = array("Report_Filters",$_data);
        $header_rows[] = array("Report_Attributes",$yops);
        $header_rows[] = array("Exceptions","");
        $_data  = "Begin_Date=" . self::$input_filters['fromYM'] . "-01; ";
        $_data .= "End_Date=" . date("Y-m-t", strtotime(self::$input_filters['toYM']));
        $header_rows[] = array("Reporting_Period",$_data);
        $header_rows[] = array("Created",date('c'));
        $header_rows[] = array("Created_By","CC Plus");
        $header_rows[] = array();
        $out_file .= "_" . $report->name . "_";
        $out_file .= self::$input_filters['fromYM'] . "_" . self::$input_filters['toYM'] . ".csv";

    // Check for ACTIVE alerts  and add a summary row linked to the alerts page/dashboard.
    //
    // if ( $alert_counts['Active'] > 0 ) {
    //   $warning  = "Warning! - At least one active alert is set for data in this report";
    //   $warning .= " details are here: /alerts\n\n";
    //   $rpt_info .= array($warning);
    // }

        // Setup header in 2-parts: "Basic" fields to the left, "Metric" fields to the right
        $left_head = array();
        $right_head = array();
        $year_mons = self::createYMarray();
        $num_months = sizeof($year_mons);
        $has_metrics = false;

        // Build left side the same, regardless of $format
        foreach ($fields as $field) {
            if ($field['is_metric']) {
                $has_metrics = true;
            } else {
                $left_head[] = $field['legend'];
            }
        }

        // If there are metrics, build right side. This side is $format-dependent
        if ($has_metrics) {
            // Metric-names for COUNTER format expressed as row-values (of Metric_Type), with counts in columns
            // labelled as YYYY_mm that come after the RP_Total column.
            if ($format == 'COUNTER') {
                $right_head[] = "Metric_Type";
                $right_head[] = "Reporting_Period_Total";
                foreach ($year_mons as $ym) {
                    $right_head[] = $ym;
                }

            // Counts for Metrics in 'Compact' format expressed in columns labelled <metric>_YYYY_mm,
            // Plus a Reporting Period Total for each metric. Single-month reports don't get an RP_total column
            } else {
                $met_head = array();
                $ttl_head = array();
                foreach ($fields as $field) {
                    if ($field['is_metric']) {
                        if ($num_months > 1) {
                            foreach ($year_mons as $ym) {
                                $met_head[] = $field['legend'] . ' ' . $ym;
                            }
                            $ttl_head[] = 'Reporting Period Total' . ' ' . $field['legend'];
                        } else {
                            $met_head[] = $field['legend'];
                        }
                    }
                }
                $right_head = ($num_months > 1) ? array_merge($met_head,$ttl_head) : $met_head;
            }
        }

        // Send info and header records to the stream
        foreach ($header_rows as $hdr) {
            $writer->insertOne($hdr);
        }
        $writer->insertOne(array_merge($left_head, $right_head));

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
        $report = Report::with('parent', 'children')->findOrFail($id);

        // Get report fields and filters for master reports
        $filters = array();
        if ($report->parent_id == 0) {
            $report->load('reportFields', 'reportFields.reportFilter');
            $fields = $report->reportFields->where('active', true)->values();

            // Set any connected filters to 'All'
            foreach ($fields as $field) {
                if ($field->reportFilter) {
                    $filters[$field->qry_as] = array('legend' => $field->legend, 'name' => 'All');
                    ;
                }
            }

        // Build fields for report-views based on inherited fields
        } else {
            // Turn report->inherited_fields into key=>value array and get full master-data
            $inherited = $report->parsedInherited();
            $master_report = Report::with('reportFields', 'reportFields.reportFilter')->find($report->parent_id);

            // Pull master-field data for each inherited field, including filters
            $child_fields = array();
            foreach ($inherited as $key => $value) {
                $field = $master_report->reportFields->find($key);
                if ($field) {
                    $child_fields[] = $field;
                }

                // Get filter preset if present
                if ($field->reportFilter) {
                    $data = array('legend' => $field->legend, 'name' => 'All');
                    if ($value > 0) {
                        if ($field->reportFilter->model) {
                            $data['name'] = $field->reportFilter->model::where('id', $value)->value('name');
                        }
                    }
                    $filters[$field->qry_as] = $data;
                }
            }
            // Make the child_fields a collection
            $fields = collect($child_fields);
        }
        return view('reports.show', compact('report', 'fields', 'filters'));
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
                                   ->where('harvestlogs.status', 'Success')
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

            // if no data, set dates to one month ago (to keep them from being ...1969)
            if ($result[0]['count'] == 0) {
                $result[0]['YM_min'] = date("Y-m", mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
                $result[0]['YM_max'] = $result[0]['YM_min'];
            }
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
         global $joins, $raw_fields, $raw_where, $subq_fields, $subq_where, $group_by, $global_db, $conso_db, $format;
         $thisUser = auth()->user();

         // Validate and deal w/ inputs
         $this->validate($request, ['report_id' => 'required', 'fields' => 'required', 'filters' => 'required']);
         $report_id = $request->report_id;
         $selected_fields = json_decode($request->fields, true);
         $_filters = json_decode($request->filters, true);
         $runtype = (isset($request->runtype)) ? $request->runtype : 'preview';
         $preview = (isset($request->preview) && $runtype == 'preview') ? $request->preview : 0;
         $format = (isset($request->format)) ? $request->format : 'COUNTER';
         $ignore_zeros = json_decode($request->zeros, true);

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
            $master_id = $report_id;
            $master_name = $report->name;
            $report_fields = $report->reportFields;
        } else {
            $master_id = $report->parent_id;
            $master_name = $report->parent->name;
            $report_fields = $report->parent->reportFields;
        }
        $report_table = $conso_db . '.' . strtolower($master_name) . '_report_data as ' . $master_name;

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

            // Call prepareExport to setup the output stream with headers
            $export_settings = self::prepareExport($report, array_merge($basic_fields, $metric_fields));
            $csv_file = $export_settings['filename'];
            $writer = $export_settings['writer'];
        }

        // Setup joins, fields to select, raw_where, and group_by based on active columns and formattting
        self::setupQueryFields($report_fields, $selected_fields);

        // Setup arrays for institution, provider, and platform whereIn clauses
        $limit_to_insts = self::limitToIds('inst_id');
        $limit_to_provs = self::limitToIds('prov_id');
        $limit_to_plats = self::limitToIds('plat_id');
        $limit_to_dtype = self::limitToIds('datatype_id');
        $limit_to_atype = self::limitToIds('accesstype_id');
        $limit_to_ameth = self::limitToIds('accessmethod_id');
        $limit_to_stype = self::limitToIds('sectiontype_id');
        // Create where clause conditions for this report beginning with date-range
        $conditions = self::filterDates();

        // Set sorting based on report-type
        $sortBy = $master_name . ".yearmon";    // default to ... something
        $sortDir = ($request->sortDesc) ? 'DESC' : 'ASC';
        if ($master_name == 'TR' || $master_name == 'IR') {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Title';
        } elseif ($master_name == 'DR') {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'Dbase';
        } elseif ($master_name == 'PR') {
            $sortBy = ($request->sortBy != '') ? $request->sortBy : 'platform';
        }

        // Run the query for "COUNTER" formatted output
        if ($format == "COUNTER") {
            $conditions[] = array('RF.is_metric',1);
            $inner_group = $group_by;
            $inner_group[] = 'yearmon';
            $records = DB::table(function ($query) use ($report_table, $joins, $subq_fields, $conditions, $inner_group,
                           $limit_to_insts, $limit_to_provs, $limit_to_plats, $limit_to_dtype, $limit_to_atype,
                           $limit_to_ameth, $limit_to_stype, $master_name, $master_id, $global_db) {
                      $query->from($report_table)
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
                      ->join($global_db . '.reportfields as RF', 'report_id', '=', $master_id, 'inner', true)
                      ->selectRaw($subq_fields)
                      ->when($limit_to_insts, function ($query, $limit_to_insts) use ($master_name) {
                          return $query->whereIn($master_name . '.inst_id', $limit_to_insts);
                      })
                      ->when($limit_to_provs, function ($query, $limit_to_provs) use ($master_name) {
                          return $query->whereIn($master_name . '.prov_id', $limit_to_provs);
                      })
                      ->when($limit_to_plats, function ($query, $limit_to_plats) use ($master_name) {
                          return $query->whereIn($master_name . '.plat_id', $limit_to_plats);
                      })
                      ->when($limit_to_dtype, function ($query, $limit_to_dtype) use ($master_name) {
                          return $query->whereIn($master_name . '.datatype_id', $limit_to_dtype);
                      })
                      ->when($limit_to_atype, function ($query, $limit_to_atype) use ($master_name) {
                          return $query->whereIn($master_name . '.accesstype_id', $limit_to_atype);
                      })
                      ->when($limit_to_ameth, function ($query, $limit_to_ameth) use ($master_name) {
                          return $query->whereIn($master_name . '.accessmethod_id', $limit_to_ameth);
                      })
                      ->when($limit_to_stype, function ($query, $limit_to_stype) use ($master_name) {
                          return $query->whereIn($master_name . '.sectiontype_id', $limit_to_stype);
                      })
                      ->when(self::$input_filters['yop'], function ($query) {
                          return $query->whereBetween('yop', self::$input_filters['yop']);
                      })
                      ->when(sizeof($conditions) > 0, function ($query) use ($conditions) {
                          return $query->where($conditions);
                      })
                      ->groupBy($inner_group);
            }, 'stats')
                ->selectRaw($raw_fields)
                ->groupBy($group_by)
                ->when($ignore_zeros, function ($query) {
                    return $query->havingRaw('Reporting_Period_Total>0');
                })
                ->orderBy($sortBy, $sortDir)
                ->orderBy('Metric_Type', 'ASC')
                ->when($preview, function ($query, $preview) {
                      return $query->limit($preview)->get();
                }, function ($query) {
                    return $query->get();
                });
        // Run the query for the "Compact" format
        } else {
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
                      ->when($limit_to_dtype, function ($query, $limit_to_dtype) use ($master_name) {
                          return $query->whereIn($master_name . '.datatype_id', $limit_to_dtype);
                      })
                      ->when($limit_to_atype, function ($query, $limit_to_atype) use ($master_name) {
                          return $query->whereIn($master_name . '.accesstype_id', $limit_to_atype);
                      })
                      ->when($limit_to_ameth, function ($query, $limit_to_ameth) use ($master_name) {
                          return $query->whereIn($master_name . '.accessmethod_id', $limit_to_ameth);
                      })
                      ->when($limit_to_stype, function ($query, $limit_to_stype) use ($master_name) {
                          return $query->whereIn($master_name . '.sectiontype_id', $limit_to_stype);
                      })
                      ->when(self::$input_filters['yop'], function ($query) {
                          return $query->whereBetween('yop', self::$input_filters['yop']);
                      })
                      ->when($ignore_zeros && $raw_where, function ($query) use ($raw_where) {
                          return $query->whereRaw($raw_where);
                      })
                      ->when(sizeof($conditions) > 0, function ($query) use ($conditions) {
                          return $query->where($conditions);
                      })
                      ->groupBy($group_by)
                      ->orderBy($sortBy, $sortDir)
                      ->when($preview, function ($query, $preview) {
                          return $query->limit($preview)->get();
                      }, function ($query) {
                          // return $query->get()->paginate($rows);
                          return $query->get();
                      });
        }
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
        if ($thisUser->email == 'Administrator') {
            $_user =  session('ccp_con_key', '') . "_" . "Administrator";
        } else {
            $_user = $thisUser->email;
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
        $_format = (isset($input['format'])) ? $input['format'] : 'Compact';

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

        // Assign global filter values with the filters that apply to this report
        $all_filters = ReportFilter::all();
        $active_ids = $report_fields->where('report_filter_id', '<>', null)->pluck('report_filter_id')->toArray();
        $active_filters =  $all_filters->whereIn('id', $active_ids)->pluck('report_column')->toArray();
        foreach ($_filters as $key => $value) {
            if (
                array_key_exists($key, self::$input_filters) ||
                in_array($key, $active_filters) ||
                $key == 'fromYM' ||
                $key == 'toYM'
            ) {
                self::$input_filters[$key] = $value;
            }
        }

        // Build columns array based on fields and date-range
        $columns = array();
        $input_fields = $input['fields'];
        $year_mons = self::createYMarray();

        // Build columns for COUNTER format
        if ($_format == 'COUNTER') {
            $metric_count = 0;
            foreach ($input_fields as $fld) {
                if ($fld['is_metric']) {
                    $metric_count++;
                } else {
                    $columns[] = array('active' => $fld['active'], 'field' => $fld['id'], 'value' => $fld['id'],
                                       'text' => $fld['text']);
                }
            }
            $columns[] = array('active' => 1, 'field' => 'Metric_Type', 'value' => 'Metric_Type',
                               'text' => 'Metric_Type');
            if ($metric_count > 0) {
                $columns[] = array('active' => 1, 'field' => 'Reporting_Period_Total',
                                   'value' => 'Reporting_Period_Total', 'text' => 'Reporting_Period_Total');
                foreach ($year_mons as $ym) {
                    $columns[] = array('active' => 1, 'field' => $ym, 'value' => $ym, 'text' => $ym);
                }
            }

        // Build columns for Compact format
        } else {
            if (sizeof($year_mons) > 1) {
                $metrics = array();
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
                        $metrics[] = $col;
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
                $columns = array_merge($columns, $metrics);
            }
        }
        return response()->json(['result' => true, 'columns' => $columns]);
    }

    /**
     * Set joins, the raw-select string, and group_by array based on fields, columns, and formatting
     *
     * @param  ReportField $all_fields
     * @param  Array $selected_fields
     * @param  String $format
     * @return
     */
    private function setupQueryFields($all_fields, $selected_fields)
    {
        global $joins, $raw_fields, $group_by, $subq_fields, $subq_where, $global_db, $conso_db, $raw_where, $format;
        $year_mons = self::createYMarray();
        $total_fields = "";
        $subq_case = "";

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

                // Output format drives how query fields and clauses are built
                // For "COUNTER", metrics and joins are embedded in a subquery
                if ($format == 'COUNTER') {
                    if ($data->is_metric) {
                        $subq_case .= $data->qry_counter . ' ';
                    } else {
                        $raw_fields .= $data->qry_as . ',';
                        if ($data->qry != $data->qry_as) {
                            $subq_fields .= $data->qry . ' as ' . $data->qry_as . ',';
                        } else {
                            $subq_fields .= $data->qry_as . ',';
                        }
                    }
                    // Group the field if reportField says to
                    if ($data->group_it) {
                        $group_by[] = $data->qry_as;
                    }
                } else {
                    if ($data->is_metric) {
                        // For "Compact", Metric fields that sum-by-yearmon become output columns.
                        // Assign metric-by-year as query fields
                        foreach ($year_mons as $ym) {
                            $raw_fields .= preg_replace('/@YM@/', $ym, $data->qry) . ' as ';
                            $raw_fields .= $data->qry_as . '_' . self::prettydate($ym) . ',';
                        }
                        // (if we're spanning multiple months,extend the reporting-period-total string)
                        if (sizeof($year_mons) > 1) {
                            $total_fields .= "sum(" . $data->qry_as . ") as RP_" . $data->qry_as . ',';
                        }
                        // Build raw_where string (for ignoring zero-records)
                        // Metric fields that sum-by-yearmon become output columns. Assign metric-by-yr as query fields
                        $raw_where .= ($raw_where != "") ? " or " : "(";
                        $raw_where .= $data->qry_as . ">0";
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
                    }
                    // Group the field if reportField says to
                    if ($data->group_it) {
                        $group_by[] = $data->qry;
                    }
                }
            }
        }

        if ($format == 'COUNTER') {
            $raw_fields .= "Metric_Type, sum(data) as Reporting_Period_Total";
            $subq_fields .= "yearmon, RF.qry_as as Metric_Type, sum(CASE" . $subq_case . " ELSE 0 END) as data";

            // For "COUNTER", Metric names become column-values and sums are displayed by yearmon.
            foreach ($year_mons as $ym) {
                $raw_fields .= ",sum(case yearmon when '" . $ym . "' then data else 0 end) as '" . $ym . "'";
            }
            $group_by[] = "Metric_Type";
        } else {
            $raw_where .= ($raw_where == "") ? "" : ")";
            $raw_fields = $raw_fields . $total_fields;
            $raw_fields = rtrim($raw_fields, ',');
        }
        return;
    }

    /**
     * Build an eloquent Where-Array based on From/To yearmon range in $input_filters
     *
     * @return Array  $dates
     */
    private function filterDates()
    {
        $dates = array();

        // Add date range as a condition if they're *both* set
        if (isset(self::$input_filters['fromYM']) && isset(self::$input_filters['toYM'])) {
            if (self::$input_filters['fromYM'] != '' && self::$input_filters['toYM'] != '') {
                $dates[] = array('yearmon','>=',self::$input_filters['fromYM']);
                $dates[] = array('yearmon','<=',self::$input_filters['toYM']);
            }
        }
        return $dates;
    }

    /**
     * Build an an array of IDs we want to limit-by based on the input filters
     *
     * @param  String $column
     * @return Array $limit_to_IDs
     */
    private function limitToIds($column)
    {
        $thisUser = auth()->user();
        $return_values = array();

        // Handle institution cases explicitly
        if ($column == 'inst_id') {
            // If user is not an "admin" or "viewer", return only their own inst.
            if (!$thisUser->hasAnyRole(['Admin','Viewer'])) {
                array_push($return_values, $thisUser->inst_id);
                return $return_values;

            // If both inst_id and group_id are set, return all inst_ids from the group
            } elseif (isset(self::$input_filters['institutiongroup_id'])) {
                if (self::$input_filters['institutiongroup_id'] > 0) {
                    $group = InstitutionGroup::find(self::$input_filters['institutiongroup_id']);
                    $return_values = $group->institutions->pluck('id')->toArray();
                    return $group->institutions->pluck('id')->toArray();
                }
            }
            // Otherwise, return the inst_id filter values
            if (isset(self::$input_filters['inst_id'])) {
                $return_values = self::$input_filters['inst_id'];
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
