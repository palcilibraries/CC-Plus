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
use App\InstitutionGroup;
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
        $thisUser = auth()->user();
        $json = ($request->input('json')) ? true : false;
        $conso_db = config('database.connections.consodb.database');

        // Assign optional inputs to $filters array
        $filters = array('inst' => [], 'prov' => [], 'rept' => [], 'stat' => [], 'fromYM' => null, 'toYM' => null);
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
                    if ($key == 'fromYM' || $key == 'toYM') {
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

        // Make sure dates are sensible
        if (!is_null($filters['fromYM']) || !is_null($filters['toYM'])) {
            if (is_null($filters['fromYM'])) {
                $filters['fromYM'] = $filters['toYM'];
            }
            if (is_null($filters['toYM'])) {
                $filters['toYM'] = $filters['fromYM'];
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

            // Build an array of $providers
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

        // Skip querying for records unless we're returning json
        // The vue-component will run a request for JSON data once it is mounted
        if ($json) {

            // Get the harvest rows based on sushisettings
            $settings = SushiSetting::when(sizeof($filters['inst']) > 0, function ($qry) use ($filters) {
                                          return $qry->whereIn('inst_id', $filters['inst']);
                                      })
                                      ->when(sizeof($filters['prov']) > 0, function ($qry) use ($filters) {
                                          return $qry->whereIn('prov_id', $filters['prov']);
                                      })
                                      ->pluck('id')->toArray();
            $harvest_data = HarvestLog::
                with('report:id,name','sushiSetting','sushiSetting.institution:id,name','sushiSetting.provider:id,name')
                ->whereIn('sushisettings_id', $settings)
                ->orderBy('updated_at', 'DESC')
                ->when(sizeof($filters['rept']) > 0, function ($qry) use ($filters) {
                    return $qry->whereIn('report_id', $filters['rept']);
                })
                ->when(sizeof($filters['stat']) > 0, function ($qry) use ($filters) {
                    return $qry->whereIn('status', $filters['stat']);
                })
                ->when($filters['fromYM'], function ($qry) use ($filters) {
                    return $qry->where('yearmon', '>=', $filters['fromYM']);
                })
                ->when($filters['toYM'], function ($qry) use ($filters) {
                    return $qry->where('yearmon', '<=', $filters['toYM']);
                })
                ->get();
            $harvests = $harvest_data->map(function ($harvest) {
                $rec = array('id' => $harvest->id, 'yearmon' => $harvest->yearmon, 'attempts' => $harvest->attempts,
                             'status' => $harvest->status);
                $rec['updated_at'] = substr($harvest->updated_at,0,10);
                $rec['inst_name'] = $harvest->sushiSetting->institution->name;
                $rec['prov_name'] = $harvest->sushiSetting->provider->name;
                $rec['report_name'] = $harvest->report->name;
                return $rec;
            });

            return response()->json(['harvests' => $harvests], 200);

        // Not returning JSON, the index/vue-component still needs these to setup the page
        } else {
            $harvests = array();
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
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        if ($thisUser->hasRole('Admin')) {
            $is_admin = true;
        } else {
            $user_inst =$thisUser->inst_id;
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
            $inst_groups = InstitutionGroup::with('institutions')->orderBy('name', 'ASC')->get(['id','name']);
            $provider_data = Provider::with('reports')
                                 ->whereIn('id', $possible_providers)->where('is_active', true)
                                 ->orderBy('name', 'ASC')->get(['id','name']);

            // Copy available_providers into the groups (to simplify the vue component)
            foreach ($inst_groups as $group) {
                $insts = $group->institutions->pluck('id')->toArray();
                $available_providers = SushiSetting::whereIn('inst_id',$insts)->pluck('prov_id')->toArray();
                $group->providers = $provider_data->whereIn('id',$available_providers)->toArray();
            }
            $providers = $provider_data->toArray();
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
            $inst_groups = array();
        }
        array_unshift($providers, ['id' => 0, 'name' => 'All Providers']);

        // Get reports for all that exist in the relationship table
        $table = config('database.connections.consodb.database') . '.' . 'provider_report';
        $report_ids = DB::table($table)->distinct('report_id')->pluck('report_id')->toArray();
        $all_reports = Report::whereIn('id', $report_ids)->orderBy('id', 'asc')->get()->toArray();

        return view('harvestlogs.create', compact('institutions','inst_groups','providers','all_reports','presets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        $this->validate(
            $request,
            ['prov' => 'required', 'reports' => 'required', 'fromYM' => 'required', 'toYM' => 'required',
             'when' => 'required']
        );
        $input = $request->all();
        if (!isset($input["inst"]) || !isset($input["inst_group_id"])) {
            return response()->json(['result' => false, 'msg' => 'Error: Missing input arguments!']);
        }
        if (sizeof($input["inst"]) == 0 && $input["inst_group_id"] <= 0) {
            return response()->json(['result' => false, 'msg' => 'Error: Institution/Group invalid in request']);
        }
        $user_inst =$thisUser->inst_id;
        $is_admin =$thisUser->hasRole('Admin');

        // Admins can harvest multiple insts or a group
        $inst_ids = array();
        if ($is_admin) {
            // Set inst_ids (force to user's inst if not an admin)
            if ($input["inst_group_id"] > 0) {
                $group = InstitutionGroup::with('institutions')->findOrFail($input["inst_group_id"]);
                $inst_ids = $group->institutions->pluck('id')->toArray();
            } else {
                // A value of 0 in inst_ids means we're doing entire consortium
                if (in_array(0,$input["inst"])) {
                    $inst_ids = Institution::where('is_active',true)->where('id','<>',1)->pluck('id')->toArray();
                } else {
                    $inst_ids = $input["inst"];
                }
            }
        // Managers are confined to only their inst
        } else {
            $inst_ids = array($user_inst);
        }
        if (sizeof($inst_ids) == 0) {
            return response()->json(['result' => false, 'msg' => 'Error: no matching institutions to harvest']);
        }
        // Get provider info
        $prov_ids = $input["prov"];
        if (sizeof($prov_ids) > 0) {
            $providers = Provider::with('sushiSettings', 'sushiSettings.institution:id,is_active', 'reports')
                                 ->whereIn('id', $prov_ids)->get();

            // Non-admin disallowed from harvesting non-consortium providers owned by other institutions
            if (!$is_admin && $providers[0]->inst_id != $user_inst && $providers[0]->inst_id != 1) {
                return response()->json(['result' => false, 'msg' => 'Requested provider is not authorized.']);
            }
        // if providers array is empty, get all active
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
                   // If institution is inactive or this inst_id is not in the $inst_ids array, skip it
                    if ((!$setting->institution->is_active) || (!in_array($setting->inst_id,$inst_ids))) {
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
     * Return available providers for an array of inst_ids or an inst_group
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function availableProviders(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
        $group_id = json_decode($request->group_id, true);
        $insts = json_decode($request->inst_ids, true);

        // Setup an array of inst_ids for querying against the sushisettings
        if ($group_id > 0) {
            $group = InstitutionGroup::with('institutions')->findOrFail($group_id);
            $inst_ids = $group->institutions->pluck('id')->toArray();
        } else if (sizeof($insts) > 0) {
            $inst_ids = $insts;
        } else {
            return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
        }

        // Query the sushisettings for providers connected to the requested inst IDs
        if (in_array(0,$inst_ids)) {
            // If inst_ids array includes a value=0, it means user chose "Entire Consortium"
            $availables = SushiSetting::pluck('prov_id')->toArray();
        } else {
            $availables = SushiSetting::whereIn('inst_id',$inst_ids)->pluck('prov_id')->toArray();
        }

        // Use availables (IDs) to get the provider data and return it via JSON
        $providers = Provider::with('reports')->where('is_active', true)
                             ->whereIn('id',$availables)
                             ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        if (sizeof($providers) == 0) {
            return response()->json(['result' => false, 'msg' => 'No matching, active providers found']);
        } else {
            return response()->json(['providers' => $providers], 200);
        }
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
        //   Queued: resets attempts to zero and requeues the harvest for immediate retrying
        //   Stopped: Sets harvest to "Stopped", regardless of what it was before.
        $new_status_allowed = array('Queued', 'Stopped');
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
        if ($input['status'] == 'Stopped') {
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
        $harvest['updated'] = substr($harvest->updated_at,0,10);

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
        $harvest = HarvestLog::with(
            'report:id,name',
            'sushiSetting',
            'sushiSetting.institution:id,name',
            'sushiSetting.provider:id,name'
        )->findOrFail($id);

        // Get any failed attempts, pass as an array
        $data = FailedHarvest::with('ccplusError', 'ccplusError.severity')->where('harvest_id', '=', $id)
                             ->orderBy('created_at','DESC')->get();
        $attempts = $data->map(function ($rec) {
            $rec->severity = $rec->ccplusError->severity->name;
            $rec->message = $rec->ccplusError->message;
            $rec->attempted = date("Y-m-d H:i:s", strtotime($rec->created_at));
            return $rec;
        })->toArray();

        // If harvest successful, pass it as an array
        if ($harvest->status == 'Success') {
            $rec = array('process_step' => 'SUCCESS', 'error_id' => '', 'severity' => '', 'detail' => '');
            $rec['message'] = "Harvest successfully completed";
            $rec['attempted'] = date("Y-m-d H:i:s", strtotime($harvest->created_at));
            array_unshift($attempts,$rec);
        } else {
            // Harvests could have prior failures, but attampes has been reset to zero to requeue ot
            if (sizeof($attempts) == 0) {
                if ($harvest->attempts == 0) {
                    $attempts[] = array('severity' => "Unknown", 'message' => "Harvest has not yet been attempted",
                                        'attempted' => "Unknown");
                } else {
                    $attempts[] = array('severity' => "Unknown", 'message' => "Failure records are missing!",
                                        'attempted' => "Unknown");
                }
            }
        }

        return view('harvestlogs.edit', compact('harvest', 'attempts'));
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
        $thisUser = auth()->user();
        $harvest = HarvestLog::findOrFail($id);
        if (!$thisUser->hasRole(['Admin'])) {
            if (!$thisUser->hasRole(['Manager']) || $harvest->sushiSetting->inst_id != $thisUser->inst_id) {
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

        // Delete any related jobs from the global queue
        $jobs = SushiQueueJob::where('harvest_id', $id)->get();
        foreach ($jobs as $job) {
            $job->delete();
        }

        // Delete the harvestlog record itself
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
