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

        $data = Alert::with('provider','alertSetting','alertSetting.reportField','alertSetting.institution',
                            'harvest','harvest.sushiSetting','harvest.sushiSetting.institution','user')
                     ->orderBy('id', 'ASC')->get();

        $records = array();
        foreach ($data as $alert) {
            if (is_null($alert->alertsettings_id) && is_null($alert->harvest_id)) {
                continue;
            }
            $record = array('id' => $alert->id);
            if (!is_null($alert->alertsettings_id)) {
                $_inst_id = $alert->alertSetting->inst_id;
                $_inst_name = $alert->alertSetting->institution->name;
                $record['detail_url'] = "/alertsettings/" . $alert->alertsettings_id;
                $record['detail_txt'] = $alert->alertSetting->reportField->legend . " is out of bounds!";
            } else {
                $_inst_id = $alert->harvest->sushiSetting->inst_id;
                $_inst_name = $alert->harvest->sushiSetting->institution->name;
                $record['detail_url'] = "/harvestlogs/" . $alert->harvest_id;
                $record['detail_txt'] = "details";
            }
            if (!auth()->user()->hasRole("Admin") && $_inst_id != auth()->user()->inst_id) {
                continue;
            }
            $record['prov_name'] = $alert->provider->name;
            $record['reportName'] = $alert->reportName();
            $record['yearmon'] = $alert->yearmon;
            $record['status'] = $alert->status;
            $record['stat_id'] = "stat_" . $alert->id;
            $record['mod_by'] = ($alert->modified_by == 1) ? 'CC-Plus System' : $alert->user->name;
            $record['inst_name'] = ($_inst_id == 1)  ? "Consortia-wide" : $_inst_name;
            $record['updated_at'] = $alert->updated_at;
            $records[] = $record;
        };

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
