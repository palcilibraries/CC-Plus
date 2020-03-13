<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Report;
use App\ReportField;
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

    /**
     * Update usage report filter options
     *
     * @return \Illuminate\Http\Response
     */
    public function updateFilters(Request $request)
    {
        // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input']);
        }
        if (!isset($input['filters']) || !isset($input['master_report'])) {
            return response()->json(['result' => false, 'msg' => 'One or more inputs are missing!']);
        }
//
// may want a column in the Report model that hold the database table name for each report...
//
        $report = Report::where('name',$input['master_report'])->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Master Report is undefined!']);
        }
// --- This kind of error testing should probably happen in the UI before we get here ---
// if ($filters['to_yearmon']=='' < $filters['from_yearmon']=='') { Error! }
// if ($filters['to_yearmon']=='' || $filters['from_yearmon']=='') {
//     if ($filters['to_yearmon']=='') {
//         $filters['to_yearmon'] = $filters['from_yearmon'];
//     } else {
//         $filters['from_yearmon'] = $filters['to_yearmon'];
//     }
// }

        // Use filters to construct where clause
        $conditions = array();
        $filters = $input['filters'];

        // Setup the non-date filters
        if (auth()->user()->hasAnyRole(['Admin','Viewer'])) {
//-->> InstitutionGroup filter probably gets applied here ... but...
//-->> Should inst and inst_group be a SINGLE filtering option?
//-->> (let interface handles it? e.g. if one is set, disable the other)?
//-->> (The rebuild query below will to one inst, or a list, regardless)
            if ($filters['inst_id'] != 0) {
                $conditions[] = array('inst_id',$filters['inst_id']);
            }
        }

        // Pull report fields with a filter_by value for this report
        $filter_fields = ReportField::where('report_id',$report->id)->whereNotNull('filter_by')->get();
        foreach ($filter_fields as $field) {
            if (!isset($filters[$field->filter_by])) {
                continue;
            }
            if ($filters[$field->filter_by] != 0) {
                $conditions[] = array($field->filter_by,$filters[$field->filter_by]);
            }
        }

        // Need to confirm that works as-desired re: the yearmon strings...
        if ($filters['from_yearmon'] != '') {
            $query->whereBetween('yearmon', [$filters['from_yearmon'], $filters['to_yearmon']])
                  ->where($conditions)
                  ->get();  // this needs to GET sets of distinct values for ALL filter-arrays
        } else {

        }

    }

}
