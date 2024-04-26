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
        $global_providers = GlobalProvider::orderBy('name')->get()->sortBy('name', SORT_NATURAL|SORT_FLAG_CASE);

        $item_key = 0;
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
                $connected_insts[] = array('id' => $prov_data->inst_id, 'name' => $_name);
            }

            // Include globals not connected to the consortium in the array
            $conso_connection = $connected_providers->where('inst_id',1)->first();
            if (!$conso_connection) {
                $rec->connected = array();
                $rec->connection_count = 0;
                $rec->item_key = $item_key;
                $output_providers[] = $rec->toArray();
                $item_key++;
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
                $rec->item_key = $item_key;
                $output_providers[] = $rec->toArray();
                $item_key++;
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
        $provider = Provider::with('globalProv','reports')->findOrFail($id);
        if (!$provider->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        if (!$provider->globalProv) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Global Provider Undefined']);
        }
        $global_provider = $provider->globalProv;
        $was_active = $provider->is_active;
        $fields = ConnectionField::whereIn('id',$global_provider->connectors)->get()->values()->toArray();

        // Validate form inputs
        $this->validate($request, ['is_active' => 'required']);
        $input = $request->all();
        $prov_input = array_except($input,array('master_reports','allow_sushi','report_state'));

        // Disallow a manager restricting themselves from their own institution-specific provider
        if (isset($input['allow_sushi'])) {
            $prov_input['restricted'] = ($input['allow_sushi'] == 1) ? 0 : 1; // default
            if ($provider->inst_id != 1 && !$thisUser->hasRole('Admin')) {
                $prov_input['restricted'] = 0;
            }
        }

        // Update the record and assign reports in master_reports
        $report_ids = [];
        $provider->update($prov_input);
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('name','ASC')->get(['id','name']);
        if (isset($input['report_state'])) {
            $provider->reports()->detach();
            // attach reports to the provider, but only if the requested one(s) are in global:master_reports
            $global_master_list = $global_provider->master_reports;
            foreach ($global_master_list as $id) {
                $master = $master_reports->where('id',$id)->first();
                if (!$master) continue;
                if ($input['report_state'][$master->name]) {
                    $provider->reports()->attach($id);
                    $report_ids[] = $id;
                }
            }
        }
        if ($thisUser->hasRole("Admin")) {
            $settings = SushiSetting::with('institution')->where('prov_id',$provider->id)->get();
        } else {
            $settings = SushiSetting::with('institution')
                                    ->where('prov_id',$provider->id)->where('inst_id', $thisUser->inst_id)->get();
        }
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

        // Build return provider data Object that matches what indexx() sends
        $conso_providers = Provider::with('institution:id,name')->where('global_id', $global_provider->id)->get();
        $return_provider = $global_provider;
        $return_provider->global_prov = $global_provider->toArray();
        $return_provider->connectors = $global_provider->connectionFields();
        $return_provider->can_connect = true; //    "      "    "       "         "       "
        $return_provider->can_delete = false;
        // Set master reports to the globally available ones and add names
        $_reports = [];
        foreach ($master_reports as $rpt) {
            if (in_array($rpt->id, $global_provider->master_reports)) {
                $_reports[] = array('id' => $rpt->id, 'name' => $rpt->name);
            }
        }
        $return_provider->master_reports = $_reports;
        // Set connected institution data for all outpute records
        $connected_insts = array();
        $connected_providers = $conso_providers->where('global_id',$global_provider->id);
        foreach ($connected_providers as $prov_data) {
            $_name = ($prov_data->inst_id == 1) ? 'Entire Consortium' : $prov_data->institution->name;
            $connected_insts[] = array('id' => $prov_data->inst_id, 'name' => $_name);
        }
        $conso_connection = $connected_providers->where('inst_id',1)->first();
        $return_provider->inst_id = $provider->inst_id;
        $return_provider->inst_name = $provider->institution->name;
        // inst-specific providers show only one connection; consortium providers include all
        $return_provider->connected = ($provider->inst_id==1) ? $connected_insts
                                      : array( array('id' => $provider->inst_id, 'name' => $return_provider->inst_name) );
        $return_provider->connection_count = count($return_provider->connected);
        $return_provider->can_edit = true;  // canManage() should be true to ever get here...
        $return_provider->conso_id = $provider->id;
        $return_provider->is_active = $provider->is_active;
        $return_provider->active = ($provider->is_active) ? 'Active' : 'Inactive';
        $return_provider->day_of_month = $provider->day_of_month;
        $return_provider->last_harvest = $provider->sushiSettings->max('last_harvest');
        $return_provider->can_delete = (is_null($provider->last_harvest)) ? true : false;
        $return_provider->restricted = $provider->restricted;
        $return_provider->allow_inst_specific = $provider->allow_inst_specific;
        if ($conso_connection) {
            $return_provider->can_connect = ($conso_connection->allow_inst_specific && $provider->inst_id == 1) ? true : false;
        } else {
            $return_provider->can_connect = ($provider->inst_id == 1) ? true : false;
        }
        if ($prov_data->reports) {
            $return_provider->reports_string = $this->makeReportString($report_ids, $master_reports);
            $return_provider->report_state = $this->reportState($report_ids, $master_reports);
        }

        return response()->json(['result' => true, 'msg' => 'Provider settings successfully updated',
                                 'provider' => $return_provider]);
    }

    /**
     * Connect a global provider.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function connect(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        // Handle inputs
        if (is_null($request->input('inst_id')) || is_null($request->input('global_id'))) {
            return response()->json(['result' => false, 'msg' => 'Store Failed: missing input arguments!']);
        }
        $input = $request->all();

        // Local Admins only create providers for their own institutions
        if (!($thisUser->hasRole('Admin')) && $input['inst_id'] != $thisUser->inst_id) {
            $input['inst_id'] = $thisUser->inst_id;
        }

        // Create the provider
        $global_provider = GlobalProvider::findOrFail($input['global_id']);
        $provider = new Provider;
        $provider->inst_id = $input['inst_id'];
        $provider->global_id = $input['global_id'];
        $provider->name = (isset($input['name'])) ? $input['name'] : $global_provider->name;
        $provider->is_active = (isset($input['is_active'])) ? $input['is_active'] : $global_provider->is_active;
        $restricted_default = ($input['inst_id'] == 1) ? 1 : 0;
        $provider->restricted = (isset($input['restricted'])) ? $input['restricted'] : $restricted_default;
        $provider->allow_inst_specific = (isset($input['allow_inst_specific'])) ? $input['allow_inst_specific'] : 0;
        $provider->save();

        // Attach reports and setup report_state and available_reports
        $all_master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
        $report_state = array();
        $available_reports = array();
        $global_master_ids = $global_provider->master_reports;
        foreach ($all_master_reports as $rpt) {
            $report_state[$rpt->name] = false;
            if (in_array($rpt->id, $global_master_ids)) {
                $available_reports[] = array('id' => $rpt->id, 'name' => $rpt->name);
                if (isset($input['report_state'])) {
                    if ($input['report_state'][$rpt->name]) {
                        $provider->reports()->attach($rpt->id);
                        $report_state[$rpt->name] = true;
                    }
                }
            }
        }

        // Set reports_string (for UI) based on report_state
        $reports_string = '';
        foreach ($report_state as $name => $val) {
            if ($val) {
                $reports_string .= ($reports_string == "") ? $name : ", " . $name;
            }
        }

        // If requested, create a sushi-setting to give a "starting point" for connecting it later
        $stub = (isset($input['sushi_stub'])) ? $input['sushi_stub'] : 0;
        if ($stub) {
            $sushi_setting = new SushiSetting;
            $sushi_setting->inst_id = $input['inst_id'];
            $sushi_setting->prov_id = $provider->id;
            // Add required conenction fields to sushi args
            foreach ($global_provider->connectionFields() as $cnx) {
                $sushi_setting->{$cnx['name']} = "-required-";
            }
            $sushi_setting->status = "Incomplete";
            $sushi_setting->save();
        }

        // Setup return object
        $provider->load('institution');
        $provider->conso_id = $provider->id;
        $provider->id = $global_provider->id;

        // Get names of connected institutions
        $conso_providers = Provider::with('institution:id,name','sushiSettings:id,prov_id,last_harvest',
                                          'reports:id,name','globalProv')->where('global_id', $global_provider->id)->get();
        if ($provider->inst_id == 1) {
            $provider->connected = $conso_providers->pluck('institution')->toArray();
            $provider->connection_count = count($global_provider->connected);
        } else {
            $cnx = $conso_providers->where('inst_id',$provider->inst_id)->pluck('institution')->toArray();
            $provider->connected = array($cnx);
            $provider->connection_count = 1;
        }
        $provider->can_connect = false;
        $provider->connectors = $global_provider->connectionFields();
        $provider->inst_name = $provider->institution->name;
        $provider->active = ($provider->is_active) ? 'Active' : 'Inactive';
        $provider->last_harvest = null;
        $provider->report_state = $report_state;
        $provider->reports_string = $reports_string;
        $provider->master_reports = $available_reports;
        $provider->allow_inst_specific = false;
        $provider->can_edit = $provider->canManage();
        $provider->can_delete = $provider->canManage();
        // return the global provider data with the consorium provider data merged in
        return response()->json(['result' => true, 'msg' => 'Provider successfully connected', 'provider' => $provider]);
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
     * @param  Collection  $master_reports
     * @return String
     */
    private function makeReportString($reports, $master_reports) {
        $report_string = '';
        foreach ($master_reports as $mr) {
            if (in_array($mr->id,$reports)) {
                $report_string .= ($report_string == '') ? '' : ', ';
                $report_string .= $mr->name;
            }
        }
        return $report_string;
    }

    /**
     * Export provider records from the database.
     * NOTE:: Provider exports are based on GLOBAL providers, and related to institutions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
     public function export(Request $request)
    {
        $thisUser = auth()->user();

        // Only admins and managers can export
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        $consodb = config('database.connections.consodb.database');

       // Admins get all providers
        if ($thisUser->hasRole("Admin")) {
            $providers = DB::table($consodb . '.providers as prv')
                      ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.global_id','prv.is_active','prv.inst_id',
                             'prv.restricted','prv.allow_inst_specific','inst.name as inst_name','day_of_month',]);
       // Managers get all consortia-wide providers and those that match user's inst_id
       // (excludes providers assigned to institutions.)
        } else {
            $providers = DB::table($consodb . '.providers as prv')
                      ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                      ->where('prv.inst_id', 1)
                      ->orWhere('prv.inst_id', $thisUser->inst_id)
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.global_id','prv.is_active','prv.inst_id',
                             'prv.restricted','prv.allow_inst_specific','inst.name as inst_name','day_of_month',]);
        }

        // Get all providers, with reports
        $all_providers = Provider::with('reports')->get();

        // Setup some styles arrays
        $leftbold_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];
        $topleft_style = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                           ],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E7');
        $info_sheet->getStyle('A1:E8')->applyFromArray($topleft_style);
        $info_sheet->getStyle('A1:E7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Providers tab represents a starting place for updating or importing settings. The table\n";
        $top_txt .= "below describes the datatype and order that the import expects. Any Import rows without a global provider\n";
        $top_txt .= "ID value in column A and a valid Institution ID column B will be ignored. If values are missing or invalid\n";
        $top_txt .= "for columns (B-H), but not required, they will be set to the 'Default'.\n\n";
        $top_txt .= "Any header row or columns beyond 'H' will be ignored. Once the data sheet contains everything\n";
        $top_txt .= "to be updated or inserted, save the sheet as a CSV and import it into CC-Plus.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A8')->applyFromArray($leftbold_style);
        $info_sheet->setCellValue('A8', "NOTE:");
        $info_sheet->mergeCells('B8:E10');
        $info_sheet->getStyle('B8:E10')->applyFromArray($topleft_style);
        $info_sheet->getStyle('B8:E10')->getAlignment()->setWrapText(true);
        $info_sheet->getStyle('A12:E20')->applyFromArray($topleft_style);
        $note_txt  = "Provider imports cannot be used to delete existing providers; only additions and updates are\n";
        $note_txt .= "supported. The recommended approach is to add to, or modify, a previously run full export\n";
        $note_txt .= "to ensure that desired end result is achieved.";
        $info_sheet->setCellValue('B8', $note_txt);
        $info_sheet->getStyle('A12:E12')->applyFromArray($leftbold_style);
        $info_sheet->setCellValue('A12', 'Column Name');
        $info_sheet->setCellValue('B12', 'Data Type');
        $info_sheet->setCellValue('C12', 'Description');
        $info_sheet->setCellValue('D12', 'Required');
        $info_sheet->setCellValue('E12', 'Default');
        $info_sheet->setCellValue('A13', 'Global Provider ID');
        $info_sheet->setCellValue('B13', 'Integer');
        $info_sheet->setCellValue('C13', 'Unique CC-Plus Provider ID');
        $info_sheet->setCellValue('D13', 'Yes');
        $info_sheet->setCellValue('A14', 'Institution ID');
        $info_sheet->setCellValue('B14', 'Integer');
        $info_sheet->setCellValue('C14', 'Unique CC-Plus Institution ID (Consortium is ID=1)');
        $info_sheet->setCellValue('D14', 'Yes');
        $info_sheet->setCellValue('A15', 'Name');
        $info_sheet->setCellValue('B15', 'String');
        $info_sheet->setCellValue('C15', 'Provider name');
        $info_sheet->setCellValue('D15', 'No');
        $info_sheet->setCellValue('E15', 'Global Provider Name');
        $info_sheet->setCellValue('A16', 'Active');
        $info_sheet->setCellValue('B16', 'String (Y or N)');
        $info_sheet->setCellValue('C16', 'Make the provider active?');
        $info_sheet->setCellValue('D16', 'No');
        $info_sheet->setCellValue('E16', 'Yes');
        $info_sheet->setCellValue('A17', 'Restricted');
        $info_sheet->setCellValue('B17', 'String (Y or N)');
        $info_sheet->setCellValue('C17', 'Allow local Admins to modify SUSHI credentials');
        $info_sheet->setCellValue('D17', 'No');
        $info_sheet->setCellValue('E17', 'Yes');
        $info_sheet->setCellValue('A18', 'Inst-Specific');
        $info_sheet->setCellValue('B18', 'String (Y or N)');
        $info_sheet->setCellValue('C18', 'Allow Institutional-Specific Definition (when InstID=1)');
        $info_sheet->setCellValue('D18', 'No');
        $info_sheet->setCellValue('E18', 'No');
        $info_sheet->setCellValue('A19', 'Harvest Day');
        $info_sheet->setCellValue('B19', 'Integer');
        $info_sheet->setCellValue('C19', 'Day of the month to harvest provider reports (1-28)');
        $info_sheet->setCellValue('D19', 'No');
        $info_sheet->setCellValue('E19', '15');
        $info_sheet->setCellValue('A20', 'Master Reports');
        $info_sheet->setCellValue('B20', 'Integer');
        $info_sheet->setCellValue('C20', 'CSV list of Master Report IDs to Harvest (e.g. : 1,3)');
        $info_sheet->setCellValue('D20', 'No');
        $info_sheet->setCellValue('E20', 'None');
        // Set row height and auto-width columns for the sheet

        for ($r = 1; $r < 21; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the provider data into a new sheet
        $providers_sheet = $spreadsheet->createSheet();
        $providers_sheet->setTitle('Providers');
        $providers_sheet->setCellValue('A1', 'Global Id');
        $providers_sheet->setCellValue('B1', 'Institution ID');
        $providers_sheet->setCellValue('C1', 'Name');
        $providers_sheet->setCellValue('D1', 'Active');
        $providers_sheet->setCellValue('E1', 'Restricted');
        $providers_sheet->setCellValue('F1', 'Inst-Specific');
        $providers_sheet->setCellValue('G1', 'Harvest Day');
        $providers_sheet->setCellValue('H1', 'Master Reports');
        $providers_sheet->setCellValue('J1', 'Institution Name');
        $providers_sheet->setCellValue('K1', 'Report Names');
        $row = 2;
        foreach ($providers as $provider) {
            $providers_sheet->getRowDimension($row)->setRowHeight(15);
            $providers_sheet->setCellValue('A' . $row, $provider->global_id);
            $providers_sheet->setCellValue('B' . $row, $provider->inst_id);
            $providers_sheet->setCellValue('C' . $row, $provider->prov_name);
            $_stat = ($provider->is_active) ? "Y" : "N";
            $providers_sheet->setCellValue('D' . $row, $_stat);
            $_restricted = ($provider->restricted) ? "Y" : "N";
            $providers_sheet->setCellValue('E' . $row, $_restricted);
            $_ais = ($provider->allow_inst_specific) ? "Y" : "N";
            $providers_sheet->setCellValue('F' . $row, $_ais);
            $providers_sheet->setCellValue('G' . $row, $provider->day_of_month);
            $_name = ($provider->inst_id == 1) ? "Entire Consortium" : $provider->inst_name;
            $this_prov = $all_providers->where('id', '=', $provider->prov_id)->first();
            if (isset($this_prov->reports)) {
                $_report_ids = "";
                $_report_names = "";
                foreach ($this_prov->reports as $rpt) {
                    $_report_ids .= $rpt->id . ", ";
                    $_report_names .= $rpt->name . ", ";
                }
                $_report_ids = rtrim(trim($_report_ids), ',');
                $_report_names = rtrim(trim($_report_names), ',');
                $providers_sheet->setCellValue('H' . $row, $_report_ids);
                $providers_sheet->setCellValue('K' . $row, $_report_names);
            } else {
                $providers_sheet->setCellValue('H' . $row, 'NULL');
            }
            $providers_sheet->setCellValue('J' . $row, $_name);
            $row++;
        }

        // Auto-size and style the output sheet
        $columns = array('A','B','C','D','E','F','G','H','I','J','K');
        foreach ($columns as $col) {
            $providers_sheet->getColumnDimension($col)->setAutoSize(true);
            $providers_sheet->getStyle($col)->applyFromArray($topleft_style);
        }
        $providers_sheet->getStyle('A1:K1')->applyFromArray($leftbold_style);

        // Give the file a meaningful filename
        if ($thisUser->hasRole('Admin')) {
            $fileName = "CCplus_" . session('ccp_con_key', '') . "_Providers.xlsx";
        } else {
            $fileName = "CCplus_" . preg_replace('/ /', '', $thisUser->institution->name) . "_Providers.xlsx";
        }

        // redirect output to client browser
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    /**
     * Import providers from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Only Admins can import provider data
        abort_unless(auth()->user()->hasRole('Admin'), 403);

        // Handle and validate inputs
        $this->validate($request, ['csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }

        // Get the CSV data
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Get existing global_provider, consortium provider, institution, and reports data
        $global_providers = GlobalProvider::get();
        $providers = Provider::with('reports','institution')->get();
        $institutions = Institution::get();
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)
                                 ->get(['id','name']);

        // Process the input rows
        $cur_prov_id = 0;
        $prov_skipped = 0;
        $prov_updated = 0;
        $prov_created = 0;
        $seen_provs = array();          // keep track of provider already processed while looping
        foreach ($rows as $row) {

            // Ignore bad/missing/invalid Global IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0]) || !is_numeric($row[1]) || sizeof($row) < 7) {
                continue;
            }

            // valid global provider is required
            $global_id = intval($row[0]);
            $global_prov = $global_providers->where("id", $global_id)->first();
            if (!$global_prov) {
                continue;
            }

            // valid institution is required
            $inst_id = intval($row[1]);
            $institution = $institutions->where("id", $inst_id)->first();
            if (!$institution) {
                continue;
            }

            // see if there is a match on existing providers
            $current_prov = $providers->where("global_id", $global_id)->where('inst_id',$inst_id)->first();
            $cur_prov_id = ($current_prov) ? $current_prov->id : null;

            // If we already processed this provider, skip this record
            if ($current_prov && in_array($cur_prov_id, $seen_provs)) {
                $prov_skipped++;
                continue;
            }

            // Update/Add the provider data/settings
            // Check ID and name columns for silliness or errors
            $_name = trim($row[2]);
            if (strlen($_name) < 1) {       // If import-name empty, use global name
                $_name = trim($global_prov->name);
            }

            // Ok - we're gonna save something - Enforce defaults
            $_active = ($row[3] == 'N') ? 0 : 1;
            $_restricted = ($row[4] == 'N') ? 0 : 1;
            $_allow_inst = ($row[5] == 'Y' && $inst_id==1) ? 1 : 0;
            $_day = ($row[6] == '') ? 15 : intval($row[6]);
            if ($_day < 1 || $_day > 28) {
                $_day = 15;
            }

            // Put provider data columns into an array
            $_prov = array('id' => $cur_prov_id, 'global_id' => $global_id, 'inst_id' => $inst_id, 'name' => $_name,
                           'is_active' => $_active, 'restricted' => $_restricted, 'allow_inst_specific' => $_allow_inst,
                           'day_of_month' => $_day);

            // Update or create the Provider record
            if ($current_prov) {      // Update
                $current_prov->update($_prov);
                $prov_updated++;
            } else {                 // Create
                $current_prov = Provider::create($_prov);
                $cur_prov_id = $current_prov->id;
                $prov_created++;
            }

            // Set reports
            $current_prov->reports()->detach();
            $_report_ids = preg_split('/,/', $row[7]);
            if (sizeof($_report_ids) > 0) {
                foreach ($_report_ids as $r) {
                    $r_id = intval(trim($r));
                    if (is_numeric($r_id)) {
                        $report = $master_reports->where('id', '=', $r_id)->first();
                        if ($report) {
                            $current_prov->reports()->attach($r_id);
                        }
                    }
                }
            }
            $seen_provs[] = $cur_prov_id;
        }

        // return the current full list of groups with a success message
        $detail = "";
        $detail .= ($prov_updated > 0) ? $prov_updated . " updated" : "";
        if ($prov_created > 0) {
            $detail .= ($detail != "") ? ", " . $prov_created . " added" : $prov_created . " added";
        }
        if ($prov_skipped > 0) {
            $detail .= ($detail != "") ? ", " . $prov_skipped . " skipped" : $prov_skipped . " skipped";
        }
        $msg  = 'Import successful, Providers : ' . $detail;

        // return response()->json(['result' => true, 'msg' => $msg, 'providers' => $providers]);
        return response()->json(['result' => true, 'msg' => $msg]);
    }

    /**
     * Return an array of booleans for report-state from provider reports columns
     *
     * @param  Array  $reports
     * @param  Collection  $master_reports
     * @return Array  $report-state
     */
    private function reportState($reports, $master_reports) {
        $rpt_state = array();
        foreach ($master_reports as $rpt) {
            $rpt_state[$rpt->name] = (in_array($rpt->id, $reports)) ? true : false;
        }
        return $rpt_state;
    }
}
