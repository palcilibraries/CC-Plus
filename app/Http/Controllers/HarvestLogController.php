<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use App\HarvestLog;
use App\Consortium;
use App\FailedHarvest;
use App\Report;
use App\Provider;
use App\Institution;
use App\SushiSetting;
use App\SushiQueueJob;
use Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class HarvestLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

   /**
    * Display a listing of the resource.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response or JSON
    */
    public function index(Request $request)
    {
        $json = ($request->input('json')) ? true : false;
        $conso_db = config('database.connections.consodb.database');

        // Assign optional inputs to $filters array
        $filters = array('inst' => [], 'prov' => [], 'rept' => [], 'stat' => [], 'ymfr' => null, 'ymto' => null);
        if ($request->input('filters')) {
            $filter_data = json_decode($request->input('filters'));
            foreach ($filter_data as $key => $val) {
                if ($val != 0) {
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
        $show_all = auth()->user()->hasAnyRole(["Admin","Viewer"]);
        if (!$show_all) {
            $filters['inst'] = array(auth()->user()->inst_id);
        }

        // Make sure dates are sensible
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
                $inst_data = Institution::where('id', '<>', 1)->get(['id', 'name']);
                $institutions = $inst_data->toArray();
            } else {
                $inst_data = Institution::whereIn('id', $filters['inst'])->get(['id', 'name']);
                $institutions = $inst_data->toArray();
            }

            // Build an array of $providers
            if ($show_all) {
                $providers = Provider::get(['id', 'name'])->toArray();
            } else {
                $providers = DB::table($consodb . '.providers as prv')
                          ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                          ->where('prv.inst_id', 1)
                          ->orWhere('prv.inst_id', auth()->user()->inst_id)
                          ->orderBy('prov_name', 'ASC')
                          ->get(['prv.id','prv.name'])
                          ->toArray();
            }
            // Get available reports and make sure dates are set right
            $reports = Report::where('parent_id', '=', 0)->get(['id', 'name'])->toArray();

            // Query for min and max yearmon values
            $bounds = array();
            $raw_query = "min(yearmon) as YM_min, max(yearmon) as YM_max";
            $result = HarvestLog::selectRaw($raw_query)->get()->toArray();
            $bounds[0] = $result[0];
            $raw_query = "report_id, " . $raw_query;
            $rpt_result = DB::table($conso_db . ".harvestlogs")->select(DB::raw($raw_query))
                                                               ->groupBy('report_id')->get();
            foreach ($rpt_result as $rpt) {
                $bounds[$rpt->report_id] = array('YM_min' => $rpt->YM_min, 'YM_max' => $rpt->YM_max);
            }
        }

        // Get the harvest rows based on sushisettings
        $settings = SushiSetting::when(sizeof($filters['inst']) > 0, function ($qry) use ($filters) {
                                      return $qry->whereIn('inst_id', $filters['inst']);
        })
                                ->when(sizeof($filters['prov']) > 0, function ($qry) use ($filters) {
                                      return $qry->whereIn('prov_id', $filters['prov']);
                                })
                                ->pluck('id')->toArray();

        $harvest_data = HarvestLog::with(
            'report:id,name',
            'sushiSetting',
            'sushiSetting.institution:id,name',
            'sushiSetting.provider:id,name'
        )
                                  ->whereIn('sushisettings_id', $settings)
                                  ->orderBy('updated_at', 'DESC')
                                  ->when(sizeof($filters['rept']) > 0, function ($qry) use ($filters) {
                                      return $qry->whereIn('report_id', $filters['rept']);
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

        $harvests = $harvest_data->map(function ($harvest) {
            $rec = array('id' => $harvest->id, 'updated_at' => $harvest->updated_at, 'yearmon' => $harvest->yearmon,
                         'attempts' => $harvest->attempts, 'status' => $harvest->status);
            $rec['institution'] = $harvest->sushiSetting->institution->name;
            $rec['provider'] = $harvest->sushiSetting->provider->name;
            $rec['report'] = $harvest->report->name;
            return $rec;
        });

        // Return results
        if ($json) {
            return response()->json(['harvests' => $harvests], 200);
        } else {
            return view('harvestlogs.index', compact(
                'harvests',
                'institutions',
                'providers',
                'reports',
                'bounds',
                'filters'
            ));
        }
    }

    /**
     * Setup wizard for manual harvesting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        if (auth()->user()->hasRole('Admin')) {
            $is_admin = true;
        } else {
            $user_inst = auth()->user()->inst_id;
            $is_admin = false;
        }

        // Allow for inbound provider and institution arguments
        $input = $request->all();
        $presets = array('inst_id' => null);
        $presets['prov_id'] = (isset($input['prov'])) ? $input['prov'] : null;
        if (isset($input['inst'])) {
            $presets['inst_id'] = ($is_admin) ? $input['inst'] : $user_inst;
        }

        // Get IDs of all possible prov_ids from the sushisettings table
        $possible_providers = SushiSetting::distinct('prov_id')->pluck('prov_id')->toArray();
        if ($is_admin) {     // Admin view
            $institutions = Institution::with('sushiSettings:id,inst_id,prov_id')
                                       ->where('id', '<>', 1)->where('is_active', true)
                                       ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            array_unshift($institutions, ['id' => 0, 'name' => 'Entire Consortium']);
            $providers = Provider::with('reports')
                                 ->whereIn('id', $possible_providers)->where('is_active', true)
                                 ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        } else {    // manager view
            $institutions = Institution::with('sushiSettings:id,inst_id,prov_id')
                                       ->where('id', '=', $user_inst)
                                       ->get(['id','name'])->toArray();
            $providers = Provider::with('reports')
                                 ->whereIn('id', $possible_providers)->where('is_active', true)
                                 ->where(function ($query) use ($user_inst) {
                                     $query->where('inst_id', 1)->orWhere('inst_id', $user_inst);
                                 })
                                 ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        }
        array_unshift($providers, ['id' => 0, 'name' => 'All Providers']);

        // Get reports for all that exist in the relationship table
        $table = config('database.connections.consodb.database') . '.' . 'provider_report';
        $report_ids = DB::table($table)->distinct('report_id')->pluck('report_id')->toArray();
        $all_reports = Report::whereIn('id', $report_ids)->orderBy('id', 'asc')->get()->toArray();

        return view('harvestlogs.create', compact('institutions', 'providers', 'all_reports', 'presets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        $is_admin = auth()->user()->hasRole('Admin');
        $user_inst = auth()->user()->inst_id;

        // Get args from the $input
        $this->validate(
            $request,
            ['inst_id' => 'required', 'prov_id' => 'required', 'reports' => 'required',
            'fromYM' => 'required',
            'toYM' => 'required',
            'when' => 'required']
        );
        $input = $request->all();

        // Set inst_id (force to user's inst if not an admin)
        $inst_id = ($is_admin) ? $input["inst_id"] : $user_inst;

        // Get provider info
        $prov_id = $input["prov_id"];
        if ($prov_id != 0) {
            $providers = Provider::with('sushiSettings', 'sushiSettings.institution:id,is_active', 'reports')
                                 ->where('id', $prov_id)->get();

            // Non-admin disallowed from harvesting non-consortium providers owned by other institutions
            if (!$is_admin && $providers[0]->inst_id != $user_inst && $providers[0]->inst_id != 1) {
                return response()->json(['result' => false, 'msg' => 'Requested provider is not authorized.']);
            }
        } else {
            $providers = Provider::with('sushiSettings', 'sushiSettings.institution:id,is_active', 'reports')
                                 ->where('is_active', '=', true)
                                 ->when(!$is_admin, function ($query, $user_inst) {
                                     return $query->where('inst_id', 1)->orWhere('inst_id', $user_inst);
                                 })->get();
        }

        // Set the status for the harvests we're creating based on "when"
        $state = "New";
        if ($input["when"] == 'now') {
            $state = "Queued";
            $con = Consortium::where('ccp_key', '=', session('ccp_con_key'))->first();
            if (!$con) {
                return response()->json(['result' => false, 'msg' => 'Error: Corrupt session or consortium settings.']);
            }
        }

        // Check From/To - truncate to current month if in future and ensure from <= to ,
        // then turn them into an array of yearmon strings
        $this_month = date("Y-m", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
        $to = ($input["toYM"] > $this_month) ? $this_month : $input["toYM"];
        $from = ($input["fromYM"] > $to) ? $to : $input["fromYM"];
        $year_mons = self::createYMarray($from, $to);

        // Loop for all months requested
        $num_queued = 0;
        $num_created = 0;
        $num_updated = 0;
        foreach ($year_mons as $yearmon) {
            // Loop for all providers
            foreach ($providers as $provider) {
               // Loop through all sushisettings for this provider
                foreach ($provider->sushiSettings as $setting) {
                   // If institution is inactive, -or- only processing a single instituution and this isn't it,
                   // skip to next setting.
                    if ((!$setting->institution->is_active) || ($inst_id != 0 && $setting->inst_id != $inst_id)) {
                        continue;
                    }

                   // Loop through all reports defined as available for this provider
                    foreach ($provider->reports as $report) {
                       // if this report isn't in $inputs['reports'], skip it
                        if (!in_array($report->name, $input['reports'])) {
                            continue;
                        }

                       // Insert new HarvestLog record; catch and prevent duplicates
                        try {
                            $harvest = HarvestLog::create(['status' => $state, 'sushisettings_id' => $setting->id,
                                                'report_id' => $report->id, 'yearmon' => $yearmon,
                                                'attempts' => 0]);
                            $num_created++;
                        } catch (QueryException $e) {
                            $errorCode = $e->errorInfo[1];
                            // Harvest already exists, reset it quietly
                            if ($errorCode == '1062') {
                                $harvest = HarvestLog::where([['sushisettings_id', '=', $setting->id],
                                                              ['report_id', '=', $report->id],
                                                              ['yearmon', '=', $yearmon]
                                                             ])->first();
                                $harvest->attempts = 0;
                                $harvest->status = $state;
                                $harvest->save();
                                $num_updated++;
                            } else {
                                return response()->json(['result' => false,
                                    'msg' => 'Failure adding to HarvestLog! Error code:' . $errorCode]);
                            }
                        }

                        // If user wants it added now create the queue entry - set replace_data to overwrite
                        if ($input["when"] == 'now') {
                            try {
                                $newjob = SushiQueueJob::create(['consortium_id' => $con->id,
                                                                 'harvest_id' => $harvest->id,
                                                                 'replace_data' => 1
                                                               ]);
                                $num_queued++;
                            } catch (QueryException $e) {
                                $code = $e->errorInfo[1];
                                if ($code == '1062') {     // If already in queue, continue silently
                                    continue;
                                } else {
                                    $msg = 'Failure adding Harvest ID: ' . $harvest->id . ' to Queue! Error ' . $code;
                                    return response()->json(['result' => false, 'msg' => $msg]);
                                }
                            }
                        }
                    }
                }
            }
        }

        // Send back confirmation with counts of what happened
        $msg  = "Success : " . $num_created . " new harvests added, " . $num_updated . " harvests updated";
        $msg .= ($num_queued > 0) ? ", and " . $num_queued . " queue jobs created." : ".";
        return response()->json(['result' => true, 'msg' => $msg]);
    }

    /**
     * Update status for a given harvest
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

        // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
        }
        if (!isset($input['id']) || !isset($input['status'])) {
            return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
        }

        // The new status will be based on one of 2 possible values:
        //   Reset: resets attempts to zero and requeues the harvest for immediate retrying
        //   Stop: Sets harvest to "Stopped", regardless of what it was before.
        $new_status_allowed = array('Reset', 'Stop');
        if (!in_array($input['status'], $new_status_allowed)) {
            return response()->json(['result' => false,
                                     'msg' => 'Invalid request: status cannot be set to requested value.']);
        }

        // Harvests w/ status= 'Success', 'Active', or 'Pending' are NOT changed
        $fixed_status = array('Success', 'Active', 'Pending');
        $harvest = HarvestLog::findOrFail($input['id']);
        if (in_array($harvest->status, $fixed_status)) {
            return response()->json(['result' => false,
                                     'msg' => 'Invalid request: harvest status cannot be changed.']);
        }

        // Stopping a harvest also means deleting any corresponding job thats in the queue
        if ($input['status'] == 'Stop') {
            $harvest->status = 'Stopped';
            $existing_job = SushiQueueJob::where('harvest_id', '=', $harvest->id)->first();
            if ($existing_job) {
                $existing_job->delete();
            }

         // Resetting means attempts get set to zero
        } else {
            $harvest->attempts = 0;
            $harvest->status = 'Queued';
        }

        // Update the harvest record
        try {
            $harvest->save();
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error updating harvest!']);
        }

        // If we're resetting, so create a Job entry if it doesn't exist
        if ($harvest->status == 'Queued') {
            $con = Consortium::where('ccp_key', '=', session('ccp_con_key'))->first();
            if (!$con) {
                return response()->json(['result' => false, 'msg' => 'Error: Corrupt session or consortium settings']);
            }
            try {
                $newjob = SushiQueueJob::create(['consortium_id' => $con->id,
                                                 'harvest_id' => $harvest->id,
                                                 'replace_data' => 1
                                               ]);
            } catch (QueryException $e) {
                $code = $e->errorInfo[1];
                if ($code != '1062') {     // If already in queue, continue silently
                    $msg = 'Failure adding Harvest ID: ' . $harvest->id . ' to Queue! Error ' . $code;
                    return response()->json(['result' => false, 'msg' => $msg]);
                }
            }
        }
        return response()->json(['result' => true, 'harvest' => $harvest]);
    }

   /**
    * Display a form for editting the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function edit($id)
    {
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        $harvest = HarvestLog::with(
            'report:id,name',
            'sushiSetting',
            'sushiSetting.institution:id,name',
            'sushiSetting.provider:id,name'
        )
                              ->findOrFail($id);
        $failed = FailedHarvest::with('ccplusError', 'ccplusError.severity')
                               ->where('harvest_id', '=', $id)->get()->toArray();
        return view('harvestlogs.edit', compact('harvest', 'failed'));
    }


    /**
     * Display the resource w/ built-in form (manager/admin) for editting the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

     /**
      * Update the specified resource in storage.
      *
      * @param  \Illuminate\Http\Request $request
      * @param  int  $id
      * @return \Illuminate\Http\Response
      */
    public function update(Request $request, $id)
    {
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        $harvest = HarvestLog::findOrFail($id);
        $this->validate($request, ['status' => 'required']);

        // A harvest being updated to ReQueued means setting attempts to zero
        if ($request->input('status') == 'ReQueued' && $harvest->status != "ReQueued") {
            $harvest->attempts = 0;
        }
        $harvest->status = $request->input('status');
        $harvest->save();
        $harvest->load(
            'report:id,name',
            'sushiSetting',
            'sushiSetting.institution:id,name',
            'sushiSetting.provider:id,name'
        );

        return response()->json(['result' => true, 'harvest' => $harvest]);
    }

    /**
     * Download raw data for a harvest
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function downloadRaw($id)
    {
        $harvest = HarvestLog::findOrFail($id);
        if (!auth()->user()->hasRole(['Admin'])) {
            if (!auth()->user()->hasRole(['Manager']) || $harvest->sushiSetting->inst_id != auth()->user()->inst_id) {
                return response()->json(['result' => false, 'msg' => 'Error - Not authorized']);
            }
        }
        if (!is_null(config('ccplus.reports_path'))) {
            // Set the path and filename based on config and harvest sushsettings
            $filename  = config('ccplus.reports_path') . session('ccp_con_key') . '/';
            $filename .= $harvest->sushiSetting->institution->name . '/';
            $filename .= $harvest->sushiSetting->provider->name . '/';
            $filename .= $harvest->rawfile;

            // Confirm the file exists and is readable before trying to bzd and return it
            if (!is_readable($filename)) {
                $msg = 'Raw datafile is not accessible.';
            }

            return response()->streamDownload(function () use ($filename) {
                echo bzdecompress(Crypt::decrypt(File::get($filename), false));
            }, $harvest->rawfile);
        } else {
            $msg = 'System not configured to save raw data, check config value of CCP_REPORTS.';
        }
        return response()->json(['result' => false, 'msg' => $msg]);
    }

   /**
    * Remove the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function destroy($id)
    {
        $record = HarvestLog::findOrFail($id);
        abort_unless($record->canManage(), 403);
        if (!$record->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Not authorized!']);
        }
        $record->delete();
        return response()->json(['result' => true, 'msg' => 'Log record deleted successfully']);
    }

    // Turn a fromYM/toYM range into an array of yearmon strings
    private function createYMarray($from, $to)
    {
        $range = array();
        $start = strtotime($from);
        $end = strtotime($to);
        if ($start > $end) {
            return $range;
        }
        while ($start <= $end) {
            $range[] = date('Y-m', $start);
            $start = strtotime("+1 month", $start);
        }
        return $range;
    }
}
