<?php

namespace App\Http\Controllers;

use DB;
use App\SavedReport;
use App\Report;
use App\ReportFilter;
use App\InstitutionGroup;
use Illuminate\Http\Request;

class SavedReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Get and show the requested resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // User must be able to manage the settings
        $report = SavedReport::with('master', 'user')->findOrFail($id);
        abort_unless($report->canManage(), 403);

        // Get master fields for $report->inherited_fields and tack on filter relationship
        $fields = $report->master->reportFields->whereIn('id', preg_split('/,/', $report->inherited_fields));
        $fields->load('reportFilter');

        // Turn report->filterBy into key=>value arrays, named by the field column
        $filters = array();
        $filter_data = $report->filterBy();

        // If insitutiongroup is filtering, add it to the $filters array first (since it isn't a "field")
        if ($filter_data['institutiongroup_id'] > 0) {
            $filters['institutiongroup'] = array('legend' => 'Institution Group');
            $filters['institutiongroup']['name'] = InstitutionGroup::where('id', $filter_data['institutiongroup_id'])
                                                                   ->pluck('name')->first();
        }
        foreach ($fields as $field) {
            if ($field->reportFilter) {
                if ($field->qry_as == 'institution' && $filter_data['institutiongroup_id'] > 0) {
                    // If filtering by inst-group, institution is on/off - not all/selected
                    $data = array('legend' => $field->legend, 'name' => '');
                } else {
                    $data = array('legend' => $field->legend, 'name' => 'All');
                    if (isset($filter_data[$field->reportFilter->report_column])) {
                        $filter_id = $filter_data[$field->reportFilter->report_column];
                        if ($field->reportFilter->model) {
                            $data['name'] = $field->reportFilter->model::where('id', $filter_id)->value('name');
                        }
                    }
                }
                $filters[$field->qry_as] = $data;
            }
        }
        return view('savedreports.edit', compact('report', 'fields', 'filters'));
    }

    /**
     * Save a report configuration
     * --> IF $request includes a non-zero 'save_id', the request is treated as an update
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function store(Request $request)
    {
        $this->validate($request, ['title' => 'required', 'save_id' => 'required', 'months' => 'required',
                                   'report_id' => 'required', 'fields' => 'required']);
        $title = $request->title;
        $save_id = $request->save_id;
        $report_id = $request->report_id;
        $input_fields = json_decode($request->fields, true);

       // Pull the model for report_id (points to presets in global table), and get all fields for it
        $_report = Report::findorFail($report_id);
        if ($_report->parent_id == 0) {
            $master_id = $_report->id;
            $all_fields = $_report->reportFields;
        } else {
            $master_id = $_report->parent_id;
            $all_fields = $_report->parent->reportFields;
        }

       // Get the saved report config
        if ($save_id != 0) {
            $saved_report = SavedReport::where('user_id', auth()->id())->where('id', $save_id)->first();
            if (!$saved_report) {
                return response()->json(['result' => false, 'msg' => 'Cannot access saved report data']);
            }

       // -or- create a new config
        } else {
            $saved_report = new SavedReport();
            $saved_report->user_id = auth()->id();
            $saved_report->master_id = $master_id;
        }

       // Build inherited fields and filters strings based on active columns/filters
        $filters = '';
        $inherited_fields = '';
        foreach ($all_fields as $field) {
            if (isset($input_fields[$field->qry_as])) {
                if ($input_fields[$field->qry_as]['active']) {
                    $inherited_fields .= ($inherited_fields == '') ? '' : ',';
                    $inherited_fields .= $field->id;
                    if ($input_fields[$field->qry_as]['limit'] > 0 && $field->reportFilter) {
                        $filters .= ($filters == '') ? '' : ',';
                        $filters .= $field->reportFilter->id;
                        $filters .= ":" . $input_fields[$field->qry_as]['limit'];
                    }
                }
            }
        }

       // Tack on institution-group if it is in input_fields. It isn't a column, but is a filter-setting
        if (isset($input_fields['institutiongroup'])) {
            $filt = ReportFilter::where('report_column', '=', 'institutiongroup_id')->first();
            if ($input_fields['institutiongroup']['limit'] > 0 && $filt) {
                $filters .= "," . $filt->id . ":" . $input_fields['institutiongroup']['limit'];
            }
        }

       // Save record with inherited fields, filters and dates
        $saved_report->title = $title;
        $saved_report->inherited_fields = $inherited_fields;
        $saved_report->filters = $filters;
        $saved_report->months = $request->months;
        $saved_report->save();
        return response()->json(['result' => true, 'msg' => 'Configuration saved successfully']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\SavedReport $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $report = SavedReport::findOrFail($id);
        if (!$report->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

       // Validate form inputs
        $this->validate($request, ['title' => 'required', 'months' => 'required']);
        $input = $request->all();

       // Update the record and assign groups
        $report->update($input);

        return response()->json(['result' => true, 'msg' => 'Report settings successfully updated']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\SavedReport  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $report = SavedReport::findOrFail($id);
        if ($report->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $report->delete();
        return response()->json(['result' => true, 'msg' => 'Saved report successfully deleted']);
    }
}
