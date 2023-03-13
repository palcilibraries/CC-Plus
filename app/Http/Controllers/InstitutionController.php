<?php

namespace App\Http\Controllers;

use App\Institution;
use App\InstitutionGroup;
use App\Provider;
use App\Role;
use App\SushiSetting;
use App\HarvestLog;
use App\GlobalProvider;
use App\ConnectionField;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\Writer\Xls;

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
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        $json = ($request->input('json')) ? true : false;

        // Assign optional inputs to $filters array
        $filters = array('stat' => 'ALL', 'groups' => []);
        if ($request->input('filters')) {
            $filter_data = json_decode($request->input('filters'));
            foreach ($filter_data as $key => $val) {
                if ($key == 'stat' && (is_null($val) || $val == '')) continue;
                if ($key == 'groups' && sizeof($val) == 0) continue;
                $filters[$key] = $val;
            }
        } else {
            $keys = array_keys($filters);
            foreach ($keys as $key) {
                if ($request->input($key)) {
                    if ($key == 'stat') {
                        if (is_null($request->input('stat')) || $request->input('stat') == '') continue;
                    } else if ($key == 'groups') {
                        if (sizeof($request->input('groups')) == 0) continue;
                    }
                    $filters[$key] = $request->input($key);
                }
            }
        }
        // Get all groups regardless of JSON or not
        $all_groups = InstitutionGroup::with('institutions:id')->orderBy('name', 'ASC')->get(['id','name']);

        // Skip querying for records unless we're returning json
        // The vue-component will run a request for initial data once it is mounted
        if ($json) {

            // Prep variables for use in querying
            $filter_stat = null;
            if ($filters['stat'] != 'ALL'){
                $filter_stat = ($filters['stat'] == 'Active') ? 1 : 0;
            }

            // Handle filter-by-group by limiting to specific inst_ids
            $limit_to_insts = array();
            if (sizeof($filters['groups']) > 0) {
                foreach ($filters['groups'] as $group_id) {
                    $group = $all_groups->where('id',$group_id)->first();
                    if ($group) {
                        $_insts = $group->institutions->pluck('id')->toArray();
                        $limit_to_insts =  array_merge(
                              array_intersect($limit_to_insts, $_insts),
                              array_diff($limit_to_insts, $_insts),
                              array_diff($_insts, $limit_to_insts)
                        );
                    }
                }
            }

            // Get institution records
            $inst_data = Institution::with('institutionGroups:id,name','sushiSettings')
                                       ->when(!is_null($filter_stat), function ($qry) use ($filter_stat) {
                                           return $qry->where('is_active', $filter_stat);
                                       })
                                       ->when(sizeof($limit_to_insts) > 0 , function ($qry) use ($limit_to_insts) {
                                           return $qry->whereIn('id', $limit_to_insts);
                                       })
                                       ->orderBy('name', 'ASC')
                                       ->get(['id','name','local_id','is_active']);

            // Add group memberships and status as strings
            $institutions = $inst_data->map( function($inst) {
                $inst->groups = "";
                foreach ($inst->institutionGroups as $group) {
                    $inst->groups .= ($inst->groups == "") ? "" : ", ";
                    $inst->groups .= $group->name;
                }
                $inst->status = ($inst->is_active) ? 'Active' : 'Inactive';
                $harvest_count = $inst->sushiSettings->whereNotNull('last_harvest')->count();
                $inst->can_delete = ($harvest_count > 0 || $inst->id == 1) ? false : true;
                return $inst;
            });
            return response()->json(['institutions' => $institutions], 200);

        // Not returning JSON, pass only what the index/vue-component needs to initialize the page
        } else {
          return view('institutions.index', compact('all_groups', 'filters'));
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
        // Make sure that local ID is unique if not set null
        $localID = (isset($input['local_id'])) ? trim($input['local_id']) : null;
        if ($localID && strlen($localID) > 0) {
            $existing_inst = Institution::where('local_id',$localID)->first();
            if ($existing_inst) {
                return response()->json(['result' => false,
                                         'msg' => 'Local ID already assigned to another institution.']);
            }
            $input['local_id'] = $localID;
        } else {
            $input['local_id'] = null;
        }
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
        $sushi_settings = SushiSetting::with('provider','provider.globalProv')->where('inst_id',$institution->id)->get();

        // Get most recent harvest and set can_delete flag
        $last_harvest = $sushi_settings->max('last_harvest');
        $institution['can_delete'] = ($id > 1 && is_null($last_harvest)) ? true : false;

        // Get institution users and map in max-role as "permission"
        $users = array();
        foreach ($institution->users as $_u) {
            $new_u = $_u->toArray();
            $max_role = $_u->maxRoleName();
            if ($max_role == "Admin") $max_role = "Consortium Admin";
            if ($max_role == "Manager") $max_role = "Local Admin";
            if ($max_role == "Viewer") $max_role = "Consortium Viewer";
            $new_u['permission'] = $max_role;
            $users[] = $new_u;
        }
        $_name = array_column($users, "name");
        array_multisort($_name, SORT_ASC, $users);

        // Related models we'll be passing
        $all_groups = InstitutionGroup::orderBy('name', 'ASC')->get(['id','name'])->toArray();
        $institution['groups'] = $institution->institutionGroups()->pluck('institution_group_id')->all();

        // Add on Sushi Settings
        $institution['sushiSettings'] = $sushi_settings;

        // Roles are limited to current user's max role
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())->orderBy('id', 'DESC')
                         ->get(['name', 'id'])->toArray();
        foreach ($all_roles as $idx => $r) {
            if ($r['name'] == 'Manager') $all_roles[$idx]['name'] = "Local Admin";
            if ($r['name'] == 'Admin') $all_roles[$idx]['name'] = "Consortium Admin";
            if ($r['name'] == 'Viewer') $all_roles[$idx]['name'] = "Viewer";
        }

        // Get all connectors and initialize the connectors array
        $fields = ConnectionField::get();
        $all_connnectors = array();
        foreach ($fields as $field) {
            $key = trim($field->name);
            $all_connectors[$key] = array('id'=>$field->id, 'name'=>$field->name, 'label'=>$field->label, 'active'=>false);
        }
        // Build arrays of providers not yet connected to this inst and set the flag in connectors
        // to mark connectors in use by connected providers
        $set_provider_ids = $sushi_settings->pluck('prov_id')->values()->toArray();
        $all_providers = Provider::with('globalProv')->whereIn('inst_id', [1,$id])->orderBy('name', 'ASC')->get();
        $unset_conso_providers = array();
        foreach ($all_providers as $prov) {
            // Pull the providers' connection fields
            $connectors = $fields->whereIn('id', $prov->globalProv->connectors);

            // Flag active connectors for providers already connected
            if (in_array($prov->id,$set_provider_ids)) {
                foreach($connectors as $cnx) {
                    $key = trim($cnx->name);
                    if (!$all_connectors[$key]['active']) $all_connectors[$key]['active'] = true;
                }
            // Un-connected providers and their connectors go into the unset array
            } else {
                $unset_conso_providers[] = array('id' => $prov->id, 'name' => $prov->name,
                                                 'connectors' => $connectors->values()->toArray());
            }
        }

        // Build list of global providers not already added to the consortium
        // These are eligible to be added as institution-specific providers
        $conso_global_ids = $all_providers->pluck('global_id')->toArray();
        $global_providers = GlobalProvider::whereNotIn('id', $conso_global_ids)->orderBy('name', 'ASC')->get();
        $unset_global_providers = array();
        foreach ($global_providers as $gp) {
            $unset = $gp->toArray();
            // replace array of IDs with full connection fields for U/I use
            $unset['connectors'] = ConnectionField::whereIn('id',$gp->connectors)->get()->values()->toArray();
            $unset_global_providers[] = $unset;
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
            compact('institution', 'users', 'unset_conso_providers', 'unset_global_providers', 'all_connectors',
                    'all_groups', 'all_roles', 'harvests')
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
        $thisUser = auth()->user();
        $institution = Institution::findOrFail($id);
        if (!$institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $was_active = $institution->is_active;

       // Validate form inputs
        $this->validate($request, ['name' => 'required', 'is_active' => 'required']);
        $input = $request->all();

       // Make sure that local ID is unique if not set null
        $newID = (isset($input['local_id'])) ? trim($input['local_id']) : null;
        if ($institution->local_id != $newID) {
            if (strlen($newID) > 0) {
                $existing_inst = Institution::where('local_id',$newID)->first();
                if ($existing_inst) {
                    return response()->json(['result' => false,
                                             'msg' => 'Local ID already assigned to another institution.']);
                }
                $input['local_id'] = $newID;
            } else {
                $input['local_id'] = null;
            }
        }

       // if not admin (user is a local-admin for their institution), only update FTE and notes
        if (!$thisUser->hasRole("Admin")) {
            $limitedFields = array('fte' => $input['fte'], 'notes' => $input['notes']);
            $institution->update($limitedFields);

       // Admins update everything from $input
        } else {
            // Update the record and assign groups
             $institution->update($input);
             if (isset($input['institutiongroups'])) {
                 $institution->institutionGroups()->detach();
                 foreach ($request->input('institutiongroups') as $g) {
                     $institution->institutionGroups()->attach($g);
                 }
             }

             // If changing from active to inactive, suspend related sushi settings
              $settings = array();
              if ( $was_active && !$institution->is_active ) {
                  SushiSetting::where('inst_id',$institution->id)->where('status','Enabled')
                              ->update(['status' => 'Suspended']);
              }

             // If changing from inactive to active, enable suspended settings where institution is also active
              if ( !$was_active && $institution->is_active ) {
                 SushiSetting::join('providers as Prov', 'sushisettings.prov_id', 'Prov.id')
                             ->where('Prov.is_active', 1)->where('sushisettings.inst_id',$institution->id)
                             ->where('status','Suspended')->update(['status' => 'Enabled']);
              }

             // Return updated institution data
              $settings = SushiSetting::with('provider')->where('inst_id',$institution->id)->get();
              $harvest_count = $settings->whereNotNull('last_harvest')->count();
              $institution['can_delete'] = ($harvest_count > 0 || $institution->id == 1) ? false : true;
              $institution['sushiSettings'] = $settings->toArray();
        }

         return response()->json(['result' => true, 'msg' => 'Settings successfully updated',
                                  'institution' => $institution]);
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
        $centered_style = [
          'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E7');
        $info_sheet->getStyle('A1:E7')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:E7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Institutions tab represents a starting place for updating or importing settings.\n";
        $top_txt .= "The table below describes the datatype and order that the import process requires.\n\n";
        $top_txt .= "Any Import rows without a CC+ System ID in column-A or a Local ID in column-B will be ignored.\n";
        $top_txt .= "Imports row with a new ID in column-A or a new Local ID in column-B are added as new institutions.\n";
        $top_txt .= "Missing or invalid, but not required, values in the other columns will be set to the 'Default'.\n\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'F' will be ignored.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->setCellValue('A8', "NOTES: ");
        $info_sheet->mergeCells('B8:E9');
        $info_sheet->getStyle('A8:B9')->applyFromArray($head_style);
        $info_sheet->getStyle('A8:B9')->getAlignment()->setWrapText(true);
        $precedence_note  = "CC+ System ID values (A) take precedence over Local ID values (B) when processing import";
        $precedence_note .= " records. If a match is found for column-A, all other values in the row are treated as";
        $precedence_note .= " updates. CC+ System ID=1 is reserved for system use.";
        $info_sheet->setCellValue('B8', $precedence_note);
        $info_sheet->mergeCells('B10:E12');
        $info_sheet->getStyle('B10:E12')->applyFromArray($info_style);
        $info_sheet->getStyle('B10:E12')->getAlignment()->setWrapText(true);
        $note_txt  = "Institution imports cannot be used to delete existing institutions; only additions and";
        $note_txt .= " updates are supported. The recommended approach is to add to, or modify, a previously";
        $note_txt .= " generated full export to ensure that desired end result is achieved.";
        $info_sheet->setCellValue('B10', $note_txt);
        $info_sheet->getStyle('A14:E14')->applyFromArray($head_style);
        $info_sheet->setCellValue('A14', 'Column Name');
        $info_sheet->setCellValue('B14', 'Data Type');
        $info_sheet->setCellValue('C14', 'Description');
        $info_sheet->setCellValue('D14', 'Required');
        $info_sheet->setCellValue('E14', 'Default');
        $info_sheet->setCellValue('A15', 'CC+ System ID');
        $info_sheet->setCellValue('B15', 'Integer > 1');
        $info_sheet->setCellValue('C15', 'Institution ID (CC+ System ID)');
        $info_sheet->setCellValue('D15', 'Yes - If LocalID not given');
        $info_sheet->setCellValue('A16', 'LocalID');
        $info_sheet->setCellValue('B16', 'String');
        $info_sheet->setCellValue('C16', 'Local institution identifier');
        $info_sheet->setCellValue('D16', 'Yes - If CC+ System ID not given');
        $info_sheet->setCellValue('A17', 'Name');
        $info_sheet->setCellValue('B17', 'String');
        $info_sheet->setCellValue('C17', 'Institution Name - required');
        $info_sheet->setCellValue('D17', 'Yes');
        $info_sheet->setCellValue('A18', 'Active');
        $info_sheet->setCellValue('B18', 'String (Y or N)');
        $info_sheet->setCellValue('C18', 'Make the institution active?');
        $info_sheet->setCellValue('D18', 'No');
        $info_sheet->setCellValue('E18', 'Y');
        $info_sheet->setCellValue('A19', 'FTE');
        $info_sheet->setCellValue('B19', 'Integer');
        $info_sheet->setCellValue('C19', 'FTE count for the institution');
        $info_sheet->setCellValue('D19', 'No');
        $info_sheet->setCellValue('E19', 'NULL');
        $info_sheet->setCellValue('A20', 'Notes');
        $info_sheet->setCellValue('B20', 'Text-blob');
        $info_sheet->setCellValue('C20', 'Notes or other details');
        $info_sheet->setCellValue('D20', 'No');
        $info_sheet->setCellValue('E20', 'NULL');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 20; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the institution data into a new sheet
        $inst_sheet = $spreadsheet->createSheet();
        $inst_sheet->setTitle('Institutions');
        $inst_sheet->setCellValue('A1', 'CC+ System ID');
        $inst_sheet->setCellValue('B1', 'Local ID');
        $inst_sheet->setCellValue('C1', 'Name');
        $inst_sheet->setCellValue('D1', 'Active');
        $inst_sheet->setCellValue('E1', 'FTE');
        $inst_sheet->setCellValue('F1', 'Notes');
        $row = 2;

        // Align column-D for the data sheet on center
        $active_column_cells = "D2:D" . strval($institutions->count()+1);
        $inst_sheet->getStyle($active_column_cells)->applyFromArray($centered_style);

        // Process all institutions, 1-per-row
        foreach ($institutions as $inst) {
            $inst_sheet->getRowDimension($row)->setRowHeight(15);
            $inst_sheet->setCellValue('A' . $row, $inst->id);
            $inst_sheet->setCellValue('B' . $row, $inst->local_id);
            $inst_sheet->setCellValue('C' . $row, $inst->name);
            $_stat = ($inst->is_active) ? "Y" : "N";
            $inst_sheet->setCellValue('D' . $row, $_stat);
            $inst_sheet->setCellValue('E' . $row, $inst->fte);
            $inst_sheet->setCellValue('F' . $row, $inst->notes);
            $row++;
        }

        // Auto-size the columns (skip notes in 'G')
        $columns = array('A','B','C','D','E','F');
        foreach ($columns as $col) {
            $inst_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        $fileName = "CCplus_" . session('ccp_con_key', '') . "_Institutions." . $type;

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
        $seen_insts = array();          // keep track of institutions seen while looping
        foreach ($rows as $rowNum => $row) {
            // Ignore header row and rows with bad/missing/invalid IDs
            if ($rowNum == 0 || !isset($row[0]) && !isset($row[1])) continue;

            // Look for a matching existing institution based on ID or local-ID
            $current_inst = null;
            $cur_inst_id = (isset($row[0])) ? strval(trim($row[0])) : null;
            $localID = (strlen(trim($row[1])) > 0) ? trim($row[1]) : null;

            // empty/missing/invalid ID and no localID?  skip the row
            if (!$localID && ($row[0] == "" || !is_numeric($row[0]))) {
                $inst_skipped++;
                continue;
            }

            // Set current_inst based on system-ID (column-0) or local-ID in column-1
            // column-0 takes precedence over column-1
            if ($cur_inst_id) {
                $current_inst = $institutions->where("id", $cur_inst_id)->first();
            }
            if (!$current_inst && $localID) {
                $current_inst = $institutions->where("local_id", $localID)->first();
            }

            // Confirm name and check for conflicts against existing records
            $_name = trim($row[2]);
            if ($current_inst) {      // found existing ID
                // If we already processed this inst, skip doing it again
                if (in_array($current_inst->id, $seen_insts)) {
                    $inst_skipped++;
                    continue;
                }
                // If import-name empty, use current value
                if (strlen($_name) < 1) {
                    $_name = trim($current_inst->name);
                }
                // Override name change if new-name already assigned to another inst
                if ($current_inst->name != $_name) {
                    $existing_inst = $institutions->where("name", $_name)->first();
                    if ($existing_inst) {
                        $_name = trim($current_inst->name);
                    }
                }
                // Override localID change if new-localID already assigned to another inst
                if ($current_inst->local_id != $localID && strlen($localID) > 0) {
                    $existing_inst = $institutions->where("local_id", $localID)->first();
                    if ($existing_inst) {
                        $localID = trim($current_inst->local_id);
                    }
                }
            // If we get here and current_inst is still null, it is PROBABLY a NEW record .. but
            // if an exact-match on institution name is found, USE IT instead of inserting
            } else {           // existing ID not found, try to find by name
                $current_inst = $institutions->where("name", $_name)->first();
                if ($current_inst) {
                    $_name = trim($current_inst->name);
                }
            }

            // Dont store/create anything if name is still empty
            if (strlen($_name) < 1) {
                $inst_skipped++;
                continue;
            }

            // Enforce defaults and put institution data columns into an array
            $_active = ($row[3] == 'N') ? 0 : 1;
            $_fte = ($row[4] == '') ? null : $row[4];
            $_notes = ($row[5] == '') ? null : $row[5];
            $_inst = array('name' => $_name, 'is_active' => $_active,  'local_id' => $localID,
                           'fte' => $_fte, 'notes' => $_notes);

            // Update or create the Institution record
            if (!$current_inst) {      // Create
                // input row had explicit ID? If so, assign it, otherwise, omit from create input array
                if ($row[0] != "" && is_numeric($row[0])) {
                    $_inst['id'] = $row[0];
                }
                $current_inst = Institution::create($_inst);
                $institutions->push($current_inst);
                $inst_created++;
            } else {                            // Update
                $_inst['id'] = $current_inst->id;
                $current_inst->update($_inst);
                $inst_updated++;
            }
            $seen_insts[] = $current_inst->id;
        }

        // Recreate the institutions list (like index does) to be returned to the caller
        $inst_data = array();
        $institutions = Institution::with('institutionGroups')->orderBy('name', 'ASC')
                                   ->get(['id','name','local_id','is_active']);
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
        $msg  = 'Institution Import successful : ' . $i_msg;

        return response()->json(['result' => true, 'msg' => $msg, 'inst_data' => $inst_data]);
    }
}
