<?php

namespace App\Http\Controllers;

use App\GlobalProvider;
use App\Consortium;
use App\Report;
use App\Provider;
use App\ConnectionField;
use Illuminate\Http\Request;
use DB;

class GlobalProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:GlobalAdmin']);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
      $gp_data = GlobalProvider::orderBy('name', 'ASC')->get();

      // get connection fields and master reports
      $connectionFields = ConnectionField::get(['id','name','label']);
      $all_connectors = $connectionFields->toArray();

      // Re-order maaster reports for the U/I
      $order = array('PR','DR','TR','IR');
      $_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
      $master_reports = array();
      foreach ($order as $_name) {
          $rpt = $_reports->where('name',$_name)->first();
          $master_reports[] = $rpt;
      }

      // get all the consortium instances and preserve the current instance database setting
      $instances = Consortium::get();
      $keepDB  = config('database.connections.consodb.database');

      // Build the providers array to pass back to the datatable
      $providers = array();
      foreach ($gp_data as $gp) {
        $provider = $gp->toArray();
        $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";
        $provider['reports_string'] = ($gp->master_reports) ?
                                      $this->makeReportString($gp->master_reports) : 'None';

        // Build arrays of booleans for connecion fields and reports for the U/I chackboxes
        $enabled = $gp->connectors;
        $cnx_state = array();
        foreach ($connectionFields as $fld) {
            $cnx_state[$fld->name] = (in_array($fld->id, $enabled)) ? true : false;
        }
        $provider['connector_state'] = $cnx_state;

        // Build reports enabled array in an arbitrary way (PR,DR,IR,TR)
        $enabled = $gp->master_reports;
        $rpt_state = array();
        foreach ($master_reports as $rpt) {
            $rpt_state[$rpt->name] = (in_array($rpt->id, $enabled)) ? true : false;
        }
        $provider['report_state'] = $rpt_state;

        // Walk all instances scan for harvests connected to this provider
        // If any are found, the can_delete flag will be set to false to disable deletion option in the U/I
        $harvest_count = 0;
        $provider['can_delete'] = true;
        foreach ($instances as $instance) {
            // switch the database connection
            config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
            try {
                DB::reconnect('consodb');
            } catch (\Exception $e) {
                return response()->json(['result' => 'Error connecting to database for the ' . $instance->name . ' instance!']);
            }
            // Get the provider and the number of harvests
            $con_prov = Provider::with('sushiSettings')->where('global_id', $gp->id)->first();
            if ($con_prov) {
                $harvest_count += $con_prov->sushiSettings->whereNotNull('last_harvest')->count();
            }
            if ($harvest_count > 0) {
                $provider['can_delete'] = false;
                break;
            };
        }
        $providers[] = $provider;
      }

      // Restore the database habdle and load the view
      config(['database.connections.consodb.database' => $keepDB]);
      return view('globalproviders.index', compact('providers', 'master_reports', 'all_connectors'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
      // Validate form inputs
      $this->validate($request, [ 'name' => 'required', 'is_active' => 'required', 'server_url_r5' => 'required' ]);
      $input = $request->all();
      // Create new global provider
      $provider = new GlobalProvider;
      $provider->name = $input['name'];
      $provider->is_active = $input['is_active'];
      $provider->server_url_r5 = $input['server_url_r5'];

      // Turn array of connection checkboxes into an array of IDs
      $connectors = array();
      if (isset($input['connector_state'])) {
        $all_conectors = ConnectionField::get();
        foreach ($all_conectors as $cnx) {
            if (!isset($input['connector_state'][$cnx->name])) continue;
            if ($input['connector_state'][$cnx->name]) {
                $connectors[] = $cnx->id;
            }
        }
        $provider->connectors = $connectors;
      }

      // Turn array of report checkboxes into an array of IDs
      $masterReports = array();
      $reports_string = "";
      if (isset($input['report_state'])) {
          $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
          foreach ($master_reports as $rpt) {
            if (!isset($input['report_state'][$rpt->name])) continue;
            if ($input['report_state'][$rpt->name]) {
                $masterReports[] = $rpt->id;
                $reports_string .= ($reports_string=="") ? "" : ", ";
                $reports_string .= $rpt->name;
            }
          }
      }
      $provider->master_reports = $masterReports;

      // If no connectors required, force customer_id ON
      $provider->connectors = (count($connectors) > 0) ? $connectors : array(1);
      $provider->save();
      $provider['can_delete'] = true;
      $provider['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $provider['reports_string'] = ($reports_string == "") ? 'None' : $reports_string;
      $provider['report_state'] = (isset($input['report_state'])) ? $input['report_state'] : array();
      $provider['connector_state'] = (isset($input['connector_state'])) ? $input['connector_state'] : array();

      return response()->json(['result' => true, 'msg' => 'Provider successfully created',
                               'provider' => $provider]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\GlobalProvider  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
      $provider = GlobalProvider::findOrFail($id);
      $orig_name = $provider->name;
      $orig_isActive = $provider->is_active;

      // Validate form inputs
      $this->validate($request, [ 'name' => 'required', 'is_active' => 'required', 'server_url_r5' => 'required' ]);
      $input = $request->all();
      $isActive = ($input['is_active']) ? 1 : 0;

      // Pull all connection fields and master reports
      $all_conectors = ConnectionField::get();
      $all_master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);

      // Gather IDs of reports that have been removed. We'll detach these from the consortia instance tables.
      // NOTE:: adding to the global master list doesn't automatically enable new reports in the instance tables.
      $dropped_reports = array();
      $original_reports = $provider->master_reports;
      foreach ($original_reports as $mr) {
          $_master = $all_master_reports->where('id', $mr)->first();
          if (!$_master) continue;
          if (!isset($input['report_state'][$_master->name])) continue;
          if (!$input['report_state'][$_master->name]) {
              $dropped_reports[] = $mr;
          }
      }

      // Update the record in the global table
      $provider->name = $input['name'];
      $provider->is_active = $isActive;
      $provider->server_url_r5 = $input['server_url_r5'];
      // Turn array of connection checkboxes into an array of IDs
      $new_connectors = array();
      foreach ($all_conectors as $cnx) {
          if (!isset($input['connector_state'][$cnx->name])) continue;
          if ($input['connector_state'][$cnx->name]) {
              $new_connectors[] = $cnx->id;
          }
      }
      $connectors_changed = ($provider->connectors != $new_connectors);
      $provider->connectors = $new_connectors;
      // Turn array of report checkboxes into an array of IDs
      $masterReports = array();
      $reports_string = "";
      foreach ($all_master_reports as $rpt) {
        if (!isset($input['report_state'][$rpt->name])) continue;
        if ($input['report_state'][$rpt->name]) {
            $masterReports[] = $rpt->id;
            $reports_string .= ($reports_string=="") ? "" : ", ";
            $reports_string .= $rpt->name;
        }
      }
      $provider->master_reports = $masterReports;
      $provider->save();
      $provider['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $provider['reports_string'] = ($reports_string == "") ? 'None' : $reports_string;

      // Get connector fields
      $fields = $all_conectors->whereIn('id',$provider->connectors)->pluck('name')->toArray();
      $unused_fields = $all_conectors->whereNotIn('id',$provider->connectors)->pluck('name')->toArray();

      // If changes implicate consortia-provider settings, Loop through all consortia instances
      if ($input['name']!=$orig_name || $isActive!=$orig_isActive || count($dropped_reports)>0 || $connectors_changed) {
          $instances = Consortium::get();
          $keepDB  = config('database.connections.consodb.database');
          $prov_updates = array('name' => $input['name']);
          // only update is_active if the global state is changing (otherwise leave consortium state as-is)
          if ($isActive != $orig_isActive) {
              $prov_updates['is_active'] = $isActive;
          }
          foreach ($instances as $instance) {
              // switch the database connection
              config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
              try {
                  DB::reconnect('consodb');
              } catch (\Exception $e) {
                  return response()->json(['result' => 'Error connecting to database for the ' . $instance->name . ' instance!']);
              }

              // Update the providers table
              $con_prov = Provider::where('global_id',$id)->first();
              if (!$con_prov) continue;
              $was_active = $con_prov->is_active;
              if ($input['name']!=$orig_name || $isActive!=$orig_isActive) {
                  $con_prov->update($prov_updates);
              }

              // Detach any reports that are no longer available
              foreach ($dropped_reports as $rpt_id) {
                  $con_prov->reports()->detach($rpt_id);
              }

              // Check, and possibly update, status for related sushi settings (skip disabled settings)
              $con_prov->load('sushiSettings','sushiSettings.institution');
              $settings = $con_prov->sushiSettings->where('status','<>','Disabled');
              foreach ($settings as $setting) {
                  // If required connectors all have values, check to see if sushi setting status needs updating
                  $setting_updates = array();
                  if ($setting->isComplete()) {
                      // Setting is Enabled, provider going inactive, suspend it
                      if ($setting->status == 'Enabled' && $was_active && !$con_prov->is_active ) {
                          $setting_updates['status'] = 'Suspended';
                      }
                      // Setting is Suspended, provider going active with active institution, enable it
                      if ($setting->status == 'Suspended' && !$was_active && $con_prov->is_active &&
                          $setting->institution->is_active) {
                          $setting_updates['status'] = 'Enabled';
                      }
                      // Setting status is Incomplete, provider is active and institution is active, enable it
                      if ($setting->status == 'Incomplete') {
                          $setting_updates['status'] = ($con_prov->is_active && $setting->institution->is_active) ?
                                                        'Enabled' : 'Suspended';
                      }
                      // Setting is Complete; clear '-missing-' labels on unused fields
                      foreach ($unused_fields as $uf) {
                          if ($setting->$uf == '-missing-') {
                              $setting_updates[$uf]= '';
                          }
                      }
                  // If required conenctors are missing value(s), mark them and update setting status tp Incomplete
                  } else {
                      $setting_updates['status'] = 'Incomplete';
                      foreach ($fields as $fld) {
                          if ($setting->$fld == null || $setting->$fld == '') {
                              $setting_updates[$fld] = "-missing-";
                          }
                      }
                  }
                  if (count($setting_updates) > 0) {
                      $setting->update($setting_updates);
                  }
              }
          }
          // Restore the database habdle
          config(['database.connections.consodb.database' => $keepDB]);
      }

      return response()->json(['result' => true, 'msg' => 'Global Provider settings successfully updated',
                               'provider' => $provider]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\GlobalProvider  $id
     */
    public function destroy($id)
    {
        $globalProvider = GlobalProvider::findOrFail($id);

        // Loop through all consortia instances and delete from the providers tables
        $instances = Consortium::get();
        $keepDB  = config('database.connections.consodb.database');
        foreach ($instances as $instance) {
            // switch the database connection
            config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
            try {
                DB::reconnect('consodb');
            } catch (\Exception $e) {
                return response()->json(['result' => 'Error connecting to database for the ' . $instance->name . ' instance!']);
            }

            try {
                Provider::where('global_id',$id)->delete();
            } catch (\Exception $ex) {
                return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
            }
        }
        // Restore the database habdle
        config(['database.connections.consodb.database' => $keepDB]);

        // Delete the global entry
        try {
            $globalProvider->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }

        return response()->json(['result' => true, 'msg' => 'Global Provider successfully deleted']);
    }

    /**
     * Build string representation of master_reports array
     *
     * @param  Array  $reports
     * @return String
     */
    private function makeReportString($reports) {
        $report_string = '';
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
        foreach ($reports as $id) {
            $rpt = $master_reports->where('id',$id)->first();
            if ($rpt) {
                $report_string .= ($report_string == '') ? '' : ', ';
                $report_string .= $rpt->name;
            }
        }
        return $report_string;
    }
}
