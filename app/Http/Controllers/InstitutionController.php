<?php

namespace App\Http\Controllers;

use App\Institution;
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
        $thisUser = auth()->user();
        if ($thisUser->hasRole("Admin")) { // show them all
            $institutions = Institution::with('institutionGroups')->orderBy('name', 'ASC')
                                       ->get(['id','name','is_active']);

            $data = array();
            foreach ($institutions as $inst) {
                $_groups = "";
                foreach ($inst->institutionGroups as $group) {
                    $_groups .= $group->name . ", ";
                }
                $i_data = $inst->toArray();
                $i_data['groups'] = rtrim(trim($_groups), ',');
                $data[] = $i_data;
            }
            $all_groups = InstitutionGroup::orderBy('name', 'ASC')->get(['id','name'])->toArray();

            return view('institutions.index', compact('data', 'all_groups'));
        } else {    // not admin, load the edit view for user's inst
            return redirect()->route('institutions.show', $thisUser->inst_id);
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
        $data = Institution::where('id', $new_id)->get(['id','name','is_active'])->first()->toArray();
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
        $thisUser = auth()->user();
        if (!$thisUser->hasRole("Admin")) {
            abort_unless($thisUser->inst_id == $id, 403);
        }

        // Get the institution and sushi settings
        $institution = Institution::with('users', 'users.roles')->findOrFail($id);
        $sushi_settings = SushiSetting::with('provider')->where('inst_id',$institution->id)->get();

        // Get most recent harvest and set can_delete flag
        $last_harvest = $sushi_settings->max('last_harvest');
        $institution['can_delete'] = ($id > 1 && is_null($last_harvest)) ? true : false;

        // Add user's highest role as "permission" as a separate array
        $users = array();
        foreach ($institution->users as $inst_user) {
            $new_u = $inst_user->toArray();
            $new_u['permission'] = $inst_user->maxRoleName();
            array_push($users, $new_u);
        }
        $_name = array_column($users, "name");
        array_multisort($_name, SORT_ASC, $users);

        // Related models we'll be passing
        $all_groups = InstitutionGroup::orderBy('name', 'ASC')->get(['id','name'])->toArray();
        $institution['groups'] = $institution->institutionGroups()->pluck('institution_group_id')->all();

        // map is_active to 'status' and attach settings to the institution object
        $institution['sushiSettings'] = $sushi_settings->map(function ($setting) {
            $setting['status'] = ($setting->is_active) ? 'Enabled' : 'Disabled';
            return $setting;
        });

        // Roles are limited to current user's max role
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())->orderBy('name', 'ASC')
                         ->get(['name', 'id'])->toArray();

        // Build an array of providers not yet connected to this inst and another that holds
        // the connection_fields used across all providers (whether connected or not)
        $set_provider_ids = $sushi_settings->pluck('prov_id')->values()->toArray();
        $all_providers = Provider::with('connectors')->whereIn('inst_id', [1,$id])
                                 ->orderBy('name', 'ASC')->get(['id','name','inst_id']);
        $unset_providers = array();
        $all_connectors = array();
        $seen_connectors = array();
        foreach ($all_providers as $prov) {
            if (!in_array($prov->id,$set_provider_ids)) {
                $unset_providers[] = array('id' => $prov->id, 'name' => $prov->name,
                                           'connectors' => $prov->connectors->toArray());
            }
            // There are only 4... if they're all set, skip checking
            if (sizeof($seen_connectors) < 4) {
              foreach($prov->connectors as $cnx) {
                  if (!in_array($cnx->name,$seen_connectors)) {
                      $all_connectors[] = array('name' => $cnx->name, 'label' => $cnx->label);
                      $seen_connectors[] = $cnx->name;
                  }
              }
            }
        }

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
            compact('institution', 'users', 'unset_providers', 'all_connectors', 'all_groups', 'all_roles', 'harvests')
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
        $this->validate($request, ['name' => 'required', 'is_active' => 'required']);
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
        // Only Admins can export institution data
        abort_unless(auth()->user()->hasRole('Admin'), 403);

        // Get all institutions
        $institutions = Institution::orderBy('name', 'ASC')->get();

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
        $info_sheet->mergeCells('A1:D7');
        $info_sheet->getStyle('A1:D7')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:D7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Institutions tab represents a starting place for updating or importing settings. The table\n";
        $top_txt .= "below describes the datatype and order that the import expects. Any Import rows without an ID\n";
        $top_txt .= "in column A will be ignored. Missing or invalid invalid values in the other columns, which are\n";
        $top_txt .= "not required, will be set to the 'Default'.\n\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'E' will be ignored.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->mergeCells('B9:D9');
        $info_sheet->getStyle('A9:B9')->applyFromArray($head_style);
        $info_sheet->setCellValue('A9', "NOTE: ");
        $info_sheet->setCellValue('B9', "Institution ID=1 is reserved for system use.");
        $info_sheet->mergeCells('B10:D12');
        $info_sheet->getStyle('B10:D12')->applyFromArray($info_style);
        $info_sheet->getStyle('B10:D12')->getAlignment()->setWrapText(true);
        $note_txt  = "Institution imports cannot be used to delete existing institutions; only additions and updates\n";
        $note_txt .= "are supported. The recommended approach is to add to, or modify, a previously run full export\n";
        $note_txt .= "to ensure that desired end result is achieved.";
        $info_sheet->setCellValue('B10', $note_txt);
        $info_sheet->getStyle('A14:D14')->applyFromArray($head_style);
        $info_sheet->setCellValue('A14', 'Column Name');
        $info_sheet->setCellValue('B14', 'Data Type');
        $info_sheet->setCellValue('C14', 'Description');
        $info_sheet->setCellValue('D14', 'Default');
        $info_sheet->setCellValue('A15', 'Id');
        $info_sheet->setCellValue('B15', 'Integer > 1');
        $info_sheet->setCellValue('C15', 'Unique CC-Plus Institution ID - required');
        $info_sheet->setCellValue('A16', 'Name');
        $info_sheet->setCellValue('B16', 'String');
        $info_sheet->setCellValue('C16', 'Institution Name - required');
        $info_sheet->setCellValue('A17', 'Active');
        $info_sheet->setCellValue('B17', 'String (Y or N)');
        $info_sheet->setCellValue('C17', 'Make the institution active?');
        $info_sheet->setCellValue('D17', 'Y');
        $info_sheet->setCellValue('A18', 'FTE');
        $info_sheet->setCellValue('B18', 'Integer');
        $info_sheet->setCellValue('C18', 'FTE count for the institution');
        $info_sheet->setCellValue('D18', 'NULL');
        $info_sheet->setCellValue('A19', 'Notes');
        $info_sheet->setCellValue('B19', 'Text-blob');
        $info_sheet->setCellValue('C19', 'Notes or other details');
        $info_sheet->setCellValue('D19', 'NULL');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 20; $r++) {
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
        $inst_sheet->setCellValue('D1', 'FTE');
        $inst_sheet->setCellValue('E1', 'Notes');
        $row = 2;
        foreach ($institutions as $inst) {
            $inst_sheet->getRowDimension($row)->setRowHeight(15);
            $inst_sheet->setCellValue('A' . $row, $inst->id);
            $inst_sheet->setCellValue('B' . $row, $inst->name);
            $_stat = ($inst->is_active) ? "Y" : "N";
            $inst_sheet->setCellValue('C' . $row, $_stat);
            $inst_sheet->setCellValue('D' . $row, $inst->fte);
            $inst_sheet->setCellValue('E' . $row, $inst->notes);
            $row++;
        }

        // Auto-size the columns (skip notes in 'G')
        $columns = array('A','B','C','D','E');
        foreach ($columns as $col) {
            $inst_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        $fileName = "CCplus_" . session('ccp_con_key', '') . "_Institutions." . $type;

        // redirect output to client browser
        if ($type == 'xlsx') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        } elseif ($type == 'xls') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
            header('Content-Type: application/vnd.ms-excel');
        }
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
        // Only Admins can import institution data
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

        // Get existing institution and inst-groups data
        $institutions = Institution::get();

        // Process the input rows
        $inst_skipped = 0;
        $inst_updated = 0;
        $inst_created = 0;
        $cur_inst_id = 0;
        $seen_insts = array();          // keep track of institutions seen while looping
        foreach ($rows as $row) {
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0])) {
                continue;
            }
            $cur_inst_id = intval($row[0]);
            if (in_array($cur_inst_id, $seen_insts)) {
              continue;
            }

            // Check ID and name columns for silliness or errors
            $_name = trim($row[1]);
            $current_inst = $institutions->where("id", "=", $cur_inst_id)->first();
            if ($current_inst) {      // found existing ID
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
                $inst_skipped++;
                continue;
            }

            // Enforce defaults and put institution data columns into an array
            $seen_insts[] = $cur_inst_id;
            $_active = ($row[2] == 'N') ? 0 : 1;
            $_fte = ($row[3] == '') ? null : $row[3];
            $_notes = ($row[4] == '') ? null : $row[4];
            $_inst = array('id' => $cur_inst_id, 'name' => $_name, 'is_active' => $_active,  'fte' => $_fte,
                           'notes' => $_notes);

            // Update or create the Institution record
            if (is_null($current_inst)) {      // Create
                $current_inst = Institution::create($_inst);
                $cur_inst_id = $current_inst->id;
                $inst_created++;
            } else {                            // Update
                $current_inst->update($_inst);
                $inst_updated++;
            }
        }

        // Recreate the institutions list (like index does) to be returned to the caller
        $inst_data = array();
        $institutions = Institution::with('institutionGroups')->orderBy('name', 'ASC')
                                   ->get(['id','name','is_active']);
        foreach ($institutions as $inst) {
            $_groups = "";
            foreach ($inst->institutionGroups as $group) {
                $_groups .= $group->name . ", ";
            }
            $i_data = $inst->toArray();
            $i_data['groups'] = rtrim(trim($_groups), ',');
            $inst_data[] = $i_data;
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
        $msg  = 'Import successful, Institutions : ' . $i_msg;

        return response()->json(['result' => true, 'msg' => $msg, 'inst_data' => $inst_data]);
    }
}
