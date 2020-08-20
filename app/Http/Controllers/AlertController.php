<?php

namespace App\Http\Controllers;

use App\Alert;
use App\Provider;
use App\Severity;
use App\SystemAlert;
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
        $statuses = Alert::getEnumValues('status');
        array_unshift($statuses, 'ALL');

        // Grab error-severities that apply to alerts
        $severities = Severity::where('id', '<', 10)->get(['id','name'])->toArray();

        $data = Alert::with('provider:id,name', 'alertSetting', 'alertSetting.reportField', 'user:id,name')
                     ->orderBy('alerts.created_at', 'DESC')->get();

        $records = array();
        foreach ($data as $alert) {
            if (is_null($alert->alertsettings_id) && is_null($alert->harvest_id)) { // broken record?
                continue;
            }

            // If not admin, skip inst-specific alerts for other institutions
            $_inst_id = $alert->institution()->id;
            if ($_inst_id != 1  && $_inst_id != auth()->user()->inst_id && !auth()->user()->hasRole("Admin")) {
                continue;
            }

            // Build a record for the view
            $record = array('id' => $alert->id, 'yearmon' => $alert->yearmon, 'status' => $alert->status,
                            'updated_at' => $alert->updated_at);

            if (!is_null($alert->alertsettings_id)) {
                $record['detail_url'] = "/alertsettings/" . $alert->alertsettings_id;
                $record['detail_txt'] = $alert->alertSetting->reportField->legend . " is out of bounds!";
            } else {
                $record['detail_url'] = "/harvestlogs/" . $alert->harvest_id . '/edit';
                $record['detail_txt'] = "Harvest failed";
            }
            $record['report_name'] = $alert->reportName();
            $record['mod_by'] = ($alert->modified_by == 1) ? 'CC-Plus System' : $alert->user->name;
            $record['inst_name'] = ($_inst_id == 1)  ? "Consortia-wide" : $alert->institution()->name;
            $record['prov_name'] = $alert->provider->name;
            $records[] = $record;
        };

       // Providers to display in the dropdown
        $providers = $data->map(function ($item, $key) {
            return $item->provider;
        })->unique('id')->pluck('name', 'id')->toArray();
        array_unshift($providers, 'ALL');

        // Get all system alerts
        $sysalerts = SystemAlert::with('severity')
                                ->orderBy('severity_id', 'DESC')->orderBy('updated_at', 'DESC')->get()->toArray();

        return view('alerts.dashboard', compact('records', 'sysalerts', 'providers', 'statuses', 'severities'));
    }

   /**
    * Update status for a given alert
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function updateStatus(Request $request)
    {
        abort_unless(auth()->user()->hasRole("Admin"), 403);

        // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
        }
        if (!isset($input['id']) || !isset($input['status'])) {
            return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
        }
        $alert = Alert::findOrFail($input['id']);

       // Validate form inputs
        $alert->status = $input['status'];
        $alert->modified_by = auth()->id();

       // Update the record
        $alert->save();
        return response()->json(['result' => true]);
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
     public function destroy($id)
     {
         abort_unless(auth()->user()->hasRole("Admin"), 403);
         $record = Alert::findOrFail($id);

         // Delete the harvestlog record itself
         $record->delete();
         return response()->json(['result' => true, 'msg' => 'Alert successfully deleted']);
     }
}
