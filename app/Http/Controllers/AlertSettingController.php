<?php

namespace App\Http\Controllers;

use App\AlertSetting;
use App\Institution;
use App\Report;
use App\ReportField;
use Illuminate\Http\Request;

class AlertSettingController extends Controller
{
   //Index method for Alerts Controller
    public function index(Request $request)
    {
        $institutions = array();
        if (auth()->user()->hasRole("Admin")) { // show them all
            $data = AlertSetting::orderBy('id', 'ASC')->get();
            $institutions = Institution::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();
            $institutions[1] = "Consortium";
        } else {                                // limit to user's inst
            $data = AlertSetting::orderBy('id', 'ASC')
                   ->where('inst_id', '=', auth()->user()->inst_id)->get();
        }

       // Get reports known to the system (to populate the dropdown)
        $master_reports = Report::where('parent_id', '=', 0)->orderBy('dorder', 'ASC')->get();
        $reports = array();
        $reports_json = "";
        foreach ($master_reports as $_report) {
            $reports[$_report->id] = $_report->name;
            $reports_json .= $_report->id . ":" . $_report->name . "(v" . $_report->revision . "),";
        }
        $reports_json = preg_replace("/,$/", "", $reports_json);
        array_unshift($reports, "Choose a Report");

        return view('alertsettings.dashboard', compact('data', 'reports_json', 'reports', 'institutions'));
    }

    /**
     * Update alert settings all-at-once. Whatever the UI holds will get
     * saved. Any "delete" operations will have already been handled
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $input = $request->all();

       // If user is not an admin, only process settings for the current user's inst_id.
        if (auth()->user()->hasRole("Admin")) { // get them all
            $settings = AlertSetting::orderBy('id', 'ASC')->get();
        } else {
            $settings = AlertSetting::orderBy('id', 'ASC')
                        ->where('inst_id', '=', auth()->user()->inst_id)->get();
        }

       // Loop through the existing settings that arrive in the request and update them.
        foreach ($settings as $setting) {
            $setting_update = array();
            if (isset($input['met_' . $setting->id])) {
                $setting_update['id'] = $setting->id;
                $setting_update['is_active'] = isset($input['cb_' . $setting->id]) ? 1 : 0;
                if (auth()->user()->hasRole("Admin")) {
                    $setting_update['inst_id'] = isset($input['inst_' . $setting->id])
                                                 ? $input['inst_' . $setting->id] : 0;
                } else {  // ignore inst_id in request if not admin
                    $setting_update['inst_id'] = auth()->user()->inst_id;
                }
                $setting_update['field_id'] = isset($input['met_' . $setting->id])
                                               ? $input['met_' . $setting->id] : 0;
                $setting_update['variance'] = isset($input['var_' . $setting->id])
                                              ? $input['var_' . $setting->id] : 0;
                $setting_update['timespan'] = isset($input['time_' . $setting->id])
                                              ? $input['time_' . $setting->id] : 0;
                $setting->update($setting_update);
            }
        }

        // Check for new alerts to be added.
        // Arguments arrive as arrays : one for each column
        $idx = 0;
        while (true) {
            if (!isset($input['newmet'][$idx])) {
                break;
            }
            $_set = array();
            $_set['is_active'] = isset($input['newcb'][$idx]) ? 1 : 0;
            $_set['field_id'] = isset($input['newmet'][$idx]) ? $input['newmet'][$idx] : 0;
            if (auth()->user()->hasRole("Admin")) {
                $_set['inst_id'] = isset($input['newinst'][$idx]) ? $input['newinst'][$idx] : 0;
            } else {  // override/force inst_id in request if not admin
                $_set['inst_id'] = auth()->user()->inst_id;
            }
            $_set['variance'] = isset($input['newvar'][$idx]) ? $input['newvar'][$idx] : 0;
            $_set['timespan'] = isset($input['newts'][$idx]) ? $input['newts'][$idx] : 0;
            $new_setting = AlertSetting::create($_set);
            $idx++;
        }

        // Redirect and notify
        return redirect()->route('alertsettings.index')
                        ->with('success', 'Alert settings successfully saved');
    }

    /**
     * Refresh the records being displayed by applying either or both filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return JSON
     */
    public function fieldsRefresh(Request $request)
    {
        // Validate form inputs
        $this->validate($request, ['report_id' => 'required']);

        // Get the reportfields
        $data = ReportField::where('report_id', '=', $request->report_id)
                            ->where('is_alertable', '=', 1)
                            ->orderBy('id', 'ASC')->get()->toArray();

        // return the records and role flags
        $main = array('fields' => $data);
        return response()->json($main);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\AlertSetting  $alertsetting
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $setting = AlertSetting::findOrFail($id);
        abort_unless($setting->canManage(), 403);
        dd('About to delete ID: ' . $id);
      // $setting->delete();

        return redirect()->route('alertsettings.index')
                      ->with('success', 'Alert setting deleted successfully');
    }
}
