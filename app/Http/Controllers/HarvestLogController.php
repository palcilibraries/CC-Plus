<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\HarvestLog;
use App\FailedHarvest;

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
            return view('harvestlogs.index', compact('data'))
                 ->with('i', ($request->input('page', 1) - 1) * 10);
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
        $record = HarvestLog::findOrFail($id);
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
