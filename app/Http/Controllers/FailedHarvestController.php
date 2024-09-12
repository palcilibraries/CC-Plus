<?php

namespace App\Http\Controllers;

use App\FailedHarvest;
use App\Institution;
use App\Provider;
use App\Report;
use App\SushiSetting;
use App\HarvestLog;
use Illuminate\Http\Request;

class FailedHarvestController extends Controller
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
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

        // Handle some optional inputs
        $inst = ($request->input('inst')) ? $request->input('inst') : null;
        $prov = ($request->input('prov')) ? $request->input('prov') : null;
        $rept = ($request->input('rept')) ? $request->input('rept') : null;
        $ymfr = ($request->input('ymfr')) ? $request->input('ymfr') : null;
        $ymto = ($request->input('ymto')) ? $request->input('ymto') : null;
        $json = ($request->input('json')) ? true : false;

        // managers and users only see their own insts
        if (!auth()->user()->hasAnyRole(["Admin"])) {
            $inst = auth()->user()->inst_id;
        }

        // Build header text if we're not returning JSON
        $details = "";
        if (!$json) {
            if (!is_null($inst)) {
                $inst_name = Institution::where('id', '=', $inst)->value('name');
                $details .= ($inst_name != "") ? $inst_name : "";
                $institutions = Institution::where('id', '=', $inst)->get(['id', 'name'])->toArray();
            } else {
                $institutions = Institution::where('id', '<>', 1)->get(['id', 'name'])->toArray();
                array_unshift($institutions, ['id' => 0, 'name' => 'All Institutions']);
            }
            if (!is_null($prov)) {
                $prov_name = Provider::where('id', '=', $prov)->value('name');
                if ($prov_name != "") {
                    $details .= ($details == "") ? $prov_name : ", " . $prov_name;
                }
            }
            $providers = Provider::get(['id', 'name'])->toArray();
            array_unshift($providers, ['id' => 0, 'name' => 'All Providers']);

            if (!is_null($rept)) {
                $_name = Report::where('id', '=', $rept)->value('name');
                $details .= " : " . $_name . " report(s)";
            }
            $reports = Report::where('parent_id',0)->orderBy('dorder', 'ASC')->get(['id', 'name'])->toArray();
            if (!is_null($ymfr) || !is_null($ymto)) {
                if (is_null($ymfr)) {
                    $ymfr = $ymto;
                }
                if (is_null($ymto)) {
                    $ymto = $ymfr;
                }
                if ($ymfr == $ymto) {
                    $details .= ($details == "") ? $ymfr : ", " . $ymfr;
                } else {
                    $range = $ymfr . " to " . $ymto;
                    $details .= ($details == "") ? $range : ", " . $range;
                }
            }

            // Query for min and max yearmon values
            $bounds = array();
            $raw_query = "min(yearmon) as YM_min, max(yearmon) as YM_max";
            $result = HarvestLog::selectRaw($raw_query)->get()->toArray();
            $bounds[0] = $result[0];
            foreach ($reports as $report) {
                $result = HarvestLog::where('report_id', $report['id'])
                                    ->selectRaw($raw_query)
                                    ->get()
                                    ->toArray();
                $bounds[$report['id']] = $result[0];
            }
            array_unshift($reports, ['id' => 0, 'name' => 'All Reports']);
        }
        $header  = "Failed Harvests";
        $header .= ($details == "") ? "" : " : " . $details;

        // Get the sushisettings implicated by inst and prov
        $setting_ids = SushiSetting::when($inst, function ($qry, $inst) {
                                      return $qry->where('inst_id', $inst);
        })
                                ->when($prov, function ($qry, $prov) {
                                      return $qry->where('prov_id', $prov);
                                })
                                ->pluck('id')->toArray();

        // Get the harvestlogs connected to the sushisettings and the other filters
        $harvest_ids = HarvestLog::whereIn('sushisettings_id', $setting_ids)
                                 ->when($rept, function ($qry, $rept) {
                                     return $qry->where('report_id', $rept);
                                 })
                                 ->when($ymfr, function ($qry, $ymfr) {
                                     return $qry->where('yearmon', '>=', $ymfr);
                                 })
                                 ->when($ymto, function ($qry, $ymto) {
                                     return $qry->where('yearmon', '<=', $ymto);
                                 })
                                 ->pluck('id')->toArray();

        // Get the failedharvest records
        $data = FailedHarvest::with(
            'harvest',
            'harvest.sushiSetting',
            'harvest.sushiSetting.institution:id,name',
            'harvest.sushiSetting.provider:id,name',
            'harvest.report:id,name',
            'ccplusError',
            'ccplusError.severity'
        )->whereIn('harvest_id', $harvest_ids)->orderBy('created_at', 'DESC')->get();
        $failed = $data->map(function ($rec) {
            $rec->attempted = date("Y-m-d H:i:s", strtotime($rec->created_at));
            return $rec;
        });

        // Return results
        if ($json) {
            return response()->json(['failed' => $failed], 200);
        } else {
            return view('failedharvests.index', compact(
                'failed',
                'institutions',
                'providers',
                'reports',
                'bounds',
                'header'
            ));
        }
    }

   /**
    * Display the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function show($id)
    {
        $record = FailedHarvest::findOrFail($id);
        return view('failedharvests.show', compact('record'));
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
        $record = FailedHarvest::findOrFail($id);
        $record->delete();

        return redirect()->route('failedharvests.index')
                      ->with('success', 'Failed harvest record deleted successfully');
    }
}
