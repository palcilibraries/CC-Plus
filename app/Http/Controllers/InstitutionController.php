<?php

namespace App\Http\Controllers;

use App\Institution;
use App\InstitutionType;
use App\InstitutionGroup;
use App\Provider;
use App\Role;
use App\SushiSetting;
use App\HarvestLog;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class InstitutionController extends Controller
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
        if (auth()->user()->hasRole("Admin")) { // show them all
            $institutions = Institution::with('institutionType', 'institutionGroups')->orderBy('name', 'ASC')
                                       ->get(['id','name','type_id','is_active']);

            $data = array();
            foreach ($institutions as $inst) {
                $_groups = "";
                foreach ($inst->institutionGroups as $group) {
                    $_groups .= $group->name . ", ";
                }
                $i_data = $inst->toArray();
                $i_data['type'] = $inst->institutionType->name;
                $i_data['groups'] = rtrim(trim($_groups), ',');
                $data[] = $i_data;
            }
            $types = InstitutionType::get(['id','name'])->toArray();
            $all_groups = InstitutionGroup::get(['id','name'])->toArray();

            return view('institutions.index', compact('data', 'types', 'all_groups'));
        } else {    // not admin, load the edit view for user's inst
            return redirect()->route('institutions.show', auth()->user()->inst_id);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return redirect()->route('institutions.index');
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
        ]);
        $input = $request->all();
        $institution = Institution::create($input);
        $new_id = $institution->id;

        // Attach groups and build a string of the names
        $_groups = "";
        if (isset($input['institutiongroups'])) {
            foreach ($request->input('institutiongroups') as $g) {
                $institution->institutionGroups()->attach($g);
                $group = InstitutionGroup::where('id', $g)->first();
                $_groups .= ($group) ? $group->name . ", " : "";
            }
        }

        // Setup a return object that matches what index does (above)
        $data = Institution::where('id', $new_id)->get(['id','name','type_id','is_active'])->first()->toArray();
        $data['type'] = $institution->institutionType->name;
        $data['groups'] = rtrim(trim($_groups), ',');

        return response()->json(['result' => true, 'msg' => 'Institution successfully created',
                                 'institution' => $data]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!auth()->user()->hasRole("Admin")) {
            abort_unless(auth()->user()->inst_id == $id, 403);
        }

        // Get the institution and most recent harvest
        $institution = Institution::
                with('institutionType', 'sushiSettings', 'sushiSettings.provider', 'users', 'users.roles')
                ->findOrFail($id);
        $last_harvest = $institution->sushiSettings->max('last_harvest');
        $institution['can_delete'] = ($id > 1 && is_null($last_harvest)) ? true : false;

        // Add user's highest role as "permission" as a separate array
        $users = array();
        foreach ($institution->users as $inst_user) {
            $new_u = $inst_user->toArray();
            $new_u['permission'] = $inst_user->maxRoleName();
            array_push($users, $new_u);
        }

        // Related models we'll be passing
        $types = InstitutionType::get(['id','name'])->toArray();
        $all_groups = InstitutionGroup::get(['id','name'])->toArray();
        $institution['groups'] = $institution->institutionGroups()->pluck('institution_group_id')->all();


        // Roles are limited to current user's max role
        $all_roles = Role::where('id', '<=', auth()->user()->maxRole())->get(['name', 'id'])->toArray();

        // Get id+name pairs for accessible providers without settings
        $set_provider_ids = $institution->sushiSettings->pluck('prov_id');
        $unset_providers = Provider::whereNotIn('id', $set_provider_ids)
                           ->where(function ($query) use ($id) {
                               $query->where('inst_id', 1)->orWhere('inst_id', $id);
                           })
                           ->orderBy('id', 'ASC')->get(['id','name'])->toArray();

        // Get 10 most recent harvests
        $harvests = HarvestLog::with(
            'report:id,name',
            'sushiSetting',
            'sushiSetting.institution:id,name',
            'sushiSetting.provider:id,name'
        )
                              ->join('sushisettings', 'harvestlogs.sushisettings_id', '=', 'sushisettings.id')
                              ->where('sushisettings.inst_id', $id)
                              ->orderBy('harvestlogs.updated_at', 'DESC')->limit(10)
                              ->get('harvestlogs.*')->toArray();

        return view(
            'institutions.show',
            compact('institution', 'users', 'unset_providers', 'types', 'all_groups', 'all_roles', 'harvests')
        );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return redirect()->route('institutions.show', [$id]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $institution = Institution::findOrFail($id);
        if (!$institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

       // Validate form inputs
        $this->validate($request, [
            'name' => 'required',
            'is_active' => 'required',
            'type_id' => 'required',
        ]);
        $input = $request->all();

       // Update the record and assign groups
        $institution->update($input);
        $institution->institutionGroups()->detach();
        if (isset($input['institutiongroups'])) {
            foreach ($request->input('institutiongroups') as $g) {
                $institution->institutionGroups()->attach($g);
            }
        }

        return response()->json(['result' => true, 'msg' => 'Settings successfully updated']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $institution = Institution::findOrFail($id);

        try {
            $institution->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }

        return response()->json(['result' => true, 'msg' => 'Institution successfully deleted']);
    }

    /**
     * Export institution records from the database.
     *
     * @param  string  $type    // 'xls' or 'xlsx'
     */
    public function export($type)
    {
        // Only Admins and Managers can export institution data
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

        // Admins get all institutions; eager load type, groups, and sushi settings
        if (auth()->user()->hasRole("Admin")) {
            $institutions = Institution::with(
                'institutionType',
                'institutionGroups',
                'sushiSettings',
                'sushiSettings.provider:id,name'
            )
                                       ->orderBy('id', 'ASC')->get();
        } else {
            $institutions = Institution::with(
                'institutionType',
                'institutionGroups',
                'sushiSettings',
                'sushiSettings.provider:id,name'
            )
                                       ->where('id', auth()->user()->inst_id)
                                       ->orderBy('id', 'ASC')->get();
        }

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
        $info_sheet->mergeCells('A1:C14');
        $info_sheet->getStyle('A1:C14')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:C14')->getAlignment()->setWrapText(true);
        $top_txt  = "The Institutions tab represents a starting place for updating or importing settings. The table\n";
        $top_txt .= "below describes the datatype and order that the import expects. Any Import rows without an ID\n";
        $top_txt .= "in column 1 will be ignored. If values are missing/invalid within the other columns, but not\n";
        $top_txt .= "required, they will be set to the 'Default'.\n\n";
        $top_txt .= "Institution imports can hold multiple rows per-institution to allow for by-provider SUSHI\n";
        $top_txt .= "settings. The first row seen for a given ID in column (A) will be canonical for columns (B-G),\n";
        $top_txt .= "and will replace any existing values or groups currently defined for the institution.\n";
        $top_txt .= "If Provider-ID is zero or missing, the SUSHI-settings columns (I-L) will be ignored. A full\n";
        $top_txt .= "export of providers, institution types, and institution groups will supply reference values\n";
        $top_txt .= "for the Provider-ID, Type-ID, and Institution-Group-IDs columns.\n\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'L' will be ignored.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->mergeCells('B16:D16');
        $info_sheet->getStyle('A16:B16')->applyFromArray($head_style);
        $info_sheet->setCellValue('A16', "NOTE: ");
        $info_sheet->setCellValue('B16', "Institution ID=1 is reserved for system use.");
        $info_sheet->mergeCells('B17:D19');
        $info_sheet->getStyle('B17:D19')->applyFromArray($info_style);
        $info_sheet->getStyle('B12:D19')->getAlignment()->setWrapText(true);
        $note_txt  = "When performing full-replacement imports, be VERY careful about changing or overwriting\n";
        $note_txt .= "existing ID value(s). The best approach is to add to, or modify, a full export to ensure\n";
        $note_txt .= "that existing institution IDs are not accidently overwritten.";
        $info_sheet->setCellValue('B17', $note_txt);
        $info_sheet->getStyle('A21:D21')->applyFromArray($head_style);
        $info_sheet->setCellValue('A21', 'Column Name');
        $info_sheet->setCellValue('B21', 'Data Type');
        $info_sheet->setCellValue('C21', 'Description');
        $info_sheet->setCellValue('D21', 'Default');
        $info_sheet->setCellValue('A22', 'Id');
        $info_sheet->setCellValue('B22', 'Integer > 1');
        $info_sheet->setCellValue('C22', 'Unique CC-Plus Institution ID - required');
        $info_sheet->setCellValue('A23', 'Name');
        $info_sheet->setCellValue('B23', 'String');
        $info_sheet->setCellValue('C23', 'Institution Name - required');
        $info_sheet->setCellValue('A24', 'Active');
        $info_sheet->setCellValue('B24', 'String (Y or N)');
        $info_sheet->setCellValue('C24', 'Make the institution active?');
        $info_sheet->setCellValue('D24', 'Y');
        $info_sheet->setCellValue('A25', 'Type ID');
        $info_sheet->setCellValue('B25', 'Integer');
        $info_sheet->setCellValue('C25', 'Institution Type ID (see above)');
        $info_sheet->setCellValue('D25', '1 (Not classified)');
        $info_sheet->setCellValue('A26', 'FTE');
        $info_sheet->setCellValue('B26', 'Integer');
        $info_sheet->setCellValue('C26', 'FTE count for the institution');
        $info_sheet->setCellValue('D26', 'NULL');
        $info_sheet->setCellValue('A27', 'Institution Group IDs');
        $info_sheet->setCellValue('B27', 'Integer,Integer,...');
        $info_sheet->setCellValue('C27', 'CSV list of Institution Group IDs (see above)');
        $info_sheet->setCellValue('D27', 'NULL');
        $info_sheet->setCellValue('A28', 'Notes');
        $info_sheet->setCellValue('B28', 'Text-blob');
        $info_sheet->setCellValue('C28', 'Notes or other details');
        $info_sheet->setCellValue('D28', 'NULL');
        $info_sheet->setCellValue('A29', 'Provider ID');
        $info_sheet->setCellValue('B29', 'Integer');
        $info_sheet->setCellValue('C29', 'Unique CC-Plus Provider ID (see above)');
        $info_sheet->setCellValue('D29', 'NULL');
        $info_sheet->setCellValue('A30', 'Customer ID');
        $info_sheet->setCellValue('B30', 'String');
        $info_sheet->setCellValue('C30', 'SUSHI customer ID , provider-specific');
        $info_sheet->setCellValue('D30', 'NULL');
        $info_sheet->setCellValue('A31', 'Requestor ID');
        $info_sheet->setCellValue('B31', 'String');
        $info_sheet->setCellValue('C31', 'SUSHI requestor ID , provider-specific');
        $info_sheet->setCellValue('D31', 'NULL');
        $info_sheet->setCellValue('A32', 'API Key');
        $info_sheet->setCellValue('B32', 'String');
        $info_sheet->setCellValue('C32', 'SUSHI API Key , provider-specific');
        $info_sheet->setCellValue('D32', 'NULL');
        $info_sheet->setCellValue('A33', 'Support Email');
        $info_sheet->setCellValue('B33', 'String');
        $info_sheet->setCellValue('C33', 'Support email address, per-provider');
        $info_sheet->setCellValue('D33', 'NULL');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 35; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the institution data into a new sheet
        $inst_sheet = $spreadsheet->createSheet();
        $inst_sheet->setTitle('Institutions');
        $inst_sheet->setCellValue('A1', 'Id');
        $inst_sheet->setCellValue('B1', 'Name');
        $inst_sheet->setCellValue('C1', 'Active');
        $inst_sheet->setCellValue('D1', 'Type ID');
        $inst_sheet->setCellValue('E1', 'FTE');
        $inst_sheet->setCellValue('F1', 'Group IDs');
        $inst_sheet->setCellValue('G1', 'Notes');
        $inst_sheet->setCellValue('H1', 'Provider ID');
        $inst_sheet->setCellValue('I1', 'Customer ID');
        $inst_sheet->setCellValue('J1', 'Requestor ID');
        $inst_sheet->setCellValue('K1', 'API Key');
        $inst_sheet->setCellValue('L1', 'Support Email');
        $inst_sheet->setCellValue('N1', 'Provider-Name');
        $inst_sheet->setCellValue('O1', 'Inst-Groups');
        $inst_sheet->setCellValue('P1', 'Inst-Type');
        $row = 2;
        foreach ($institutions as $inst) {
            $inst_sheet->getRowDimension($row)->setRowHeight(15);
            $inst_sheet->setCellValue('A' . $row, $inst->id);
            $inst_sheet->setCellValue('B' . $row, $inst->name);
            $_stat = ($inst->is_active) ? "Y" : "N";
            $inst_sheet->setCellValue('C' . $row, $_stat);
            $inst_sheet->setCellValue('D' . $row, $inst->type_id);
            $inst_sheet->setCellValue('P' . $row, $inst->institutionType->name);
            $inst_sheet->setCellValue('E' . $row, $inst->fte);
            if (isset($inst->InstitutionGroups)) {
                $_group_ids = "";
                $_group_names = "";
                foreach ($inst->InstitutionGroups as $grp) {
                    $_group_ids .= $grp->id . ", ";
                    $_group_names .= $grp->name . ", ";
                }
                $_group_ids = rtrim(trim($_group_ids), ',');
                $_group_names = rtrim(trim($_group_names), ',');
                $inst_sheet->setCellValue('F' . $row, $_group_ids);
                $inst_sheet->setCellValue('O' . $row, $_group_names);
            } else {
                $inst_sheet->setCellValue('F' . $row, 'NULL');
            }
            $inst_sheet->setCellValue('G' . $row, $inst->notes);
            if (isset($inst->sushiSettings)) {
                foreach ($inst->sushiSettings as $setting) {
                    $inst_sheet->setCellValue('A' . $row, $inst->id);
                    $inst_sheet->setCellValue('H' . $row, $setting->prov_id);
                    $inst_sheet->setCellValue('I' . $row, $setting->customer_id);
                    $inst_sheet->setCellValue('J' . $row, $setting->requestor_id);
                    $inst_sheet->setCellValue('K' . $row, $setting->API_key);
                    $inst_sheet->setCellValue('L' . $row, $setting->support_email);
                    $inst_sheet->setCellValue('N' . $row, $setting->provider->name);
                    $row++;
                }
            } else {
                $inst_sheet->setCellValue('H' . $row, '0');
                $inst_sheet->setCellValue('I' . $row, 'NULL');
                $inst_sheet->setCellValue('J' . $row, 'NULL');
                $inst_sheet->setCellValue('K' . $row, 'NULL');
                $inst_sheet->setCellValue('L' . $row, 'NULL');
                $row++;
            }
        }

        // Auto-size the columns (skip notes in 'G')
        $columns = array('A','B','C','D','E','F','H','I','J','K','L','M','N','O','P');
        foreach ($columns as $col) {
            $inst_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        if (auth()->user()->hasRole('Admin')) {
            $fileName = "CCplus_" . session('ccp_con_key', '') . "_Institutions." . $type;
        } else {
            $fileName = "CCplus_" . preg_replace('/ /', '', auth()->user()->institution->name) . "_Settings." . $type;
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
     * Import institutions (including sushi-settings) from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Only Admins and Managers can import institution data
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

        // Handle and validate inputs
        $this->validate($request, ['type' => 'required', 'csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }
        $type = $request->input('type');
        if ($type != 'Full Replacement' && $type != 'Add or Update') {
            return response()->json(['result' => false, 'msg' => 'Error - unrecognized import type.']);
        }

        // Get the CSV data
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Get existing institution and inst-groups data
        if (auth()->user()->hasRole('Admin')) {
            $limit_inst = 0;
            $institutions = Institution::with('sushiSettings')->get();
        } else {
            $limit_inst = auth()->user()->inst_id;
            $institutions = Institution::with('sushiSettings')->where('id', '=', $limit_inst)->get();
        }
        $all_groups = InstitutionGroup::get(['id','name']);

        // Process the input rows
        $inst_skipped = 0;
        $inst_updated = 0;
        $inst_created = 0;
        $sushi_skipped = 0;
        $sushi_deleted = 0;
        $sushi_updated = 0;
        $cur_inst_id = 0;
        $seen_insts = array();          // keep track of institutions seen while looping
        $settings_to_keep = array();    // only used for full-replacements
        foreach ($rows as $row) {
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0])) {
                continue;
            }
            $cur_inst_id = $row[0];

            // Disallow managers from modify settings of other insts
            if ($limit_inst > 0 && $cur_inst_id != $limit_inst || $cur_inst_id < 2) {
                $skip_sushi = true;
                $inst_skipped++;
                continue;
            }

            // Update/Add the institution data/settings using the 1st record for an inst_id
            if (!in_array($cur_inst_id, $seen_insts)) {
                $skip_sushi = false;

                // Check ID and name columns for silliness or errors
                $_name = trim($row[1]);
                $current_inst = $institutions->where("id", "=", $cur_inst_id)->first();
                if (!is_null($current_inst)) {      // found existing ID
                    if (strlen($_name) < 1) {       // If import-name empty, use current value
                        $_name = trim($current_inst->name);
                    } else {        // trap changing a name to a name that already exists
                        $existing_inst = $institutions->where("name", "=", $_name)->first();
                        if (!is_null($existing_inst)) {
                            $_name = trim($current_inst->name);     // override, use current - no change
                        }
                    }
                } else {           // existing ID not found, try to find by name
                    $current_inst = $institutions->where("name", "=", $_name)->first();
                    if (!is_null($current_inst)) {
                        $_name = trim($current_inst->name);
                    }
                }

                // Dont store/create anything if name is still empty
                if (strlen($_name) < 1) {
                    $skip_sushi = true;
                    $inst_skipped++;
                    continue;
                }

                // Enforce defaults and put institution data columns into an array
                $seen_insts[] = $cur_inst_id;
                $_active = ($row[2] == 'N') ? 0 : 1;
                $_type = ($row[3] == '') ? 1 : $row[3];
                $_fte = ($row[4] == '') ? null : $row[4];
                $_notes = ($row[6] == '') ? null : $row[6];
                $_inst = array('id' => $cur_inst_id, 'name' => $_name, 'is_active' => $_active, 'type_id' => $_type,
                               'fte' => $_fte, 'notes' => $_notes);

                // Update or create the Institution record
                if (is_null($current_inst)) {      // Create
                    $current_inst = Institution::create($_inst);
                    $cur_inst_id = $current_inst->id;
                    $inst_created++;
                } else {                            // Update
                    $current_inst->update($_inst);
                    $inst_updated++;
                }

                // Set group membership(s)
                $_group_ids = preg_split('/,/', $row[5]);
                $current_inst->institutionGroups()->detach();
                if (sizeof($_group_ids) > 0) {
                    foreach ($_group_ids as $g) {
                        $g_id = trim($g);
                        if (is_numeric($g_id)) {
                            $group = $all_groups->where('id', $g_id)->first();
                            if ($group) {
                                $current_inst->institutionGroups()->attach($g);
                            }
                        }
                    }
                }
            }

            // Handle Sushi Settings (as long as there's no issue from above and prov_id has a value)
            $prov_id = trim($row[7]);
            if (!$skip_sushi && $prov_id > 0 && is_numeric($prov_id)) {
                // Put settings into an array
                $provider = Provider::find($prov_id);

                if (!is_null($provider)) {
                    // Disallow managers assigning credentials for non-consortia providers belonging to another inst
                    if ($limit_inst > 0 && $provider->inst_id != 1 && $provider->inst_id != auth()->user()->inst_id) {
                        $sushi_skipped++;
                        continue;
                    }

                    // Update or create the settings
                    $_args = array('customer_id' => $row[8], 'requestor_id' => $row[9],'API_key' => $row[10],
                                   'support_email' => $row[11]);
                    $new_setting = SushiSetting::
                        updateOrCreate(['inst_id' => $cur_inst_id, 'prov_id' => $prov_id], $_args);

                    // Keep track of settings-IDs to be retained
                    if ($type == 'Full Replacement') {
                        $settings_to_keep[] = $new_setting->id;
                    }
                    $sushi_updated++;
                } else {
                    $sushi_skipped++;
                }
            }
        }

        // Cleanup and return-results are different for Admins and Managers
        // (Admins import from the datatable, managers only from the inst-show component)
        $inst_data = array();
        if ($limit_inst > 0) {
            if (!isset($current_inst)) {
                $msg = "Import failed : No updates to apply - verify IDs in import file.";
                return response()->json(['result' => false, 'msg' => $msg]);
            }

            // If we're doing full-replace, delete sushi settings missing from the import
            if ($type == 'Full Replacement') {
                $sushi_deleted = SushiSetting::where('inst_id', '=', auth()->user()->inst_id)
                                             ->whereNotIn('id', $settings_to_keep)->delete();
            }

            // Add on group-settings
            $inst_data = $current_inst->toArray();
            $inst_data['groups'] = $current_inst->institutionGroups()->pluck('institution_group_id')->all();

        // Admins...
        } else {
            if ($type == 'Full Replacement') {
                $sushi_deleted = SushiSetting::whereNotIn('id', $settings_to_keep)->delete();
            }

            // Recreate the institutions list (like index does) to be returned to the caller
            $institutions = Institution::with('institutionType', 'institutionGroups')->orderBy('name', 'ASC')
                                       ->get(['id','name','type_id','is_active']);
            $inst_data = array();
            foreach ($institutions as $inst) {
                $_groups = "";
                foreach ($inst->institutionGroups as $group) {
                    $_groups .= $group->name . ", ";
                }
                $i_data = $inst->toArray();
                $i_data['type'] = $inst->institutionType->name;
                $i_data['groups'] = rtrim(trim($_groups), ',');
                $inst_data[] = $i_data;
            }
        }

        // return the current full list of groups with a success message
        $i_msg = "";
        $i_msg .= ($inst_updated > 0) ? $inst_updated . " updated" : "";
        if ($inst_created > 0) {
            $i_msg .= ($i_msg != "") ? ", " . $inst_created . " added" : $inst_created . " added";
        }
        if ($inst_skipped > 0) {
            $i_msg .= ($i_msg != "") ? ", " . $inst_skipped . " skipped" : $inst_skipped . " skipped";
        }
        $s_msg = "";
        $s_msg .= ($sushi_updated > 0) ? $sushi_updated . " added or updated" : "";
        if ($sushi_deleted > 0) {
            $s_msg .= ($s_msg != "") ? ", " . $sushi_deleted . " deleted" : $sushi_deleted . " deleted";
        }
        if ($sushi_skipped > 0) {
            $s_msg .= ($s_msg != "") ? ", " . $sushi_skipped . " skipped" : $sushi_skipped . " skipped";
        }
        $msg  = 'Import successful, Institutions : ' . $i_msg;
        $msg .= ($s_msg != "") ? '; Sushi settings : ' . $s_msg : ".";

        return response()->json(['result' => true, 'msg' => $msg, 'inst_data' => $inst_data]);
    }
}
