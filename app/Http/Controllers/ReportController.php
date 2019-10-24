<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Report;
//Enables us to output flash messaging
use Session;

class ReportController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth','role:Admin']);
    }

    /**
     * List the defined reports
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // $_report = Report::orderBy('name', 'asc')->first();
        // dd($_report->reportfields);

        $master_reports = Report::orderBy('name', 'asc')->where('parent_id', '=', 0)->get();

        return view('reports.index', compact('master_reports'));
    }

    /**
     * Display a specific report
     *
     * @param  \App\Report  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $report = Report::findOrFail($id);
        $fields = $report->reportfields();
        return view('reports.show', compact('report', 'fields'));
    }
}
