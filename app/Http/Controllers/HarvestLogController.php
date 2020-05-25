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
    * @return \Illuminate\Http\Response
    */
    public function index(Request $request)
    {
        // Handle some optional inputs
        $inst = ($request->input('inst')) ? $request->input('inst') : null;
        $prov = ($request->input('prov')) ? $request->input('prov') : null;
        $yrmo = ($request->input('yrmo')) ? $request->input('yrmo') : null;
        $rept = ($request->input('rept')) ? $request->input('rept') : null;
        $json = ($request->input('json')) ? true : false;

        // managers and users only see their own insts
        if (!auth()->user()->hasAnyRole(["Admin","Viewer"])) {
            $inst = auth()->user()->inst_id;
        }

        // Build header text if we're not returning JSON
        $details = "";
        if (!$json) {
            if (!is_null($inst)) {
                $inst_name = Institution::where('id','=',$inst)->value('name');
                $details .= ($inst_name != "") ? $inst_name : "";
            }
            if (!is_null($prov)) {
                $prov_name = Provider::where('id','=',$prov)->value('name');
                if ($prov_name != "") {
                    $details .= ($details=="") ? $prov_name : ", " . $prov_name;
                }
            }
            if (!is_null($yrmo)) {
                $details .= ($details=="") ? $yrmo : ", " . $yrmo;
            }
            if (!is_null($rept)) {
                $_name = Report::where('id','=',$rept)->value('name');
                $details .= " : " . $_name . " report(s)";
            }
        }
        $header = ($details == "") ? "Harvest Log" : "Harvests : " . $details;

        // Get the rows
        $settings = SushiSetting::when($inst, function ($qry, $inst) {
                                           return $qry->where('inst_id', $inst);
                                   })
                                 ->when($prov, function ($qry, $prov) {
                                       return $qry->where('prov_id', $prov);
                                   })
                                 ->pluck('id')->toArray();
        $data = HarvestLog::with('report:id,name','sushiSetting',
                                 'sushiSetting.institution:id,name','sushiSetting.provider:id,name')
                          ->whereIn('sushisettings_id', $settings)
                          ->orderBy('updated_at', 'DESC')
                          ->when($rept, function ($qry, $rept) {
                              return $qry->where('report_id', $rept);
                          })
                          ->when($yrmo, function ($qry, $yrmo) {
                              return $qry->where('yearmon', '=', $yrmo);
                          })
                          ->get();

        // Return results
        if ($json) {
            return response()->json(['data' => $data], 200);
        } else {
            return view('harvestlogs.index', compact('data', 'header'));
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

        // Get IDs of all possible prov_ids from the sushisettings table
        $possible_providers = SushiSetting::distinct('prov_id')->pluck('prov_id')->toArray();
        if (auth()->user()->hasRole("Admin")) {     // Admin view
            // $inst_data = Institution::with('sushiSettings:prov_id')->orderBy('name', 'ASC')
            $institutions = Institution::with('sushiSettings:id,inst_id,prov_id')->orderBy('name', 'ASC')
                                       ->where('id', '<>', 1)->get(['id','name'])->toArray();
            array_unshift($institutions, ['id' => 0, 'name' => 'Entire Consortium']);
            $providers = Provider::with('reports')->whereIn('id',$possible_providers)
                                 ->orderBy('name', 'ASC')->get(['id','name'])->toArray();

        } else {    // manager view
            $user_inst = auth()->user()->inst_id;
            // $inst_data = Institution::with('sushiSettings:prov_id')
            $institutions = Institution::with('sushiSettings:id,inst_id,prov_id')
                                       ->where('id', '=', $user_inst)->get(['id','name'])->toArray();
            $providers = Provider::with('reports')->whereIn('id',$possible_providers)
                                 ->where(function ($query) use ($user_inst) {
                                     $query->where('inst_id', 1)->orWhere('inst_id', $user_inst);
                                 })
                                 ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        }
        array_unshift($providers, ['id' => 0, 'name' => 'All Providers']);

        // Get reports for all that exist in the relationship table
        $table = config('database.connections.consodb.database') . '.' . 'provider_report';
        $report_ids = DB::table($table)->distinct('report_id')->pluck('report_id')->toArray();
        $all_reports = Report::whereIn('id',$report_ids)->orderBy('id', 'asc')->get()->toArray();

        return view('harvestlogs.create',compact('institutions', 'providers', 'all_reports'));
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
        $this->validate($request,
            ['inst_id' => 'required', 'prov_id' => 'required', 'reports' => 'required',
             'fromYM' => 'required', 'toYM' => 'required', 'when' => 'required']);
        $input = $request->all();

        // Set inst_id (force to user's inst if not an admin)
        $inst_id = ($is_admin) ? $input["inst_id"] : $user_inst;

        // Get provider info
        $prov_id = $input["prov_id"];
        if ($prov_id!=0) {
            $providers = Provider::with('sushiSettings', 'sushiSettings.institution:id,is_active','reports')
                                 ->where('id', $prov_id)->get();

            // Non-admin disallowed from harvesting non-consortium providers owned by other institutions
            if (!$is_admin && $providers[0]->inst_id!=$user_inst && $providers[0]->inst_id!= 1) {
                return response()->json(['result' => false, 'msg' => 'Requested provider is not authorized.']);
            }
        } else {
            $providers = Provider::with('sushiSettings', 'sushiSettings.institution:id,is_active','reports')
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
                    if ((!$setting->institution->is_active) || ($inst_id!=0 && $setting->inst_id!=$inst_id)) {
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
                                    $msg = 'Failure adding Harvest ID: ' . $harvest->id .' to Queue! Error ' . $code;
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
    * Display the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function show($id)
    {
        $record = HarvestLog::with('failedHarvests')->findOrFail($id);
        return view('harvestlogs.show', compact('record'));
    }

    /**
     * Doenload raw data for a harvest
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
     public function downloadRaw($id)
     {
         $harvest = HarvestLog::findOrFail($id);
         if (!auth()->user()->hasRole(['Admin'])) {
             if (!auth()->user()->hasRole(['Manager']) || $harvest->sushiSetting->inst_id!=auth()->user()->inst_id) {
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

             return response()->streamDownload(function() use ($filename) {
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
        $this->middleware(['role:Admin']);
        $record = HarvestLog::findOrFail($id);
        $record->delete();

        return redirect()->route('harvestlogs.index')
                      ->with('success', 'Log record deleted successfully');
    }

    // Turn a fromYM/toYM range into an array of yearmon strings
    private function createYMarray($from, $to) {
        $range = array();
        $start = strtotime($from);
        $end = strtotime($to);
        if ($start > $end) {
            return $range;
        }
        while($start <= $end) {
          $range[] = date('Y-m', $start);
          $start = strtotime("+1 month", $start);
        }
        return $range;
    }

}
