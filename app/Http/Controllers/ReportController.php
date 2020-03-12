<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Report;
use App\SavedReport;
use App\Institution;
use App\InstitutionGroup;
use App\Provider;
use App\Platform;
use App\Publisher;
use App\DataType;
use App\SectionType;
use App\AccessType;
use App\AccessMethod;

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
        // $this->middleware(['auth','role:Admin']);
    }

    /**
     * Setup dashboard for generating usage report summaries
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $platforms = Platform::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $publishers = Publisher::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $accesstypes = AccessType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $accessmethods = AccessMethod::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $datatypes = DataType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $sectiontypes = SectionType::orderBy('name', 'asc')->get(['name','id'])->toArray();
        $master_reports = Report::orderBy('name', 'asc')->where('parent_id', '=', 0)->get(['name','id'])->toArray();
        if (auth()->user()->hasRole("Admin") || auth()->user()->hasRole("Viewer")) {
            $inst_groups = InstitutionGroup::get(['name', 'id'])->toArray();
            $institutions = Institution::where('is_active',true)->orderBy('name', 'ASC')->get();
            $providers = Provider::where('is_active',true)->orderBy('name', 'ASC')->get();
        } else {
            $inst_groups = array();
            $institutions = Institution::where('id', auth()->user()->inst_id)->get();
            $providers = Provider::where('is_active',true)
                                 ->where(function($qry) {
                                     $qry->where('inst_id', 1)
                                         ->orWhere('inst_id', auth()->user()->inst_id);
                                 })
                                 ->orderBy('name', 'ASC')->get();
        }
        return view('reports.usage', compact('platforms','publishers','accesstypes','accessmethods',
                                             'datatypes', 'sectiontypes','master_reports','inst_groups',
                                             'institutions', 'providers'));
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
