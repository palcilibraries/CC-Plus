<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
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
        self::$input_filters = [];
        $group_by = [];
        $raw_fields = '';
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
        $master_reports = Report::with('reportFields', 'children')
                                ->orderBy('name', 'asc')
                                ->where('parent_id', '=', 0)
                                ->get();
        $user_report_data = SavedReport::with('master')->orderBy('title', 'asc')
                                       ->where('user_id', '=', auth()->id())
                                       ->get();

        // Map the data to get a count fields in the inherited_fields string
        if ($user_report_data) {
            $user_reports = $user_report_data->map(function ($record) {
                                $record['field_count'] = sizeof(preg_split('/,/', $record->inherited_fields));
                                return $record;
            });
        } else {
            $user_reports = null;
        }
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
            $institutions = Institution::orderBy('name', 'ASC')->where('id', '<>', 1)->get(['id','name'])->toArray();
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
                                     $query->where('inst_id', 1)->orWhere('inst_id', $user_inst);
                                 })
                                 ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        }
        array_unshift($providers, ['id' => 0, 'name' => 'All Providers']);
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
        // If saved_id requested, get filters via SavedReport
        if (isset($request->saved_id)) {
            $saved_report = SavedReport::findOrFail($request->saved_id);
            if (!$saved_report->canManage()) {
                return response()->json(['result' => false, 'msg' => 'Access Forbidden (403)']);
            }
            $preset_filters = $saved_report->filterBy();
        } else {
            // otherwise, get filters from $request as Json
            $this->validate($request, ['filters' => 'required']);
            $preset_filters = json_decode($request->filters, true);
        }
        // update the private global
        self::$input_filters = $preset_filters;

        // Get the report model and all rows of the reportFilter model
        $report = Report::where('id', $preset_filters['report_id'])->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Report ID: ' . $report_id . ' is undefined']);
        }
        $all_filters = ReportFilter::all();

        // Get (master) fields
        if ($report->parent_id == 0) {   // previewing a master report?
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

                // If the field has a filter, update the filters array with any inherited value
                $filter = $all_filters->find($field->report_filter_id);
                if ($filter && !is_null($value)) {
                    $preset_filters[$filter->report_column] = $value;
                }
            }
            $field_data = collect($child_fields);
        }

        // Turn the field-map into a field-map and a columns-map for the component
        $fields = array();
        $columns = array();
        $year_mons = self::createYMarray();
        foreach ($field_data as $fld) {
            $key = (is_null($fld->qry_as)) ? $fld->qry : $fld->qry_as;
            $field = array('id' => $key, 'text' => $fld->legend, 'active' => $fld->active, 'reload' => $fld->reload);

            // Activate any field w/ an filter preset defined
            if (!$fld->active && $fld->reportFilter) {
                if (isset($preset_filters[$fld->reportFilter->report_column])) {
                    if ($preset_filters[$fld->reportFilter->report_column] > 0) {
                        $field['active'] = 1;
                    }
                }
            }
            $fields[] = $field;

            // If this is a summing-metric field, add a column for each month
            if (preg_match('/^sum/',$fld->qry)) {
                foreach ($year_mons as $ym) {
                    $columns[] = array('text' => $fld->legend, 'field' => $key, 'active' => $fld->active,
                                       'value' => $fld->qry_as . '_' . self::prettydate($ym));
                }

            // Otherwise add a single column to the map
            } else {
                $columns[] = array('text' => $fld->legend, 'field' => $key, 'active' => $fld->active, 'value' => $key);
            }
        }

        // Get list of saved reports for this user
        $saved_reports = SavedReport::where('user_id', auth()->id())->get(['id','title'])->toArray();
        return view('reports.preview', compact('preset_filters', 'fields', 'columns', 'saved_reports'));
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
        $con_name = Consortium::where('ccp_key','=',$con_key)->value('name');

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
            $filt = $all_filters->where('report_column','=',$key)->first();
            if ($filt) {
                if ($value <= 0) {  // skip if filter is off
                    continue;
                }
                if ($filt->report_column == 'inst_id' || $filt->report_column == 'institutiongroup_id' ||
                    $filt->report_column == 'prov_id') {
                    $out_file .= "_" . preg_replace('/ /','',$filt->model::where('id', $value)->value('name'));
                } else {
                    $limits .= ($limits=="") ? '' : ', ';
                    $limits .= rtrim($filt->table_name, "s") . "=";
                    $limits .= $filt->model::where('id', $value)->value('name');
                }
            }
        }
        if ( $limits != "" ) {
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

        // Get non-metric columns first (these are same across yearmons)
        // (this assumes that the caller ordered the $fields as: basics->metrics)
        foreach ($fields as $key => $data) {
            // "basic" column
            if (!preg_match('/^(searches_|total_|unique_|limit_|no_lic)/',$key)) {
                // $left_head[] = $key;
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
                    for ($m=1; $m<$num_months; $m++) {
                        $upper_right_head[] = '';
                    }
                } else {
                    $lower_right_head[] = $data['legend'];
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
        $writer->insertOne(array_merge($left_head,$lower_right_head));

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
        return response()->json(['reports' => $output], 200);
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
        $report_table = $conso_db . '.' . strtolower($master_name) . '_report_data';

        // If we're running an export
        if ($runtype == 'export') {

            // Build an organized field list and separate the "basic" fields from the "metric" ones
            $basic_fields = array();
            $metric_fields = array();
            foreach ($selected_fields as $key => $data) {
                if (!$data['active']) {
                    continue;
                }
                $data = $report_fields->where('qry_as','=',$key)->first();
                $legend = ($data) ? $data->legend : $key;

                // If metric field...
                if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/',$key)) {
                    $metric_fields[$key] = $data;
                    $metric_fields[$key]['legend'] = $legend;
                // treat as basic
                } else {
                    $basic_fields[$key] = $data;
                    $basic_fields[$key]['legend'] = $legend;
                }
            }

            // Call prepareExport tp setup the output stream with headers
            $export_settings = self::prepareExport($report, array_merge($basic_fields,$metric_fields));
            $csv_file = $export_settings['filename'];
            $writer = $export_settings['writer'];
        }

        // Setup joins, fields to select, and group_by based on active columns
        self::setupQueryFields($report_fields, $selected_fields);

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
                          // return $query->get()->paginate($rows);
                          return $query->get();
                      });
        } elseif ($master_name == "DR") {
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
                          return $query->get();
                          // return $query->get()->paginate($rows);
                      });
        } elseif ($master_name == "IR") {
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
                          return $query->get();
                          // return $query->get()->paginate($rows);
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
                          return $query->get();
                          // return $query->get()->paginate($rows);
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
    }

    /**
     * Update usage report preview settings and options
     *
     * @param  \Illuminate\Http\Request  $request
     *         Expects $request to be a JSON object holding the Vue state.filter_by object and report_id
     * @return \Illuminate\Http\Response
     */
    public function updateSettings(Request $request)
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
        $report = Report::where('id', $report_id)->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Report ID: ' . $report_id . ' is undefined']);
        }

        // Get all known filters and reportFields
        $all_filters = ReportFilter::all();
        if ($report->parent_id == 0) {
            $master_name = $report->name;
            $report_fields = $report->reportFields;
        } else {
            $master_name = $report->parent->name;
            $report_fields = $report->parent->reportFields;
        }
        $report_table = $conso_db . '.' . strtolower($master_name) . '_report_data';

        // Update filters to remove filters that don't apply to this report
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
        foreach ($input_fields as $fld) {

            $col = array('active' => $fld['active'], 'field' => $fld['id']);

            // If this is a summing-metric field, add a column for each month
            if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/',$fld['id'])) {
                foreach ($year_mons as $ym) {
                    $col['value'] = $fld['id'] . '_' . self::prettydate($ym);
                    $col['text'] = $fld['text'] . ' - ' . self::prettydate($ym);
                    $columns[] = $col;
                }
            // Otherwise add a single column to the map
            } else {
                $col['value'] = $fld['id'];
                $col['text'] = $fld['text'];
                $columns[] = $col;
            }
        }

        // Setup institution limiter array
        $filter_data = array();
        $limit_to_insts = self::limitToInstitutions();
        if (isset(self::$input_filters['institutiongroup_id'])) {
            $filter_data['institutiongroup'] = InstitutionGroup::get(['id','name'])->toArray();
        }

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

        foreach ($all_filters as $filt) {
            if (!isset(self::$input_filters[$filt->report_column]) || !isset($filt->model)) {
                continue;
            }
            if (self::$input_filters[$filt->report_column] < 0) { // Don't query if column is inactive
                continue;
            }
            // Don't need to query to limit by institution
            if (
                !empty($limit_to_insts) &&
                ($filt->report_column == 'inst_id' ||
                $filt->report_column == 'institutiongroup_id')
            ) {
                $_ids = $limit_to_insts;
            } else {
                // Get distinct ids for the column from the report (Groups are not report-fields ... skip them)
                if ($filt->report_column != 'institutiongroup_id') {
                    $_ids = DB::table($report_table)
                              ->when($limit_to_insts, function ($query, $limit_to_insts) {
                                  return $query->whereIn('inst_id', $limit_to_insts);
                              })
                              ->where($conditions)
                              ->distinct()
                              ->pluck($filt->report_column);
                }
            }
            // Setup an array of ID+name pairs for the filter options, append it to $filter_data
            if ($filt->table_name == 'institutiongroups') {
                // Get all of them instead of trying to build a list of "possibles" based on the data-in-table
                ${$filt->table_name} = InstitutionGroup::get(['id', 'name'])->toArray();
            } else {
                ${$filt->table_name} = $filt->model::whereIn('id', $_ids)->get(['id','name'])->toArray();
                array_unshift(${$filt->table_name}, ['id' => 0, 'name' => 'ALL']);
            }
            $_key = rtrim($filt->table_name, "s");
            $filter_data[$_key] = ${$filt->table_name};
        }

        return response()->json(['result' => true, 'columns' => $columns,
                                 'filters' => $filter_data, 'bounds' => $bounds]);
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

        foreach ($selected_fields as $key => $field) {
            if ($field['active']) {
                $data = $all_fields->where('qry_as','=',$key)->first();
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
                if (preg_match('/^sum/',$data->qry)) {
                    foreach ($year_mons as $ym) {
                        $raw_fields .= preg_replace('/@YM@/', $ym, $data->qry) . ' as ';
                        $raw_fields .= $data->qry_as . '_' . self::prettydate($ym) . ',';
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

        // Make sure all the joins exist, even if the field is inactive
        foreach ($all_fields as $field) {
            if (!is_null($field->joins) && !isset($joins[$field->qry_as])) {
                $joins[$field->qry_as] = "";
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
    private function filterOnConditions($with_dates = true)
    {
        $conditions = array();
        foreach (self::$input_filters as $filt => $value) {
            // Skip report_id, date-fields, inst, and inst-group
            if (
                $filt == "report_id" ||
                $filt == "inst_id" ||
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
            array_push($return_values, auth()->user()->inst_id);
        } else {
            if (isset(self::$input_filters['inst_id'])) {
                if (self::$input_filters['inst_id'] > 0) {
                    array_push($return_values, self::$input_filters['inst_id']);
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

    // Turn a fromYM/toYM range into an array of yearmon strings
    private function createYMarray() {
        $range = array();
        $start = strtotime(self::$input_filters['fromYM']);
        $end = strtotime(self::$input_filters['toYM']);
        if ($start > $end) {
            return $range;
        }
        while($start <= $end) {
          $range[] = date('Y-m', $start);
          $start = strtotime("+1 month", $start);
        }
        return $range;
    }

    // Reformat a date string
    private function prettydate($date) {
      list($yyyy, $mm) = explode("-", $date);
      return date("M_Y", mktime(0, 0, 0, $mm, 1, $yyyy));
    }

}
