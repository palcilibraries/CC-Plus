<?php

namespace App\Http\Controllers;

use DB;
use App\SavedReport;
use App\Report;
use App\ReportField;
use App\ReportFilter;
use App\Provider;
use App\Platform;
use App\Institution;
use App\InstitutionGroup;
use App\HarvestLog;
use App\Alert;
use App\SystemAlert;
use Illuminate\Http\Request;

class SavedReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
      // Get and map the standard Counter reports
      $master_reports = Report::with('reportFields', 'children', 'reportFields.reportFilter')
                              ->where('parent_id',0)->orderBy('name', 'asc')->get();

      // Setup array of counter reports; [0] holds intro info, [1]->[n] hold report-series-by-master
      $counter_reports = array();
      $intro = array('series' => " >>> ", 'text' => "Select a tab to view standard COUNTER-5 report definitions by type",
                     'reports' => array());
      $counter_reports[] = $intro;

      // Procoess all master reports
      foreach ($master_reports as $master) {
          $title = preg_replace('/Master /','',$master->legend) . 's';
          $series = array('series' => $title, 'text' => '', 'reports' => array());
          $rpt = array('id' => $master->id, 'name' => $master->name, 'legend' => $master->legend, 'master' => "--Master--",
                       'field_count' => $master->reportFields->count());

          // Get report fields and filters for master
          $fields = $master->reportFields->where('active', true)->values();

          // Set any connected filters to 'All'
          $field_data = array();
          foreach ($fields as $field) {
              $rec = array('name' => $field->legend);
              $rec['filter'] =  ($field->reportFilter) ? "All" : '';
              $field_data[] = $rec;
          }
          $rpt['fields'] = $field_data;
          $series['reports'][] = $rpt;

          // Build report records for children of this master, including fields and filters
          foreach ($master->children as $child) {
              $rpt = array('id' => $child->id, 'name' => $child->name, 'legend' => $child->legend,
                          'master' => $master->name, 'field_count' => $child->fieldCount());
              $field_data = array();
              $inherited = $child->parsedInherited();
              foreach ($inherited as $key => $value) {
                  $field = $master->reportFields->find($key);
                  if (!$field) continue;
                  $rec = array('name' => $field->legend, 'filter' => '');

                  // Get filter preset if present
                  if ($field->reportFilter) {
                      if ($value > 0) {
                          if ($field->reportFilter->model) {
                              $rec['filter'] = $field->reportFilter->model::where('id', $value)->value('name');
                          }
                      } else {
                          $rec['filter'] = 'All';
                      }
                  }
                  $field_data[] = $rec;
              }
              $rpt['fields'] = $field_data;
              $series['reports'][] = $rpt;
          }
          $counter_reports[] = $series;
      }

      // Get formatted array of saved user reports
      $myname = auth()->user()->name;
      $report_data = $this->savedUserReports(auth()->id());
      return view('savedreports.my-saved', compact('report_data','counter_reports','myname'));
    }

    /**
     * Return a listing of the resource with detail for the home-dashboard
     *
     * @return JSON
     */
    public function home()
    {
        // Get list of saved reports for this user
        $thisUser = auth()->user();
        $user_inst = $thisUser->inst_id;
        $user_is_admin = $thisUser->hasRole("Admin");
        $user_is_viewer = $thisUser->hasRole("Viewer");

        // If the user is a Local Admin, redirect to their Institution summary page
        if (!$user_is_admin && $thisUser->hasRole('Manager')) {
            return redirect()->route('institutions.show', [$user_inst]);
        }

        // Get formatted array of saved user reports
        $report_data = $this->savedUserReports(auth()->id());

        // Summarize harvest data values and counts
        $limit_to_insts = ($user_is_admin || $user_is_viewer) ? array() : array($user_inst);
        $total_insts = Institution::where('is_active', true)->count() - 1;   // inst_id=1 doesn't count...
        $inst_count = ($user_is_admin || $user_is_viewer) ? $total_insts : 1;
        if ($user_is_admin) {
            $prov_count = Provider::where('is_active', true)->count();
        } else {
            $prov_count = Provider::where('is_active', true)
                                  ->where(function ($q) use ($user_inst) {
                                      return $q->where('inst_id', 1)
                                               ->orWhere('inst_id', $user_inst);
                                  })
                                  ->count();
        }

        // Get 10 most recent harvests
        $harvests = HarvestLog::with(
            'report:id,name',
            'sushiSetting',
            'sushiSetting.institution:id,name',
            'sushiSetting.provider:id,name'
        )
                              ->join('sushisettings', 'harvestlogs.sushisettings_id', '=', 'sushisettings.id')
                              ->when($limit_to_insts, function ($query, $limit_to_insts) {
                                    return $query->whereIn('sushisettings.inst_id', $limit_to_insts);
                              })
                              ->orderBy('harvestlogs.created_at', 'DESC')->limit(10)
                              ->get('harvestlogs.*')->toArray();

        // Get any active system alerts
        $system_alerts = SystemAlert::where('is_active', true)->get();

        // Get and organize up to 5 data/harvest alerts
        $data = Alert::with(
            'provider:id,name',
            'alertSetting',
            'alertSetting.reportField',
            'user:id,name',
            'harvest',
            'harvest.sushiSetting'
        )
                     ->latest()->limit(5)->get();

        $data_alerts = array();
        foreach ($data as $alert) {
            if (is_null($alert->alertsettings_id) && is_null($alert->harvest_id)) { // broken record?
                continue;
            }

            // If not admin, skip inst-specific alerts for other institutions
            $_inst_id = $alert->institution()->id;
            if ($_inst_id != 1  && $_inst_id != $user_inst && !$user_is_admin) {
                continue;
            }

            // Build a record for the view
            $record = array('id' => $alert->id, 'yearmon' => $alert->yearmon, 'status' => $alert->status,
                            'updated_at' => $alert->updated_at);

            if (!is_null($alert->alertsettings_id)) {
                $record['detail_url'] = "/alertsettings/" . $alert->alertsettings_id;
                $record['detail_txt'] = $alert->alertSetting->reportField->legend . " is out of bounds!";
            } else {
                $record['detail_url'] = "/harvestlogs/" . $alert->harvest_id . '/edit';
                $record['detail_txt'] = "Harvest failed";
            }
            $record['report_name'] = $alert->report()->name;
            $record['mod_by'] = ($alert->modified_by == 1) ? 'CC-Plus System' : $alert->user->name;
            $record['inst_name'] = ($_inst_id == 1)  ? "Consortia-wide" : $alert->institution()->name;
            $record['prov_name'] = $alert->provider->name;
            $data_alerts[] = $record;
        }

        return view('savedreports.home', compact(
            'inst_count',
            'prov_count',
            'report_data',
            'harvests',
            'total_insts',
            'system_alerts',
            'data_alerts'
        ));
    }

    /**
     * Get and show the requested resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // User must be able to manage the settings
        $report = SavedReport::with('master', 'report', 'user')->findOrFail($id);
        abort_unless($report->canManage(), 403);

        // Get master fields for $report->inherited_fields and tack on filter relationship
        $fields = $report->master->reportFields->whereIn('id', preg_split('/,/', $report->inherited_fields));
        $fields->load('reportFilter');

        // Get names and IDs all providers
        $all_providers = Provider::get(['id','name']);

        // Turn report->filterBy into key=>value arrays, named by the field column
        $filters = array();
        $filter_data = $report->filterBy();

        // If not filtering by instutiongroup, get names and IDs all institutions
        if ($filter_data['institutiongroup_id'] <= 0) {
            $all_institutions = Institution::where('id', '>', 1)->get(['id','name']);
        }
        $all_providers = Provider::get(['id','name']);
        $all_platforms = Platform::get(['id','name']);
        foreach ($fields as $field) {
            if ($field->reportFilter) {
                // If filtering by inst-group, skip institution ... we'we'll handle it last, below
                if ($field->qry_as == 'institution' && $filter_data['institutiongroup_id'] > 0) {
                    continue;
                }
                if ($field->qry_as == 'institution') {
                    if (sizeof($filter_data['inst_id']) == 0) {
                        $data['name'] = 'All';
                    } else {
                        $data['name'] = '';
                        foreach ($filter_data['inst_id'] as $val) {
                            $_inst = $all_institutions->where('id', $val)->first();
                            $data['name'] .= $_inst->name . ', ';
                        }
                        $data['name'] = rtrim(trim($data['name']), ',');
                    }
                } elseif ($field->qry_as == 'provider') {
                    if (sizeof($filter_data['prov_id']) == 0) {
                        $data['name'] = 'All';
                    } else {
                        $data['name'] = '';
                        foreach ($filter_data['prov_id'] as $val) {
                            $_prov = $all_providers->where('id', $val)->first();
                            $data['name'] .= $_prov->name . ', ';
                        }
                        $data['name'] = rtrim(trim($data['name']), ',');
                    }
                } elseif ($field->qry_as == 'platform') {
                    if (sizeof($filter_data['plat_id']) == 0) {
                        $data['name'] = 'All';
                    } else {
                        $data['name'] = '';
                        foreach ($filter_data['plat_id'] as $val) {
                            $_prov = $all_providers->where('id', $val)->first();
                            $data['name'] .= $_prov->name . ', ';
                        }
                        $data['name'] = rtrim(trim($data['name']), ',');
                    }
                } elseif ($field->qry_as == 'yop') {
                    if (sizeof($filter_data['yop']) == 0) {
                        $data['name'] = 'All';
                    } else {
                        $data['name'] = $filter_data['yop'][0] . ' to ' . $filter_data['yop'][1];
                    }
                } else {
                    $data = array('legend' => $field->legend, 'name' => 'All');
                    if (isset($filter_data[$field->reportFilter->report_column])) {
                        $filter_id = $filter_data[$field->reportFilter->report_column];
                        if ($field->reportFilter->model) {
                            $data['name'] = $field->reportFilter->model::where('id', $filter_id)->value('name');
                        }
                    }
                }

                $filters[$field->qry_as] = $data;
            }
        }

        // If filtering by institutiongroup, add it to the $filters array AS-IF institution, with a different legend
        if ($filter_data['institutiongroup_id'] > 0) {
            $filters['institution'] = array('legend' => 'Institution Group');
            $filters['institution']['name'] = InstitutionGroup::where('id', $filter_data['institutiongroup_id'])
                                                                   ->value('name');
        }

        // Set bounds for the from/to date selectors
        $conso_db = config('database.connections.consodb.database');
        $report_table = $conso_db . "." . strtolower($report->master->name) . '_report_data';
        $result = DB::table($report_table)
                    ->selectRaw("Count(*) as count, min(yearmon) as minYM, max(yearmon) as maxYM")
                    ->get()
                    ->toArray();
        $bounds['count'] = $result[0]->count;
        $bounds['minYM'] = $result[0]->minYM;
        $bounds['maxYM'] = $result[0]->maxYM;
        $latest_yr_start = max($bounds['minYM'], date("Y-m", strtotime('-11 months', strtotime($bounds['maxYM']))));
        $bounds['latestYear'] = $latest_yr_start . ' to ' . $bounds['maxYM'];

        return view('savedreports.edit', compact('report', 'fields', 'filters', 'bounds'));
    }

    /**
     * Save a report configuration
     * --> IF $request includes a non-zero 'save_id', the request is treated as an update
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function store(Request $request)
    {
        $this->validate($request, ['date_range'  => 'required', 'report_id' => 'required', 'fields' => 'required']);

        // Need somewhere to save it...
        if (!isset($request->title) && !isset($request->save_id)) {
            return response()->json(['result' => false, 'msg' => 'A name or ID of a saved report is required.']);
        }
        $save_id = $request->save_id;
        $report_id = $request->report_id;
        $input_fields = json_decode($request->fields, true);

       // Pull the model for report_id (points to presets in global table), and get all fields for it
        $_report = Report::findorFail($report_id);
        $master_id = ($_report->parent_id == 0) ? $_report->id : $_report->parent_id;
        $all_fields = ReportField::where('report_id', '=', $master_id)->get();

       // Get the saved report config
        if ($save_id != 0) {
            $saved_report = SavedReport::where('user_id', auth()->id())->where('id', $save_id)->first();
            if (!$saved_report) {
                return response()->json(['result' => false, 'msg' => 'Cannot access saved report data']);
            }

       // -or- create a new config
        } else {
            $saved_report = new SavedReport();
            $saved_report->title = $request->title;
            $saved_report->user_id = auth()->id();
            $saved_report->master_id = $master_id;
            $saved_report->report_id = $report_id;
        }

       // Build inherited fields and filters strings based on active columns/filters
        $filters = '';
        $inherited_fields = '';
        foreach ($all_fields as $field) {
            if (isset($input_fields[$field->qry_as])) {
                if ($input_fields[$field->qry_as]['active']) {
                    $inherited_fields .= ($inherited_fields == '') ? '' : ',';
                    $inherited_fields .= $field->id;
                    // Filters are saved as ID:VALUE or ID:[VALUE,VALUE,...], separated by "+"
                    if ($field->reportFilter) {
                        // Check for an array (i.e. Providers, institutions, and platforms)
                        if (is_array($input_fields[$field->qry_as]['limit'])) {
                            if (sizeof($input_fields[$field->qry_as]['limit']) > 0) {
                                $_filt = '';
                                foreach ($input_fields[$field->qry_as]['limit'] as $val) {
                                    $_filt .= ($_filt == '') ? $val : ',' . $val;
                                }
                                $filters .= ($filters == '') ? '' : '+';
                                $filters .= $field->reportFilter->id . ":";
                                $filters .= "[" . $_filt . "]";
                            }
                        } else {
                            if ($input_fields[$field->qry_as]['limit'] > 0) {
                                $filters .= ($filters == '') ? '' : '+';
                                $filters .= $field->reportFilter->id;
                                $filters .= ":" . $input_fields[$field->qry_as]['limit'];
                            }
                        }
                    }
                }
            }
        }

       // Tack on institution-group if it is in input_fields. It isn't a column, but is a filter-setting
        if (isset($input_fields['institutiongroup'])) {
            $filt = ReportFilter::where('report_column', '=', 'institutiongroup_id')->first();
            if ($input_fields['institutiongroup']['limit'] > 0 && $filt) {
                $filters .= "+" . $filt->id . ":" . $input_fields['institutiongroup']['limit'];
            }
        }

       // Save record with inherited fields, filters and dates
        $saved_report->inherited_fields = $inherited_fields;
        $saved_report->filters = $filters;
        $saved_report->date_range = $request->date_range;
        $saved_report->ym_from = $request->from;
        $saved_report->ym_to = $request->to;
        $saved_report->save();
        return response()->json(['result' => true, 'msg' => 'Configuration saved successfully']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\SavedReport $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $report = SavedReport::findOrFail($id);
        if (!$report->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

       // Validate form inputs
        $this->validate($request, ['title' => 'required', 'date_range' => 'required']);
        $input = $request->all();

       // Update the record and assign groups
        $report->update($input);

        return response()->json(['result' => true, 'msg' => 'Report settings successfully updated']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\SavedReport  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $report = SavedReport::findOrFail($id);
        if (!$report->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $report->delete();
        return response()->json(['result' => true, 'msg' => 'Saved report successfully deleted']);
    }

    /**
     * Return a formatted array of saved user reports
     *
     * @param  Integer $userId
     * @return Array
     */
    private function savedUserReports($userId)
    {
        // Get list of saved reports for this user
        $saved_reports = SavedReport::with('master')->where('user_id', $userId)->get();

        // Get the report filters
        $all_filters = ReportFilter::get(['id','table_name']);

        // Setup raw fields for what we need from the harvestlog
        $count_fields  = "sushisettings.inst_id, ";
        $count_fields .= "count(*) as total, sum(case when harvestlogs.status='Success' then 1 else 0 end) as success";

        // Build the output data array
        $report_data = array();
        foreach ($saved_reports as $report) {
            $last_harvest = HarvestLog::where('report_id', '=', $report->master->id)->max('yearmon');
            $data = array('id' => $report->id, 'title' => $report->title, 'last_harvest' => $last_harvest,
                          'master_id' => $report->master_id, 'master_name' => $report->master->name);

            // Handle institution/group filters
            $limit_to_insts = array();  // default to no limit
            $filter_vals = $report->parsedFilters();
            foreach ($filter_vals as $key => $val) {
                $filt = $all_filters->where('id', $key)->first();
                if (!$filt) {
                    continue;
                }
                if ($filt->table_name == 'institutions') {
                    $limit_to_insts = $val; // $val should be an array....
                    break;
                } elseif ($filt->table_name == 'institutiongroups') {
                    if ($val > 0) {
                        $group = InstitutionGroup::find($val);
                        $limit_to_insts = $group->institutions->pluck('id')->toArray();
                        break;
                    }
                }
            }

            // Pull by-institution harvest/error counts, add to report_data
            $inst_harv = HarvestLog::join('sushisettings', 'harvestlogs.sushisettings_id', '=', 'sushisettings.id')
                                  ->when($limit_to_insts, function ($query, $limit_to_insts) {
                                        return $query->whereIn('sushisettings.inst_id', $limit_to_insts);
                                  })
                                  ->where('report_id', '=', $report->master->id)
                                  ->where('yearmon', '=', $last_harvest)
                                  ->selectRaw($count_fields)
                                  ->groupBy('sushisettings.inst_id')
                                  ->get(['inst_id','total','success'])
                                  ->toArray();

            $data['successful'] = 0;
            $data['inst_count'] = sizeof($inst_harv);
            foreach ($inst_harv as $inst) {
                $data['successful'] += ($inst['total'] == $inst['success']) ? 1 : 0;
            }
            $report_data[] = $data;
        }
        return $report_data;
    }
}
