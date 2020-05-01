<?php

namespace App\Http\Controllers;

use App\Alert;
use App\Provider;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

   // Index method for Alerts Controller
    public function index(Request $request)
    {
        $__options = Alert::getEnumValues('status');
        array_unshift($__options, 'ALL');
        $status_options = array_combine($__options, $__options);

        if (auth()->user()->hasRole("Admin")) { // show them all
            $data = Alert::orderBy('id', 'ASC')->get();
        } else {                                // limit to user's inst
            $data = Alert::orderBy('id', 'ASC')->where('inst_id', auth()->user()->inst_id)->get();
        }

        $records = $data->map(function ($record) {
            $record['inst_name'] = ($record->inst_id == 1)  ? "Consortia-wide" : $record->institution()->name;
            $record['stat_id'] = "stat_" . $record->id;
            $record['mod_by'] = ($record->modified_by == 1) ? 'CC-Plus System' : $record->user->name;
            if (!is_null($record->harvest_id)) {
                $record['detail_url'] = "/harvestlogs/" . $record->harvest->id;
                $record['detail_txt'] = "details";
            }
            if (!is_null($record->alertsettings_id)) {
                $record['detail_url'] = "/alertsettings/" . $record->alertsettings_id;
                $record['detail_txt'] = $record->alertSetting->reportField->legend . " is out of bounds!";
            }
            return $record;
        });

       // Providers to display in the dropdown
        $providers = $data->map(function ($item, $key) {
            return $item->provider;
        })->unique('id')->pluck('name', 'id')->toArray();
        array_unshift($providers, 'ALL');

        return view('alerts.dashboard', compact('records', 'status_options', 'providers'))
               ->with('i', ($request->input('page', 1) - 1) * 10);
    }

   /**
    * Update status for a given alert record
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function updateStatus(Request $request)
    {
       // value of name should be : stat_nnnn, without leading zeros, where nnnn is the alert ID
        $alert_id = substr($request->name, 5);
        $record = Alert::findOrFail($alert_id);
        abort_unless($record->canManage(), 403);
       // Validate form inputs
        $this->validate($request, ['status' => 'required']);
        $record->status = $request->status;
        $record->modified_by = auth()->id();
       // Update the record
        $record->save();
        return response()->json($record);
    }

   /**
    * Refresh the records being displayed by applying either or both filters
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  int  $id
    * @return JSON
    */
    public function dashRefresh(Request $request)
    {
       // Validate form inputs
        $this->validate($request, ['filt_stat' => 'required', 'filt_prov' => 'required']);
       // Setup the where clause
        if (auth()->user()->hasRole("Admin")) { // default to all
            $__where = array();
        } else {  // default to just current user's inst
            $__where = array(['inst_id', '=', auth()->user()->inst_id]);
        }
        if ($request->filt_stat != "ALL") {
            $__where[] = array('status', '=', $request->filt_stat);
        }
        if ($request->filt_prov != 0) {
            $__where[] = array('prov_id', '=', $request->filt_prov);
        }

       // Get the records, and tack on related fields before returning json
       // (there's probably a better way to do this)
        $alerts = Alert::orderBy('id', 'ASC')->where($__where)->get();
        $data = array();
        foreach ($alerts as $alert) {
            $_rec = $alert->toArray();
            $_rec['detail'] = $alert->detail();
            $_rec['report_name'] = $alert->reportName();
            if (auth()->user()->hasRole("Admin")) {
                $_rec['inst_id'] = $alert->institution()->id;
                $_rec['inst_name'] = ($alert->institution()->id < 2) ? 'Consortia-wide' : $alert->institution()->name;
            }
            $_rec['prov_name'] = $alert->provider->name;
            $data[] = $_rec;
        }

       // return the records and role flags
        $main = array('records' => $data, 'admin' => auth()->user()->hasRole('Admin'),
                                        'manager' => auth()->user()->hasRole('Manager') );
        return response()->json($main);
    }
}
