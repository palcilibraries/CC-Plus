<?php

namespace App\Http\Controllers;

use DB;
use App\Provider;
use App\Institution;
use App\Report;
use App\HarvestLog;
use App\SushiSetting;
use App\ConnectionField;
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
        $consodb = config('database.connections.consodb.database');
       // Admins get list of all providers
        if ($thisUser->hasRole("Admin")) {
            $provider_data = Provider::with('institution:id,name','sushiSettings:id,last_harvest')->orderBy('name','ASC')->get();

       // Otherwise, get all consortia-wide providers and those that match user's inst_id
       // (exclude providers assigned to institutions.)
        } else {
            $provider_data = Provider::with('institution:id,name')->where('inst_id', 1)->orWhere('inst_id', $thisUser->inst_id)
                                 ->orderBy('name','ASC')->get();
        }

        // Map columns to simplify the datatable
        $providers = $provider_data->map( function ($rec) use ($thisUser) {
            $rec->active = ($rec->is_active) ? 'Active' : 'Inactive';
            $rec->inst_name = ($rec->institution->id == 1) ? 'Entire Consortium' : $rec->institution->name;
            $rec->can_delete = false;
            if ($thisUser->hasRole("Admin") || ($thisUser->hasRole("Manager") && $thisUser->inst_id == $rec->inst_id)) {
                $last_harvest = $rec->sushiSettings->max('last_harvest');
                $rec->can_delete = (is_null($last_harvest)) ? true : false;
            }
            return $rec;
        });

       // $institutions depends on whether current user is admin or Manager
        if ($thisUser->hasRole("Admin")) {
            $institutions = Institution::where('id','>',1)->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            array_unshift($institutions,array('id' => 1,'name' => 'Entire Consortium'));
        } else {  // Managers and Users limited their own inst
            $institutions = Institution::where('id', '=', $thisUser->inst_id)->get(['id','name'])->toArray();
        }
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)
                                 ->get(['id','name'])->toArray();
        $default_retries = config('ccplus.max_harvest_retries');

        return view('providers.index', compact('providers', 'institutions', 'master_reports', 'default_retries'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return redirect()->route('providers.index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();
        if ($thisUser->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Create failed (403) - Forbidden']);
        }
        $this->validate($request, [
          'name' => 'required',
          'inst_id' => 'required'
        ]);
        $input = $request->all();
        $provider = Provider::create($input);

        // Attach reports
        if (!is_null($request->input('master_reports'))) {
            foreach ($request->input('master_reports') as $r) {
                $provider->reports()->attach($r);
            }
        }

        // Build return object that matches what index does (above)
        $return_data = array();
        $data = Provider::with('institution:id,name')->where('id', $provider->id)->first();
        if ($data) {
            $return_data = $data->toArray();
            $return_data['active'] = ($data->is_active) ? 'Active' : 'Inactive';
            $return_data['inst_name'] = ($data->inst_id == 1) ? 'Entire Consortium' : $data->institution->name;
            $return_data['can_delete'] = ($thisUser->hasRole("Admin") ||
                                          ($thisUser->hasRole("Manager") && $thisUser->inst_id == $data->inst_id)) ? true : false;
        }

        return response()->json(['result' => true, 'msg' => 'Provider successfully created',
                                 'provider' => $return_data]);
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
        $provider = Provider::with(['reports:reports.id,reports.name','connectors'])->findOrFail($id);

       // Build data to be passed based on whether the user is admin or Manager
        if ($thisUser->hasRole("Admin")) {
            $sushi_settings = SushiSetting::with('institution')->where('prov_id',$provider->id)->get();

            // Get last_harvest for the provider (ALL insts) as determinant for whether it can be deleted
            $last_harvest = $sushi_settings->max('last_harvest');
            $provider['can_delete'] = (is_null($last_harvest)) ? true : false;

            // Make an institutions list
            $institutions = Institution::where('id','>',1)->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            array_unshift($institutions,array('id' => 1,'name' => 'Entire Consortium'));

            // Setup an array of insts without settings for this provider
            $set_inst_ids = $provider->sushiSettings->pluck('inst_id');
            $set_inst_ids[] = 1;
            $unset_institutions = Institution::whereNotIn('id', $set_inst_ids)
                                             ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            $limit_to_insts = array();
        } else {  // Managers/Users are limited their own inst
            $user_inst = $thisUser->inst_id;
            $limit_to_insts = array($user_inst);
            $sushi_settings = SushiSetting::with('institution')
                                          ->where('prov_id',$provider->id)->where('inst_id', $user_inst)->get();
            $last_harvest = $sushi_settings->max('last_harvest');
            $provider['can_delete'] = ($thisUser->inst_id == $provider->inst_id && is_null($last_harvest)) ? true : false;
            $institutions = Institution::where('id', '=', $user_inst)->get(['id','name'])->toArray();
            $unset_institutions = array();
            if (count($provider->sushiSettings) == 0) {
                $unset_institutions[] = Institution::where('id', $user_inst)->first()->toArray();
            }
        }

        // Add on Sushi Settings
        $provider->unsetRelation('sushiSettings');
        $provider['sushiSettings'] = $sushi_settings;

        // get master reports and harvestlog records
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)
                                 ->get(['id','name'])->toArray();

        // Get connection fields
        $connection_fields = ConnectionField::get(['id','name']);
        $all_fields = $connection_fields->toArray();

        // if the provider has NO connectors required, update it to at least require customer_id
        if ($provider->connectors->count() == 0) {
            $cf = $connection_fields->where('name','customer_id')->first();
            if ($cf) {
                $provider->unsetRelation('connectors');
                $provider->connectors()->attach($cf->id);
                $provider->load('connectors');
            }
        }

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
            'all_fields',
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
        $provider = Provider::with(['reports:reports.id,reports.name','connectors'])->findOrFail($id);
        if (!$provider->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $was_active = $provider->is_active;

      // Validate form inputs
        $this->validate($request, [
            'name' => 'required',
            'is_active' => 'required',
            'inst_id' => 'required',
        ]);
        $input = $request->all();
        if (!isset($input['max_retries']) || $input['max_retries'] < 0) {
            $input['max_retries'] = 0;
        }
        $prov_input = array_except($input,array('connectors','master_reports'));

      // Update the record and assign reports in master_reports
        $provider->update($prov_input);
        $provider->reports()->detach();
        if (!is_null($request->input('master_reports'))) {
            foreach ($request->input('master_reports') as $r) {
                $provider->reports()->attach($r);
            }
        }

      // Update the provider_connectors
        $provider->connectors()->detach();
        if (!is_null($request->input('connectors'))) {
            foreach ($request->input('connectors') as $cnx) {
                $provider->connectors()->attach($cnx);
            }
        }
        $provider->load('connectors');

      // If changing from active to inactive, suspend related sushi settings
        if ( $was_active && !$provider->is_active ) {
            SushiSetting::where('prov_id',$provider->id)->where('status','Enabled')
                        ->update(['status' => 'Suspended']);
         }

      // If changing from inactive to active, enable suspended settings where institution is also active
        if ( !$was_active && $provider->is_active ) {
           SushiSetting::join('institutions as Inst', 'sushisettings.inst_id', 'Inst.id')
                       ->where('Inst.is_active', 1)->where('prov_id',$provider->id)
                       ->where('status','Suspended')->update(['status' => 'Enabled']);
        }

      // Return updated provider data
        $provider->load('reports:reports.id,reports.name');
        if ($thisUser->hasRole("Admin")) {
            $settings= SushiSetting::with('institution')->where('prov_id',$provider->id)->get();
        } else {
            $settings = SushiSetting::with('institution')->where('prov_id',$provider->id)->where('inst_id', $user_inst)->get();
        }
        $last_harvest = $settings->max('last_harvest');
        $provider['can_delete'] = (is_null($last_harvest)) ? true : false;
        $provider['sushiSettings'] = $settings->toArray();

        return response()->json(['result' => true, 'msg' => 'Provider settings successfully updated',
                                 'provider' => $provider]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Provider  $provider
     */
    public function destroy($id)
    {
        $provider = Provider::findOrFail($id);
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
     * Export provider records from the database.
     *
     * @param  string  $type    // 'xls' or 'xlsx'
     * @return \Illuminate\Http\Response
     */
    public function export($type)
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
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active','prv.inst_id','server_url_r5',
                             'inst.name as inst_name','day_of_month','max_retries']);
       // Managers get all consortia-wide providers and those that match user's inst_id
       // (excludes providers assigned to institutions.)
        } else {
            $providers = DB::table($consodb . '.providers as prv')
                      ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                      ->where('prv.inst_id', 1)
                      ->orWhere('prv.inst_id', $thisUser->inst_id)
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active','prv.inst_id','server_url_r5',
                             'inst.name as inst_name','day_of_month','max_retries']);
        }

        // Get all providers, with reports
        $all_providers = Provider::with('reports')->get();

        // Setup some styles arrays
        $head_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];
        $info_style = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                           ],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:C7');
        $info_sheet->getStyle('A1:C7')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:C7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Providers tab represents a starting place for updating or importing settings. The table\n";
        $top_txt .= "below describes the datatype and order that the import expects. Any Import rows without an\n";
        $top_txt .= "ID value in column A and a name in column B will be ignored. If values are missing or invalid\n";
        $top_txt .= "for columns (B-H), but not required, they will be set to the 'Default'.\n\n";
        $top_txt .= "Any header row or columns beyond 'H' will be ignored. Once the data sheet contains everything\n";
        $top_txt .= "to be updated or inserted, save the sheet as a CSV and import it into CC-Plus.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A8')->applyFromArray($head_style);
        $info_sheet->setCellValue('A8', "NOTE:");
        $info_sheet->mergeCells('B8:C10');
        $info_sheet->getStyle('B8:C10')->applyFromArray($info_style);
        $info_sheet->getStyle('B8:C10')->getAlignment()->setWrapText(true);
        $note_txt  = "Provider imports cannot be used to delete existing providers; only additions and updates are\n";
        $note_txt .= "supported. The recommended approach is to add to, or modify, a previously run full export\n";
        $note_txt .= "to ensure that desired end result is achieved.";
        $info_sheet->setCellValue('B8', $note_txt);
        $info_sheet->getStyle('A12:D12')->applyFromArray($head_style);
        $info_sheet->setCellValue('A12', 'Column Name');
        $info_sheet->setCellValue('B12', 'Data Type');
        $info_sheet->setCellValue('C12', 'Description');
        $info_sheet->setCellValue('D12', 'Default');
        $info_sheet->setCellValue('A13', 'Id');
        $info_sheet->setCellValue('B13', 'Integer');
        $info_sheet->setCellValue('C13', 'Unique CC-Plus Provider ID - required');
        $info_sheet->setCellValue('A14', 'Name');
        $info_sheet->setCellValue('B14', 'String');
        $info_sheet->setCellValue('C14', 'Provider name - required');
        $info_sheet->setCellValue('A15', 'Active');
        $info_sheet->setCellValue('B15', 'String (Y or N)');
        $info_sheet->setCellValue('C15', 'Make the provider active?');
        $info_sheet->setCellValue('D15', 'Y');
        $info_sheet->setCellValue('A16', 'Server URL');
        $info_sheet->setCellValue('B16', 'String');
        $info_sheet->setCellValue('C16', 'URL for Provider SUSHI service');
        $info_sheet->setCellValue('D16', 'NULL');
        $info_sheet->setCellValue('A17', 'harvest_day');
        $info_sheet->setCellValue('B17', 'Integer');
        $info_sheet->setCellValue('C17', 'Day of the month provider reports are ready (1-28)');
        $info_sheet->setCellValue('D17', '15');
        $info_sheet->setCellValue('A18', 'max_retries');
        $info_sheet->setCellValue('B18', 'Integer');
        $info_sheet->setCellValue('C18', 'The number of times to re-attempt failed harvests');
        $info_sheet->setCellValue('D18', '10');
        $info_sheet->setCellValue('A19', 'Institution ID');
        $info_sheet->setCellValue('B19', 'Integer');
        $info_sheet->setCellValue('C19', 'Institution ID (see above)');
        $info_sheet->setCellValue('D19', '1');
        $info_sheet->setCellValue('A20', 'Master Reports');
        $info_sheet->setCellValue('B20', 'Integer');
        $info_sheet->setCellValue('C20', 'CSV list of Master Report IDs (see above)');
        $info_sheet->setCellValue('D20', 'NULL');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 21; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the provider data into a new sheet
        $providers_sheet = $spreadsheet->createSheet();
        $providers_sheet->setTitle('Providers');
        $providers_sheet->setCellValue('A1', 'Id');
        $providers_sheet->setCellValue('B1', 'Name');
        $providers_sheet->setCellValue('C1', 'Active');
        $providers_sheet->setCellValue('D1', 'Server URL');
        $providers_sheet->setCellValue('E1', 'Harvest Day');
        $providers_sheet->setCellValue('F1', 'Max Retries');
        $providers_sheet->setCellValue('G1', 'Institution ID');
        $providers_sheet->setCellValue('H1', 'Master Reports');
        $providers_sheet->setCellValue('J1', 'Institution Name');
        $providers_sheet->setCellValue('K1', 'Report Names');
        $row = 2;
        foreach ($providers as $provider) {
            $providers_sheet->getRowDimension($row)->setRowHeight(15);
            $providers_sheet->setCellValue('A' . $row, $provider->prov_id);
            $providers_sheet->setCellValue('B' . $row, $provider->prov_name);
            $_stat = ($provider->is_active) ? "Y" : "N";
            $providers_sheet->setCellValue('C' . $row, $_stat);
            $providers_sheet->setCellValue('D' . $row, $provider->server_url_r5);
            $providers_sheet->setCellValue('E' . $row, $provider->day_of_month);
            $providers_sheet->setCellValue('F' . $row, $provider->max_retries);
            $providers_sheet->setCellValue('G' . $row, $provider->inst_id);
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

        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I','J','K');
        foreach ($columns as $col) {
            $providers_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        if ($thisUser->hasRole('Admin')) {
            $fileName = "CCplus_" . session('ccp_con_key', '') . "_Providers." . $type;
        } else {
            $fileName = "CCplus_" . preg_replace('/ /', '', $thisUser->institution->name) . "_Providers." . $type;
        }

        // redirect output to client browser
        if ($type == 'xlsx') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // } elseif ($type == 'xls') {
        //     $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        //     header('Content-Type: application/vnd.ms-excel');
        }
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

        // Get existing provider, institution, and reports data
        $providers = Provider::with('reports')->get();
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
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0]) || sizeof($row) < 7) {
                continue;
            }
            $cur_prov_id = intval($row[0]);
            if (in_array($cur_prov_id, $seen_provs)) {
              continue;
            }

            // Update/Add the provider data/settings
            // Check ID and name columns for silliness or errors
            $_name = trim($row[1]);
            $current_prov = $providers->where("id", "=", $cur_prov_id)->first();
            if (!is_null($current_prov)) {      // found existing ID
                if (strlen($_name) < 1) {       // If import-name empty, use current value
                    $_name = trim($current_prov->name);
                } else {                        // trap changing a name to a name that already exists
                    $existing_prov = $providers->where("name", "=", $_name)->first();
                    if (!is_null($existing_prov)) {
                        $_name = trim($current_prov->name);     // override, use current - no change
                    }
                }
            } else {        // existing ID not found, try to find by name
                $current_prov = $providers->where("name", "=", $_name)->first();
                if (!is_null($current_prov)) {
                    $_name = trim($current_prov->name);
                }
            }

            // Dont store/create anything if name is still empty
            if (strlen($_name) < 1) {
                $prov_skipped++;
                continue;
            }

            // Confirm that the import::institution_id exists; otherwise, skip it
            if ($row[6] == '') {
                $_inst = 1;
            } else {
                $_inst = intval($row[6]);
                $prov_inst = $institutions->where('id', $_inst)->first();
                if (!$prov_inst) {
                    $prov_skipped++;
                    continue;
                }
            }

            // Enforce defaults
            $seen_provs[] = $cur_prov_id;
            $_active = ($row[2] == 'N') ? 0 : 1;
            $_url = ($row[3] == '') ? null : $row[3];
            $_day = ($row[4] == '') ? 15 : intval($row[4]);
            if ($_day < 1 || $_day > 28) {
                $_day = 15;
            }
            $_retries = ($row[5] == '') ? 10 : intval($row[5]);

            // Put provider data columns into an array
            $_prov = array('id' => $cur_prov_id, 'name' => $_name, 'is_active' => $_active, 'server_url_r5' => $_url,
                           'day_of_month' => $_day, 'max_retries' => $_retries, 'inst_id' => $_inst);

            // Update or create the Provider record
            if (is_null($current_prov)) {      // Create
                $_prov['max_retries'] = config('ccplus.max_harvest_retries');
                $current_prov = Provider::create($_prov);
                $cur_prov_id = $current_prov->id;
                $prov_created++;
            } else {                            // Update
                $current_prov->update($_prov);
                $prov_updated++;
            }

            // Set reports
            $current_prov->reports()->detach();
            $_report_ids = preg_split('/,/', $row[6]);
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
        }

        // Recreate the institutions list (like index does) to be returned to the caller
        $consodb = config('database.connections.consodb.database');
        $providers = DB::table($consodb . '.providers as prv')
                       ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                       ->orderBy('prov_name', 'ASC')
                       ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                              'prv.inst_id','inst.name as inst_name','day_of_month','max_retries']);

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

        return response()->json(['result' => true, 'msg' => $msg, 'providers' => $providers]);
    }
}
