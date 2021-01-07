<?php

namespace App\Http\Controllers;

use DB;
use App\Provider;
use App\Institution;
use App\Report;
use App\HarvestLog;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class ProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        $consodb = config('database.connections.consodb.database');
       // Admins get list of all providers
        if ($thisUser->hasRole("Admin")) {
            $providers = DB::table($consodb . '.providers as prv')
                      ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                             'prv.inst_id','inst.name as inst_name','day_of_month']);
       // Otherwise, get all consortia-wide providers and those that match user's inst_id
       // (exclude providers assigned to institutions.)
        } else {
            $providers = DB::table($consodb . '.providers as prv')
                      ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                      ->where('prv.inst_id', 1)
                      ->orWhere('prv.inst_id', $thisUser->inst_id)
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                             'prv.inst_id','inst.name as inst_name','day_of_month']);
        }

       // $institutions depends on whether current user is admin or Manager
        if ($thisUser->hasRole("Admin")) {
            $institutions = Institution::where('id','>',1)->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            array_unshift($institutions,array('id' => 1,'name' => 'Entire Consortium'));
        } else {  // Managers and Users limited their own inst
            $institutions = Institution::where('id', '=', $thisUser->inst_id)->get(['id','name'])->toArray();
        }
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)
                                 ->get(['id','name'])->toArray();

        return view('providers.index', compact('providers', 'institutions', 'master_reports'));
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
        if (!auth()->user()->hasRole("Admin")) {
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
        $consodb = config('database.connections.consodb.database');
        $data = DB::table($consodb . '.providers as prv')
                  ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                  ->where('prv.id', $provider->id)
                  ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                         'prv.inst_id','inst.name as inst_name','day_of_month'])
                  ->first();

        return response()->json(['result' => true, 'msg' => 'Provider successfully created',
                                 'provider' => $data]);
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

       // Build data to be passed based on whether the user is admin or Manager
        if ($thisUser->hasRole("Admin")) {
            $provider = Provider::with(['reports:reports.id,reports.name','sushiSettings','sushiSettings.institution'])
                                ->findOrFail($id);

            // Get last_harvest for the provider (ALL insts) as determinant for whether it can be deleted
            $last_harvest = $provider->sushiSettings->max('last_harvest');
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
            $provider = Provider::with(['reports:reports.id,reports.name',
                                        'sushiSettings' => function ($query) use ($user_inst) {
                                            $query->where('inst_id', '=', $user_inst);
                                        },
                                        'sushiSettings.institution'])->findOrFail($id);
            $provider['can_delete'] = false;
            $institutions = Institution::where('id', '=', $user_inst)->get(['id','name'])->toArray();
            $unset_institutions = array();
            if (count($provider->sushiSettings) == 0) {
                $unset_institutions[] = Institution::where('id', $user_inst)->first()->toArray();
            }
        }
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)
                                 ->get(['id','name'])->toArray();

        // Get 10 most recent harvests
        $harvests = HarvestLog::with(
            'report:id,name',
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
        $provider = Provider::findOrFail($id);
        if (!$provider->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

      // Validate form inputs
        $this->validate($request, [
            'name' => 'required',
            'is_active' => 'required',
            'inst_id' => 'required',
        ]);
        $input = $request->all();

      // Update the record and assign reports in master_reports
        $provider->update($input);
        $provider->reports()->detach();
        if (!is_null($request->input('master_reports'))) {
            foreach ($request->input('master_reports') as $r) {
                $provider->reports()->attach($r);
            }
        }

        $provider->load('reports:reports.id,reports.name');
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
                             'inst.name as inst_name','day_of_month']);
       // Managers get all consortia-wide providers and those that match user's inst_id
       // (excludes providers assigned to institutions.)
        } else {
            $providers = DB::table($consodb . '.providers as prv')
                      ->join($consodb . '.institutions as inst', 'inst.id', '=', 'prv.inst_id')
                      ->where('prv.inst_id', 1)
                      ->orWhere('prv.inst_id', $thisUser->inst_id)
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active','prv.inst_id','server_url_r5',
                             'inst.name as inst_name','day_of_month']);
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
        $info_sheet->mergeCells('A1:C8');
        $info_sheet->getStyle('A1:C8')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:C8')->getAlignment()->setWrapText(true);
        $top_txt  = "The Providers tab represents a starting place for updating or importing settings. The table\n";
        $top_txt .= "below describes the datatype and order that the import expects. Any Import rows without an\n";
        $top_txt .= "ID value in column 1 and a name in column 2 will be ignored. If values are missing/invalid\n";
        $top_txt .= "for columns (B-G), but not required, they will be set to the 'Default'.\n\n";
        $top_txt .= "Only Admins can use the CC-Plus import for adding or updating providers and their settings.\n";
        $top_txt .= "Any header row or columns beyond 'G' will be ignored. Once the data sheet contains everything\n";
        $top_txt .= "to be updated or inserted, save the sheet as a CSV and import it into CC-Plus.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A10')->applyFromArray($head_style);
        $info_sheet->setCellValue('A10', "NOTE:");
        $info_sheet->mergeCells('B10:C12');
        $info_sheet->getStyle('B10:C12')->applyFromArray($info_style);
        $info_sheet->getStyle('B10:C12')->getAlignment()->setWrapText(true);
        $note_txt  = "When performing full-replacement imports, be VERY careful about modifying\n";
        $note_txt .= "existing ID value(s). The best approach is to add to, or modify, a full export\n";
        $note_txt .= "to ensure that existing provider IDs are not accidently overwritten.";
        $info_sheet->setCellValue('B10', $note_txt);
        $info_sheet->getStyle('A14:D14')->applyFromArray($head_style);
        $info_sheet->setCellValue('A14', 'Column Name');
        $info_sheet->setCellValue('B14', 'Data Type');
        $info_sheet->setCellValue('C14', 'Description');
        $info_sheet->setCellValue('D14', 'Default');
        $info_sheet->setCellValue('A15', 'Id');
        $info_sheet->setCellValue('B15', 'Integer');
        $info_sheet->setCellValue('C15', 'Unique CC-Plus Provider ID - required');
        $info_sheet->setCellValue('A16', 'Name');
        $info_sheet->setCellValue('B16', 'String');
        $info_sheet->setCellValue('C16', 'Provider name - required');
        $info_sheet->setCellValue('A17', 'Active');
        $info_sheet->setCellValue('B17', 'String (Y or N)');
        $info_sheet->setCellValue('C17', 'Make the provider active?');
        $info_sheet->setCellValue('D17', 'Y');
        $info_sheet->setCellValue('A18', 'Server Url');
        $info_sheet->setCellValue('B18', 'String');
        $info_sheet->setCellValue('C18', 'URL for Provider SUSHI service');
        $info_sheet->setCellValue('D18', 'NULL');
        $info_sheet->setCellValue('A19', 'harvest_day');
        $info_sheet->setCellValue('B19', 'Integer');
        $info_sheet->setCellValue('C19', 'Day of the month provider reports are ready (1-28)');
        $info_sheet->setCellValue('D19', '15');
        $info_sheet->setCellValue('A20', 'Institution ID');
        $info_sheet->setCellValue('B20', 'Integer');
        $info_sheet->setCellValue('C20', 'Institution ID (see above)');
        $info_sheet->setCellValue('D20', '1');
        $info_sheet->setCellValue('A21', 'Master Reports');
        $info_sheet->setCellValue('B21', 'Integer');
        $info_sheet->setCellValue('C21', 'CSV list of Master Report IDs (see above)');
        $info_sheet->setCellValue('D21', 'NULL');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 22; $r++) {
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
        $providers_sheet->setCellValue('F1', 'Institution ID');
        $providers_sheet->setCellValue('G1', 'Master Reports');
        $providers_sheet->setCellValue('I1', 'Institution Name');
        $providers_sheet->setCellValue('J1', 'Report Names');
        $row = 2;
        foreach ($providers as $provider) {
            $providers_sheet->getRowDimension($row)->setRowHeight(15);
            $providers_sheet->setCellValue('A' . $row, $provider->prov_id);
            $providers_sheet->setCellValue('B' . $row, $provider->prov_name);
            $_stat = ($provider->is_active) ? "Y" : "N";
            $providers_sheet->setCellValue('C' . $row, $_stat);
            $providers_sheet->setCellValue('D' . $row, $provider->server_url_r5);
            $providers_sheet->setCellValue('E' . $row, $provider->day_of_month);
            $providers_sheet->setCellValue('F' . $row, $provider->inst_id);
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
                $providers_sheet->setCellValue('G' . $row, $_report_ids);
                $providers_sheet->setCellValue('J' . $row, $_report_names);
            } else {
                $providers_sheet->setCellValue('G' . $row, 'NULL');
            }
            $providers_sheet->setCellValue('I' . $row, $_name);
            $row++;
        }

        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I','J');
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
        } elseif ($type == 'xls') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        }
        header('Content-Type: application/vnd.ms-excel');
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
        // Only Admins can import institution data
        abort_unless(auth()->user()->hasAnyRole(['Admin']), 403);

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
        $reports_to_keep = array();    // only used for full-replacements
        foreach ($rows as $row) {
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0]) || sizeof($row) < 7) {
                continue;
            }
            $cur_prov_id = $row[0];

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
            if ($row[5] == '') {
                $_inst = 1;
            } else {
                $_inst = $row[5];
                $prov_inst = $institutions->where('id', $_inst)->first();
                if (!$prov_inst) {
                    $prov_skipped++;
                    continue;
                }
            }

            // Enforce defaults
            $_active = ($row[2] == 'N') ? 0 : 1;
            $_url = ($row[3] == '') ? null : $row[3];
            $_day = ($row[4] == '') ? 15 : $row[4];
            if (!is_numeric($_day) || $_day < 1 || $_day > 28) {
                $_day = 15;
            }

            // Put provider data columns into an array
            $_prov = array('id' => $cur_prov_id, 'name' => $_name, 'is_active' => $_active, 'server_url_r5' => $_url,
                           'day_of_month' => $_day, 'inst_id' => $_inst);

            // Update or create the Provider record
            if (is_null($current_prov)) {      // Create
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
                    $r_id = trim($r);
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
                              'prv.inst_id','inst.name as inst_name','day_of_month']);

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
