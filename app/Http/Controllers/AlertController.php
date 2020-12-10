<?php

namespace App\Http\Controllers;
use DB;
use App\Alert;
use App\Provider;
use App\Institution;
use App\Report;
use App\Severity;
use App\SystemAlert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

   // Index method for Alerts Controller
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        $conso_db = config('database.connections.consodb.database');
        $json = ($request->input('json')) ? true : false;
        $statuses = Alert::getEnumValues('status');
        array_unshift($statuses, 'ALL');

        // Assign optional inputs to $filters array
        $filters = array('inst' => [], 'prov' => [], 'rept' => [], 'stat' => [], 'ymfr' => null, 'ymto' => null);
        if ($request->input('filters')) {
            $filter_data = json_decode($request->input('filters'));
            foreach ($filter_data as $key => $val) {
                if (!is_array($val) && $key != 'ymfr' && $key != 'ymto') {
                    $filters[$key] = array($val);
                } else {
                    $filters[$key] = $val;
                }
            }
        } else {
            $keys = array_keys($filters);
            foreach ($keys as $key) {
                if ($request->input($key)) {
                    if ($key == 'ymfr' || $key == 'ymto') {
                        $filters[$key] = $request->input($key);
                    } elseif (is_numeric($request->input($key))) {
                        $filters[$key] = array(intval($request->input($key)));
                    }
                }
            }
        }

        // Managers and users only see their own insts
        $show_all = $thisUser->hasAnyRole(["Admin","Viewer"]);
        if (!$show_all) {
            $filters['inst'] = array($thisUser->inst_id);
        }

        // Ensure sensible date ranges
        if (!is_null($filters['ymfr']) || !is_null($filters['ymto'])) {
            if (is_null($filters['ymfr'])) {
                $filters['ymfr'] = $filters['ymto'];
            }
            if (is_null($filters['ymto'])) {
                $filters['ymto'] = $filters['ymfr'];
            }
        }

        // Build arrays for the filter-options. Skip if returning JSON
        if (!$json) {
            // Setup array of institutions
            if ($show_all) {
                $inst_data = Institution::where('id', '<>', 1)->orderBy('name', 'ASC')->get(['id', 'name']);
                $institutions = $inst_data->toArray();
            } else {
                $inst_data = Institution::whereIn('id', $filters['inst'])->get(['id', 'name']);
                $institutions = $inst_data->toArray();
            }

            // Build an array of providers and master-report names
            if ($show_all) {
                $providers = Provider::orderBy('name', 'ASC')->get(['id', 'name'])->toArray();
            } else {
                $providers = DB::table($conso_db . '.providers as prv')
                          ->join($conso_db . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                          ->where('prv.inst_id', 1)
                          ->orWhere('prv.inst_id', $thisUser->inst_id)
                          ->orderBy('prv.name', 'ASC')
                          ->get(['prv.id','prv.name'])
                          ->toArray();
            }
            $reports = Report::where('parent_id', '=', 0)->get(['id', 'name'])->toArray();

            // Query for min and max yearmon values
            $bounds = array();
            $raw_query = "min(yearmon) as YM_min, max(yearmon) as YM_max";
            $result = Alert::selectRaw($raw_query)->get()->toArray();
            $bounds[0] = $result[0];
            $raw_query = "report_id, " . $raw_query;
            $rpt_result = DB::table($conso_db . ".harvestlogs")->select(DB::raw($raw_query))
                                                               ->groupBy('report_id')->get();
            foreach ($rpt_result as $rpt) {
                $bounds[$rpt->report_id] = array('YM_min' => $rpt->YM_min, 'YM_max' => $rpt->YM_max);
            }
        }

        // Get all system alerts and error-severities that apply to alerts
        $sysalerts = SystemAlert::with('severity')
                                ->orderBy('severity_id', 'DESC')->orderBy('updated_at', 'DESC')->get()->toArray();
        $severities = Severity::where('id', '<', 10)->get(['id','name'])->toArray();

        // Skip querying for records unless we're returning json
        // The vue-component will run a request for JSON data once it is mounted
        $alerts = array();
        if ($json) {
            $data = Alert::with('provider:id,name', 'alertSetting', 'alertSetting.reportField',
                                'alertSetting.institution', 'alertSetting.reportField.report', 'harvest',
                                'harvest.sushiSetting', 'harvest.sushiSetting.institution', 'user:id,name')
                         ->orderBy('alerts.created_at', 'DESC')
                         ->when(sizeof($filters['prov']) > 0, function ($qry) use ($filters) {
                             return $qry->whereIn('prov_id', $filters['prov']);
                         })
                         ->when(sizeof($filters['stat']) > 0, function ($qry) use ($filters) {
                             return $qry->whereIn('status', $filters['stat']);
                         })
                         ->when($filters['ymfr'], function ($qry) use ($filters) {
                             return $qry->where('yearmon', '>=', $filters['ymfr']);
                         })
                         ->when($filters['ymto'], function ($qry) use ($filters) {
                             return $qry->where('yearmon', '<=', $filters['ymto']);
                         })
                         ->get();

            foreach ($data as $alert) {
                if (is_null($alert->alertsettings_id) && is_null($alert->harvest_id)) { // broken record?
                    continue;
                }

                // Filter-by-inst (here, instead of in the query)
                $_inst_id = $alert->institution()->id;
                if ($filters['inst']) {
                    if (!in_array($alert->institution()->id, $filters['inst'])) {
                        continue;
                    }
                }

                // Filter-by-Report (here, instead of in the query)
                if ($filters['rept']) {
                    if (!in_array($alert->report()->id, $filters['rept'])) {
                        continue;
                    }
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
                $alerts[] = $record;
            };

            // Return results
            return response()->json(['alerts' => $alerts], 200);

        // Not returning JSON, the index/vue-component still needs these to setup the page
        } else {
            return view('alerts.dashboard',
                   compact('alerts', 'sysalerts', 'providers', 'statuses', 'severities', 'institutions', 'reports',
                           'bounds', 'filters'));
        }
    }

   /**
    * Update status for a given alert
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function updateStatus(Request $request)
    {
        abort_unless(auth()->user()->hasRole("Admin"), 403);

        // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
        }
        if (!isset($input['id']) || !isset($input['status'])) {
            return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
        }
        $alert = Alert::findOrFail($input['id']);

       // Validate form inputs
        $alert->status = $input['status'];
        $alert->modified_by = auth()->id();

       // Update the record
        $alert->save();
        return response()->json(['result' => true]);
    }

   /**
    * Refresh the records being displayed by applying either or both filters
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  int  $id
    * @return JSON
    */
    public function dashRefresh(Request $request)
    {
       // Validate form inputs
        $this->validate($request, ['filt_stat' => 'required', 'filt_prov' => 'required']);
       // Setup the where clause
        if (auth()->user()->hasRole("Admin")) { // default to all
            $__where = array();
        } else {  // default to just current user's inst
            $__where = array(['inst_id', '=', auth()->user()->inst_id]);
        }
        if ($request->filt_stat != "ALL") {
            $__where[] = array('status', '=', $request->filt_stat);
        }
        if ($request->filt_prov != 0) {
            $__where[] = array('prov_id', '=', $request->filt_prov);
        }

       // Get the records, and tack on related fields before returning json
       // (there's probably a better way to do this)
        $alerts = Alert::orderBy('id', 'ASC')->where($__where)->get();
        $data = array();
        foreach ($alerts as $alert) {
            $_rec = $alert->toArray();
            $_rec['detail'] = $alert->detail();
            $_rec['report_name'] = $alert->report()->name;
            if (auth()->user()->hasRole("Admin")) {
                $_rec['inst_id'] = $alert->institution()->id;
                $_rec['inst_name'] = ($alert->institution()->id < 2) ? 'Consortia-wide' : $alert->institution()->name;
            }
            $_rec['prov_name'] = $alert->provider->name;
            $data[] = $_rec;
        }

       // return the records and role flags
        $main = array('records' => $data, 'admin' => auth()->user()->hasRole('Admin'),
                                        'manager' => auth()->user()->hasRole('Manager') );
        return response()->json($main);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        abort_unless(auth()->user()->hasRole("Admin"), 403);
        $record = Alert::findOrFail($id);

        // Delete the harvestlog record itself
        $record->delete();
        return response()->json(['result' => true, 'msg' => 'Alert successfully deleted']);
    }
}
