<?php

namespace App\Http\Controllers;

use App\FailedHarvest;
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
        $data = FailedHarvest::orderBy('id', 'DESC')->paginate(10);
        return view('failedharvests.index', compact('data'))
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
