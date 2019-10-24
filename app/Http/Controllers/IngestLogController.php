<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\IngestLog;
use App\FailedIngest;

class IngestLogController extends Controller
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
        $data = IngestLog::orderBy('id', 'DESC')->paginate(10);
        return view('ingestlogs.index', compact('data'))
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
        $record = IngestLog::findOrFail($id);
        $_limiters = ['sushisettings_id' => $record->sushisettings_id,
                    'yearmon' => $record->yearmon,
                    'report_id' => $record->report_id];
        $failed = FailedIngest::where($_limiters)->first();
        return view('ingestlogs.show', compact('record', 'failed'));
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
        $record = IngestLog::findOrFail($id);
        $record->delete();

        return redirect()->route('ingestlogs.index')
                      ->with('success', 'Log record deleted successfully');
    }
}
