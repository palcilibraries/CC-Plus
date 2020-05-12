<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\HarvestLog;
use App\FailedHarvest;
use App\Report;
use App\Provider;
use App\Institution;
use App\SushiSetting;

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

        // Build a lower header if we're not returning JSON and filtering
        $lower_head = "";
        if (!$json) {
            if (!is_null($inst)) {
                $inst_name = Institution::where('id','=',$inst)->value('name');
                $lower_head .= ($inst_name != "") ? "Institution: " . $inst_name : "";
            } else {
                $lower_head .= (!is_null($prov) || !is_null($yrmo) || !is_null($rept)) ? "All institutions" : "";
            }
            if (!is_null($prov)) {
                $prov_name = Provider::where('id','=',$prov)->value('name');
                if ($prov_name != "") {
                    $lower_head .= ($lower_head=="") ? "Provider: " . $prov_name : " and provider: " . $prov_name;
                }
            } else {
                if (!is_null($inst) || !is_null($yrmo) || !is_null($rept)) {
                    $lower_head .= ($lower_head=="") ? "All providers" : " and all providers";
                }
            }
            if (!is_null($yrmo)) {
                $lower_head .= ($lower_head=="") ? "Report month: " . $yrmo : " : " . $yrmo;
            }
            if (!is_null($rept)) {
                $_name = Report::where('id','=',$rept)->value('name');
                $lower_head .= " : " . $_name . " report(s)";
            } else {
                $lower_head .= ($lower_head=="") ? "" : " : all reports";
            }
        }

        // Get the rows
        $data = HarvestLog::join('sushisettings', 'harvestlogs.sushisettings_id', '=', 'sushisettings.id')
                          ->orderBy('yearmon', 'DESC')
                          ->when($inst, function ($qry, $inst) {
                              return $qry->where('sushisettings.inst_id', $inst);
                          })
                          ->when($prov, function ($qry, $prov) {
                              return $qry->where('sushisettings.prov_id', $prov);
                          })
                          ->when($rept, function ($qry, $rept) {
                              return $qry->where('report_id', $rept);
                          })
                          ->when($yrmo, function ($qry, $yrmo) {
                              return $qry->where('yearmon', '=', $yrmo);
                          })
                          ->when($json, function ($query) {
                              return $query->get();
                          }, function ($query) {
                              return $query->paginate(20);
                              // return $query->get()->paginate(20);
                          });

        // Return results
        if ($json) {
            return response()->json(['data' => $data], 200);
        } else {
            return view('harvestlogs.index', compact('data', 'lower_head'))
                 ->with('i', ($request->input('page', 1) - 1) * 10);
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

        $input = $request->all();

        // Get args from the $input

        // Check From/To - chop off at current month if in future

        // Pull all sushi settings, but inst and prov

        // Loop over sushi settings, add the harvests for-each report in $input->reports
        //   --> if the already exist, reset the attempt counter to zero and
        //       set the overwrite flag to trash existing date

// --database-structure
//
    $table->Increments('id');
    // Status should be: 'Success', 'Fail', 'New', 'Queued', 'Active', 'Pending', 'Stopped', or 'Retrying'
    $table->string('status', 8);
    $table->unsignedInteger('sushisettings_id');
    $table->unsignedInteger('report_id');
    $table->string('yearmon', 7);
    $table->unsignedInteger('attempts')->default(0);

//--code from QLoader...
//
    // Insert new HarvestLog record; catch and prevent duplicates
     try {
         HarvestLog::insert(['status' => 'New', 'sushisettings_id' => $setting->id,
                            'report_id' => $report->id, 'yearmon' => $yearmon,
                            'attempts' => 0, 'created_at' => $ts]);
     } catch (QueryException $e) {
         $errorCode = $e->errorInfo[1];
         if ($errorCode == '1062') {
             $harvest = HarvestLog::where([['sushisettings_id', '=', $setting->id],
                                         ['report_id', '=', $report->id],
                                         ['yearmon', '=', $yearmon]
                                        ])->first();
             $this->line('Harvest ' . '(ID:' . $harvest->id . ') already defined. Updating to retry (' .
                         'setting: ' . $setting->id . ', ' . $report->name . ':' . $yearmon . ').');
             $harvest->status = 'Retrying';
             $harvest->save();
         } else {
             $this->line('Failed adding to HarvestLog! Error code:' . $errorCode);
             exit;
         }
     }

// may want the confirmation message to show how many new and how many re-queued...
        return response()->json(['result' => true, 'msg' => 'Successfully queued' . $count . ' harvests.']);
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
}
