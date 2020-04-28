<?php

namespace App\Http\Controllers;

use App\SavedReport;
use App\Report;
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
        $report = SavedReport::findOrFail($id);
        abort_unless($report->canManage(), 403);

        return view('savedreports.edit', compact('report'));
    }

    /**
     * Save a report configuration (New or Update)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function saveReportConfig(Request $request)
    {
        $this->validate($request, ['title' => 'required', 'save_id' => 'required', 'report_id' => 'required',
                                   'months' => 'required', 'fields' => 'required']);
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
            $saved_report = SavedReport::where('user_id',auth()->id())->where('id',$save_id)->first();
            if (!$saved_report) {
                return response()->json(['result' => false, 'msg' => 'Cannot access saved report data']);
            }

       // -or- create a new config
        } else {
            $saved_report = new SavedReport();
            $saved_report->user_id = auth()->id();
            $saved_report->master_id = $master_id;
        }

       // Build inherited fields string based on active columns and filters
        $inherited_fields = '';
        foreach ($all_fields as $field) {
            if ($input_fields[$field->qry_as]) {
                if ($input_fields[$field->qry_as]['active']) {
                    $inherited_fields .= ($inherited_fields == '') ? '' : ',';
                    $inherited_fields .= $field->id;
                    if ($input_fields[$field->qry_as]['limit']>0) {
                        $inherited_fields .= ":" . $input_fields[$field->qry_as]['limit'];
                    }
                }
            }
        }

       // Save record with inherited fields and dates
        $saved_report->title = $title;
        $saved_report->inherited_fields = $inherited_fields;
        $saved_report->months = $request->months;
        $saved_report->save();
        return response()->json(['result' => true, 'msg' => 'Configuration saved successfully']);
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
