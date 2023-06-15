<?php

namespace App\Http\Controllers;

use DB;
use App\Provider;
use App\Institution;
use App\Report;
use App\HarvestLog;
use App\SushiSetting;
use App\ConnectionField;
use App\GlobalProvider;
use App\Consortium;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\Writer\Xls;

class ProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);

        $consodb = config('database.connections.consodb.database');
       // Get all providers
        $provider_data = Provider::with('institution:id,name','sushiSettings:id,prov_id,last_harvest','reports:id,name',
                                        'globalProv')
                                 ->orderBy('name','ASC')->get();
        $existingIds = $provider_data->pluck('global_id')->toArray();

        // Map columns to simplify the datatable
        $providers = $provider_data->map( function ($rec) use ($thisUser) {
            $rec->inst_name = ($rec->institution->id == 1) ? 'Entire Consortium' : $rec->institution->name;
            $rec->day_of_month = $rec->day_of_month;
            $last_harvest = $rec->sushiSettings->max('last_harvest');
            $rec->can_delete = (is_null($last_harvest)) ? true : false;
            $rec->reports_string = ($rec->reports) ? $this->makeReportString($rec->reports) : 'None';
            $rec->connectors = $rec->globalProv->connectionFields();
            return $rec;
        })->toArray();

       // Get all institutions
        $institutions = Institution::where('id','>',1)->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        array_unshift($institutions,array('id' => 1,'name' => 'Entire Consortium'));

       // Pull unset global provider definitions
        $unset_global = GlobalProvider::whereNotIn('id',$existingIds)->orderBy('name', 'ASC')->get();
        $cur_instance = Consortium::where('ccp_key', session('ccp_con_key'))->first();
        $conso_name = ($cur_instance) ? $cur_instance->name : "Template";
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
        return view('providers.index', compact('conso_name', 'providers', 'institutions', 'unset_global', 'master_reports'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $thisUser = auth()->user();
        $con_prov = Provider::with('reports:reports.id,reports.name','globalProv')->findOrFail($id);
        // $con_prov holds the database record, $provider is what we'll build and pass to the UI
        $provider = $con_prov->data();

       // Build data to be passed based on whether the user is admin or Manager
        $limit_to_insts = null;
        if ($thisUser->hasRole("Admin")) {
            $sushi_settings = SushiSetting::with('institution')->where('prov_id',$con_prov->id)->get();

            // Get last_harvest for the provider (ALL insts) as determinant for whether it can be deleted
            $last_harvest = $sushi_settings->max('last_harvest');
            $provider['can_delete'] = (is_null($last_harvest)) ? true : false;

            // Make an institutions list
            $institutions = Institution::where('id','>',1)->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            array_unshift($institutions,array('id' => 1,'name' => 'Entire Consortium'));

            // Setup an array of insts without settings for this provider
            $set_inst_ids = $con_prov->sushiSettings->pluck('inst_id');
            $set_inst_ids[] = 1;
            $unset_institutions = Institution::whereNotIn('id', $set_inst_ids)
                                             ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        } else {  // Managers/Users are limited to their own inst
            $user_inst = $thisUser->inst_id;
            $limit_to_insts = array($user_inst);
            $sushi_settings = SushiSetting::with('institution')
                                          ->where('prov_id',$con_prov->id)->where('inst_id', $user_inst)->get();
            $last_harvest = $sushi_settings->max('last_harvest');
            $provider['can_delete'] = ($thisUser->inst_id == $con_prov->inst_id && is_null($last_harvest)) ? true : false;
            $institutions = Institution::where('id', '=', $user_inst)->get(['id','name'])->toArray();
            $unset_institutions = array();
            if ($sushi_settings->count() == 0) {
                $unset_institutions[] = Institution::where('id', $user_inst)->first()->toArray();
            }
        }

        // Add on Sushi Settings
        $provider['sushiSettings'] = $sushi_settings->toArray();

        // Master reports limited to whet is defined for the related global provider
        $master_reports = Report::whereIn('id', $con_prov->globalProv->master_reports)->orderBy('name','ASC')->get(['id','name']);

        // setup reprts_state structure to mkae checkboxres with
        $rpt_state = [];
        foreach ($master_reports as $rpt) {
            $rpt_state[$rpt->name] = ($con_prov->reports->where('name',$rpt->name)->first()) ? true : false;
        }
        $provider['report_state'] = $rpt_state;

        // Connection fields is the set defined for the related global provider
        $connectors = ConnectionField::whereIn('id', $con_prov->globalProv->connectors)->get()->values()->toArray();

        // Get 10 most recent harvests
        $harvests = HarvestLog::with('report:id,name',
                                     'sushiSetting',
                                     'sushiSetting.institution:id,name',
                                     'sushiSetting.provider:id,name'
                                    )
                              ->join('sushisettings', 'harvestlogs.sushisettings_id', '=', 'sushisettings.id')
                              ->when($limit_to_insts, function ($query, $limit_to_insts) {
                                    return $query->whereIn('sushisettings.inst_id', $limit_to_insts);
                              })
                              ->where('sushisettings.prov_id', $id)
                              ->orderBy('harvestlogs.updated_at', 'DESC')->limit(10)
                              ->get('harvestlogs.*')->toArray();

        return view('providers.show', compact(
            'provider',
            'institutions',
            'unset_institutions',
            'master_reports',
            'connectors',
            'harvests'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return redirect()->route('providers.show', [$id]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $thisUser = auth()->user();
        $provider = Provider::with('globalProv')->findOrFail($id);
        if (!$provider->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $was_active = $provider->is_active;
        $fields = ConnectionField::whereIn('id',$provider->globalProv->connectors)->get()->values()->toArray();

        // Validate form inputs
        $this->validate($request, ['is_active' => 'required']);
        $input = $request->all();
        $prov_input = array_except($input,array('master_reports'));

        // Disallow a manager restricting themselves from an institution-specific provider
        if ($provider->inst_id != 1 && !$thisUser->hasRole('Admin')) {   // local-admin/Manager adding an institution-specific vendor?
            $prov_input['restricted'] = 0;
        }

        // Update the record and assign reports in master_reports
        $provider->update($prov_input);
        $all_master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
        if (!is_null($request->input('report_state'))) {
            $provider->reports()->detach();
            $report_state = $request->input('report_state');
            // attach reports to the provider, but only if the requested one(s) are in global:master_reports
            $global_master_list = $provider->globalProv->master_reports;
            foreach ($global_master_list as $id) {
                $master = $all_master_reports->where('id',$id)->first();
                if (!$master) continue;
                if ($report_state[$master->name]) {
                    $provider->reports()->attach($id);
                }
            }
        }
        if ($thisUser->hasRole("Admin")) {
            $settings = SushiSetting::with('institution')->where('prov_id',$provider->id)->get();
        } else {
            $settings = SushiSetting::with('institution')
                                    ->where('prov_id',$provider->id)->where('inst_id', $thisUser->inst_id)->get();
        }
        // If is_active is changing, check and update related sushi settings
        if ($was_active != $provider->is_active) {
            foreach ($settings as $setting) {
              // skip disabled settings
                if ($setting->status == 'Disabled') continue;
              // If required connectors all have values, check to see if sushi setting status needs updating
                if ($setting->isComplete()) {
                  // Setting is Enabled, provider going inactive, suspend it
                    if ($setting->status == 'Enabled' && $was_active && !$provider->is_active ) {
                        $setting->update(['status' => 'Suspended']);
                    }
                  // Setting is Suspended, provider going active with active institution, enable it
                    if ($setting->status == 'Suspended' && !$was_active && $provider->is_active &&
                        $setting->institution->is_active) {
                        $setting->update(['status' => 'Enabled']);
                    }
                  // Setting status is Incomplete, provider is active and institution is active, enable it
                    if ($setting->status == 'Incomplete' && $provider->is_active && $setting->institution->is_active) {
                        $setting->update(['status' => 'Enabled']);
                    }
              // If required conenctors are missing value(s), mark them and update setting status tp Incomplete
                } else {
                    $setting_updates = array('status' => 'Incomplete');
                    foreach ($fields as $fld) {
                        $name = $fld['name'];
                        if ($setting->$name == null || $setting->$name == '') {
                            $setting_updates[$name] = "-missing-";
                        }
                    }
                    $setting->update($setting_updates);
                }
            }
        }

        // return flags for enabled master-reports
        $rpt_state = [];
        foreach ($all_master_reports as $rpt) {
            $rpt_state[$rpt->name] = ($provider->reports->where('name',$rpt->name)->first()) ? true : false;
        }
        $provider->report_state = $rpt_state;
        $provider->reports_string = ($provider->reports) ? $this->makeReportString($provider->reports) : 'None';
        $provider->connectors = $provider->globalProv->connectionFields();

        // Return updated provider data
        $provider->load('reports:reports.id,reports.name');
        $last_harvest = $settings->max('last_harvest');
        $provider['can_delete'] = (is_null($last_harvest)) ? true : false;
        $provider['sushiSettings'] = $settings->toArray();

        return response()->json(['result' => true, 'msg' => 'Provider settings successfully updated',
                                 'provider' => $provider]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function connect(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);

        if (is_null($request->input('inst_id')) || is_null($request->input('global_id'))) {
            return response()->json(['result' => false, 'msg' => 'Store Failed: missing input arguments!']);
        }

        // Create the provider
        $global_provider = GlobalProvider::findOrFail($request->input('global_id'));
        $provider = new Provider;
        $provider->name = $global_provider->name;
        $provider->inst_id = $request->input('inst_id');
        $provider->restricted = ($request->input('inst_id') == 1) ? 1 : 0;
        $provider->global_id = $request->input('global_id');
        $provider->is_active = $global_provider->is_active;
        $provider->save();

        // Setup return object
        $provider->load('institution');

        // Enable all available reports (based on the global provider)
        $reports_string = '';
        $report_state = array();
        $all_master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
        $global_reports = $global_provider->master_reports;
        foreach ($all_master_reports as $mr) {
          if ( in_array($mr->id, $global_provider->master_reports) ) {
              $provider->reports()->attach($mr->id);
              $reports_string .= ($reports_string == "") ? "" : ", ";
              $reports_string .= $mr->name;
              $report_state[$mr->name] = true;
          } else {
              $report_state[$mr->name] = false;
          }
        }

        // Create a sushi-setting to give a "starting point" for connecting it later
        $sushi_setting = new SushiSetting;
        $sushi_setting->inst_id = $request->input('inst_id');
        $sushi_setting->prov_id = $provider->id;
        // Add required conenction fields to sushi args
        foreach ($global_provider->connectionFields() as $cnx) {
            $sushi_setting->{$cnx['name']} = "-missing-";
        }
        $sushi_setting->status = "Incomplete";
        $sushi_setting->save();

        // Setup return object; start by getting names of connected institutions
        if ($thisUser->hasRole("Admin")) {
            $conso_providers = Provider::with('institution:id,name','sushiSettings:id,prov_id,last_harvest','reports:id,name',
                                              'globalProv')->get();
        } else {
            $conso_providers = Provider::where('global_id', $global_provider->id)->whereIn('inst_id', [1,$thisUser->inst_id])
                                       ->with('institution:id,name','sushiSettings:id,prov_id,last_harvest',
                                              'reports:id,name','globalProv')->get();
        }
        // connected array returned from theis function is always either the consortium or a single institution
        $global_provider->connected = array('id' => $provider->inst_id, $provider->institution->name);
        $global_provider->conso_id = $provider->id;
        $global_provider->inst_id = $provider->inst_id;
        $global_provider->connectors = $global_provider->connectionFields();
        $global_provider->inst_name = ($provider->inst_id == 1) ? 'Entire Consortium' : $provider->institution->name;
        $global_provider->active = ($provider->is_active) ? 'Active' : 'Inactive';
        $global_provider->day_of_month = $provider->day_of_month;
        $global_provider->report_state = $report_state;
        $global_provider->reports_string = $reports_string;
        $global_provider->can_edit = $provider->canManage();
        $global_provider->can_delete = $provider->canManage();
        // return the global provider data with the consorium provider data merged in
        return response()->json(['result' => true, 'msg' => 'Provider created successfully', 'provider' => $global_provider]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Provider  $id
     */
    public function destroy($id)
    {
        $provider = Provider::with('globalProv')->findOrFail($id);
        if (!$provider->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

        try {
            $provider->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }

        return response()->json(['result' => true, 'msg' => 'Provider successfully deleted']);
    }

    /**
     * Build string representation of master_reports array
     *
     * @param  Array  $reports
     * @return String
     */
    private function makeReportString($reports) {
        $report_string = '';
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('name','ASC')->get(['id','name']);
        foreach ($master_reports as $mr) {
            if ($reports->where('id',$mr->id)->first()) {
                $report_string .= ($report_string == '') ? '' : ', ';
                $report_string .= $mr->name;
            }
        }
        return $report_string;
    }
}
