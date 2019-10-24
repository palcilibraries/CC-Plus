<?php

namespace App\Http\Controllers;

use App\FailedIngest;
use Illuminate\Http\Request;

class FailedIngestController extends Controller
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
        $data = FailedIngest::orderBy('id', 'DESC')->paginate(10);
        return view('failedingests.index', compact('data'))
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
        $record = FailedIngest::findOrFail($id);
        return view('failedingests.show', compact('record'));
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
        $record = FailedIngest::findOrFail($id);
        $record->delete();

        return redirect()->route('failedingests.index')
                      ->with('success', 'Failed ingest record deleted successfully');
    }
}
