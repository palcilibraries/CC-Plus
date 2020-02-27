<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Report;
use App\SavedReport;
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
     * Setup dashboard for generating usage report summaries
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $master_reports = Report::orderBy('name', 'asc')->where('parent_id', '=', 0)->get();
        return view('reports.usage', compact('master_reports'));
    }

    /**
     * View defined reports
     *
     * @return \Illuminate\Http\Response
     */
    public function view(Request $request)
    {
        $master_reports = Report::orderBy('name', 'asc')->where('parent_id', '=', 0)->get();
        $user_reports = SavedReport::orderBy('title', 'asc')->where('user_id', '=', auth()->id())->get();

        return view('reports.view', compact('master_reports', 'user_reports'));
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
        $fields = $report->reportFields();
        return view('reports.show', compact('report', 'fields'));
    }
}
