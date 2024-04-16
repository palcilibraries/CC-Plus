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
     * Return provider data for JSON request (matches what dashboards expect)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);
        $json = ($request->input('json')) ? true : false;

        // Nothing needs to hit this method anymore, except JSON requests from provider datatable
        if (!$json) {
            return response()->json(['result' => false, 'msg' => 'Module disabled - check source in github find a developer.']);
        }

        // Get master report definitions
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('name','ASC')->get(['id','name']);

        // Get all (consortium) providers, extract array of global IDs
        $conso_providers = Provider::with('sushiSettings:id,prov_id,last_harvest','reports:id,name','globalProv',
                                          'institution:id,name')->orderBy('name','ASC')->get();

        // Build list of providers, based on globals, that includes extra mapped in consorium-specific data
        $global_providers = GlobalProvider::orderBy('name', 'ASC')->get();

        $output_providers = [];
        foreach ($global_providers as $rec) {
            $rec->global_prov = $rec->toArray();
            $rec->connectors = $rec->connectionFields();
            $rec->can_edit = false;   // default value for unconnected global provider
            $rec->can_connect = true; //    "      "    "       "         "       "
            $rec->conso_id = null;
            $rec->inst_id = null;
            $rec->inst_name = null;
            $rec->active = ($rec->is_active) ? 'Active' : 'Inactive';
            $rec->day_of_month = null;
            $rec->can_delete = false;
            $rec->connected = array();
            $reports_string = ($rec->master_reports) ?
                                   $this->makeReportString($rec->master_reports, $master_reports) : '';
            $rec->report_state = $this->reportState($rec->master_reports, $master_reports);

            // Remap master reports to just the globally available ones and add names
            $_reports = [];
            foreach ($master_reports as $rpt) {
                if (in_array($rpt->id, $rec->master_reports)) {
                    $_reports[] = array('id' => $rpt->id, 'name' => $rpt->name);
                }
            }
            $rec->master_reports = $_reports;
            $rec->reports_string = ($reports_string == '') ? "None" : $reports_string;

            // Setup connected institution data for all outpute records
            $connected_insts = array();
            $connected_providers = $conso_providers->where('global_id',$rec->id);
            foreach ($connected_providers as $prov_data) {
                $_name = ($prov_data->inst_id == 1) ? 'Entire Consortium' : $prov_data->institution->name;
                $connected_insts[] = array('id' => $prov_data->instid, 'name' => $_name);
            }

            // Include globals not connected to the consortium in the array
            $conso_connection = $connected_providers->where('inst_id',1)->first();
            if (!$conso_connection) {
                $rec->connected = array();
                $rec->connection_count = 0;
                $output_providers[] = $rec->toArray();
            }

            // Include all providers connected to the global in the array
            foreach ($connected_providers as $prov_data) {
                $rec->inst_id = $prov_data->inst_id;
                $rec->inst_name = $prov_data->institution->name;
                // inst-specific providers show only one connection; consortium providers include all
                $rec->connected = ($rec->inst_id==1) ? $connected_insts
                                                     : array( array('id' => $rec->inst_id, 'name' => $rec->inst_name) );
                $rec->connection_count = count($rec->connected);
                $rec->can_edit = true;
                $rec->conso_id = $prov_data->id;
                $rec->is_active = $prov_data->is_active;
                $rec->active = ($prov_data->is_active) ? 'Active' : 'Inactive';
                $rec->day_of_month = $prov_data->day_of_month;
                $rec->last_harvest = $prov_data->sushiSettings->max('last_harvest');
                $rec->restricted = $prov_data->restricted;
                $rec->allow_inst_specific = $prov_data->allow_inst_specific;
                if ($conso_connection) {
                    $rec->can_connect = ($conso_connection->allow_inst_specific && $rec->inst_id == 1) ? true : false;
                } else {
                    $rec->can_connect = ($rec->inst_id == 1) ? true : false;
                }
                $rec->can_delete = (is_null($rec->last_harvest)) ? true : false;
                if ($prov_data->reports) {
                    $report_ids = $prov_data->reports->pluck('id')->toArray();
                    $rec->reports_string = $this->makeReportString($report_ids, $master_reports);
                    $rec->report_state = $this->reportState($report_ids, $master_reports);
                }
                $output_providers[] = $rec->toArray();
            }
        }
        $providers = array_values($output_providers);
        return response()->json(['providers' => $providers], 200);
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
        $prov_input = array_except($input,array('master_reports','allow_sushi'));

        // Disallow a manager restricting themselves from their own institution-specific provider
        if (isset($input['allow_sushi'])) {
            $prov_input['restricted'] = ($input['allow_sushi'] == 1) ? 0 : 1; // default
            if ($provider->inst_id != 1 && !$thisUser->hasRole('Admin')) {
                $prov_input['restricted'] = 0;
            }
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
                // Went from Active to Inactive
                if ($was_active) {
                    $setting->update(['status' => 'Disabled']);
                // Went from Inactive to Active
                } else {
                    $setting->resetStatus();
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

        // If requrested, create a sushi-setting to give a "starting point" for connecting it later
        $stub = ($request->input('sushi_stub')) ? $request->input('sushi_stub') : 0;
        if ($stub) {
            $sushi_setting = new SushiSetting;
            $sushi_setting->inst_id = $request->input('inst_id');
            $sushi_setting->prov_id = $provider->id;
            // Add required conenction fields to sushi args
            foreach ($global_provider->connectionFields() as $cnx) {
                $sushi_setting->{$cnx['name']} = "-missing-";
            }
            $sushi_setting->status = "Incomplete";
            $sushi_setting->save();
        }

        // Setup return object; start by getting names of connected institutions
        $conso_providers = Provider::with('institution:id,name','sushiSettings:id,prov_id,last_harvest',
                                          'reports:id,name','globalProv')->where('global_id', $global_provider->id)->get();
        $global_provider->connected = $conso_providers->where('global_id',$global_provider->id)->pluck('institution')->toArray();
        $global_provider->connection_count = count($global_provider->connected);
        $global_provider->conso_id = $provider->id;
        $global_provider->inst_id = $provider->inst_id;
        $global_provider->can_connect = false;
        $global_provider->connectors = $global_provider->connectionFields();
        $global_provider->inst_name = ($provider->inst_id == 1) ? 'Entire Consortium' : $provider->institution->name;
        $global_provider->active = ($provider->is_active) ? 'Active' : 'Inactive';
        $global_provider->day_of_month = $provider->day_of_month;
        $global_provider->last_harvest = null;
        $global_provider->report_state = $report_state;
        $global_provider->reports_string = $reports_string;
        $global_provider->restricted = $provider->restricted;
        $global_provider->allow_inst_specific = false;
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
