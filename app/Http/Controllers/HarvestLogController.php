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
        $data = HarvestLog::orderBy('id', 'DESC')->paginate(10);
        return view('harvestlogs.index', compact('data'))
             ->with('i', ($request->input('page', 1) - 1) * 10);
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
