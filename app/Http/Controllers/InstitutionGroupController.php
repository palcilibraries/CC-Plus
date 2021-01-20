<?php

namespace App\Http\Controllers;

use App\InstitutionGroup;
use App\Institution;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class InstitutionGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $groups = InstitutionGroup::with('institutions:id,name')->orderBy('name', 'ASC')->get();

        $data = array();
        foreach ($groups as $group) {
            $members = $group->institutions->pluck('id')->toArray();
            $group->not_members = Institution::whereNotIn('id',$members)->get(['id','name'])->toArray();
            $data[] = $group->toArray();
        }
        return view('institutiongroups.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Built-in to index Vue component
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
          'name' => 'required|unique:consodb.institutiongroups,name',
        ]);

        $group = InstitutionGroup::create(['name' => $request->input('name')]);
        $group->institutions = array();
        $group->not_members = Institution::get(['id','name'])->toArray();

        return response()->json(['result' => true, 'msg' => 'Group created successfully', 'group' => $group]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
    //     $data = InstitutionGroup::with('institutions')->findOrFail($id);
    //     $members = $data->institutions->sortBy('name')->values()->toArray();
    //     $member_ids = $data->institutions->pluck('id');
    //     $not_members = Institution::whereNotIn('id', $member_ids)
    //                        ->where(function ($query) use ($id) {
    //                            $query->where('id', '<>', 1)->where('is_active', true);
    //                        })
    //                        ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
    //     $group = $data->toArray();
    //     $group['institutions'] = $members;
    //     return view('institutiongroups.edit', compact('group', 'not_members'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
    //     $data = InstitutionGroup::with('institutions')->findOrFail($id);
    //     $members = $data->institutions->sortBy('name')->values()->toArray();
    //     $member_ids = $data->institutions->pluck('id');
    //     $not_members = Institution::whereNotIn('id', $member_ids)
    //                        ->where(function ($query) use ($id) {
    //                            $query->where('id', '<>', 1)->where('is_active', true);
    //                        })
    //                        ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
    //     $group = $data->toArray();
    //     $group['institutions'] = $members;
    //     return view('institutiongroups.edit', compact('group', 'not_members'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $group = InstitutionGroup::with('institutions:id,name')->findOrFail($id);
        $this->validate($request, [
          'name' => 'required',
          'institutions' => 'required',
        ]);
        // Update group name
        $group->name = $request->input('name');
        $group->save();

        // Reset membership assignments
        $group->institutions()->detach();
        foreach ($request->institutions as $inst) {
            $group->institutions()->attach($inst['id']);
        }

        // Build returned group data the way index() does
        $data = array();
        $member_ids = $group->institutions->pluck('id')->toArray();
        $group->not_members = Institution::whereNotIn('id',$member_ids)->get(['id','name'])->toArray();
        $data = $group->toArray();
        $data['institutions'] = $request->institutions;

        return response()->json(['result' => true, 'msg' => 'Group updated successfully', 'group' => $data]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $group = InstitutionGroup::findOrFail($id);
        $group->delete();
        return response()->json(['result' => true, 'msg' => 'Group successfully deleted']);
    }

    /**
     * Export institution types from the database.
     *
     * @param  string  $type    // 'xls' or 'xlsx'
     */
    public function export($type)
    {
        // Get all types
        $groups = InstitutionGroup::orderBy('name', 'ASC')->get();

        // Setup styles array for headers
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
        $info_sheet->mergeCells('A1:D6');
        $info_sheet->getStyle('A1:D6')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:D6')->getAlignment()->setWrapText(true);
        $top_txt  = "The Institution Groups tab represents a starting place for updating or importing settings.\n";
        $top_txt .= "The table below describes the field datatypes and order that the import expects. Any Import\n";
        $top_txt .= "rows without an ID in column A will be ignored. If required values are missing/invalid within\n";
        $top_txt .= "a given row, the row will be ignored.\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'B' will be ignored.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A8:D8')->applyFromArray($head_style);
        $info_sheet->setCellValue('A9', 'Column Name');
        $info_sheet->setCellValue('B9', 'Data Type');
        $info_sheet->setCellValue('C9', 'Description');
        $info_sheet->setCellValue('A10', 'Id');
        $info_sheet->setCellValue('B10', 'Integer');
        $info_sheet->setCellValue('C10', 'Unique CC-Plus InstitutionGroup ID - required');
        $info_sheet->setCellValue('A11', 'Name');
        $info_sheet->setCellValue('B11', 'String');
        $info_sheet->setCellValue('C11', 'Institution Group Name - required');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 13; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the type data into a new sheet
        $group_sheet = $spreadsheet->createSheet();
        $group_sheet->setTitle('Institution Groups');
        $group_sheet->setCellValue('A1', 'Id');
        $group_sheet->setCellValue('B1', 'Name');
        $row = 2;
        foreach ($groups as $group) {
            $group_sheet->getRowDimension($row)->setRowHeight(15);
            $group_sheet->setCellValue('A' . $row, $group->id);
            $group_sheet->setCellValue('B' . $row, $group->name);
            $row++;
        }

        // Auto-size the columns
        $group_sheet->getColumnDimension('A')->setAutoSize(true);
        $group_sheet->getColumnDimension('B')->setAutoSize(true);

        // Give the file a meaningful filename
        $fileName = "CCplus_" . session('ccp_con_key', '') . "_InstitutionGroups." . $type;

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
     * Import institution groups from a CSV to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Handle and validate inputs
        $this->validate($request, ['type' => 'required', 'csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }
        $type = $request->input('type');
        if ($type != 'New Additions' && $type != 'Full Replacement') {
            return response()->json(['result' => false, 'msg' => 'Error - unrecognized import type.']);
        }

        // Turn the CSV data into an array
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);

        $rows = array_map("str_getcsv", explode("\n", $csvData));

        // If input file is empty, return w/ error string
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }
        $num_deleted = 0;
        $num_skipped = 0;
        $num_updated = 0;
        $num_created = 0;

        // If user requested full replacement we'll delete all the existing groups.
        // First, though, keep the current set to be able to restore any duplicates
        // after we've imported the new set.
        if ($type == 'Full Replacement') {
            // Get all institutions with their groups
            $original_groups = array();
            $institutions = Institution::with('institutionGroups')->get();
            foreach ($institutions as $inst) {
                $groups = array();
                // Save the current assigned groups and detach them
                foreach ($inst->institutionGroups as $group) {
                    $groups[$group->id] = $group->name;
                }
                $original_groups[$inst->id] = $groups;
                // nuke the groups for this inst
                $inst->institutionGroups()->sync([]);
            }

            // Okay, toss the groups themselves
            $all_groups = InstitutionGroup::get();
            $num_deleted = $all_groups->count();
            foreach ($all_groups as $group) {
                $group->delete();
            }
        }
        $current_groups = InstitutionGroup::get();

        // Process the input rows
        foreach ($rows as $row) {
            if (isset($row[0])) {
                // Ignore bad/missing ID
                if ($row[0] != "" && is_numeric($row[0])) {
                    // If we're adding and the name or id already exists, skip it
                    if ($request->input('type') == 'New Additions') {
                        $existing_id = $current_groups->where("id", "=", $row[0])->first();
                        $existing_name = $current_groups->where("name", "=", $row[1])->first();
                        if (!is_null($existing_id) || !is_null($existing_name)) {
                            $num_skipped++;
                            continue;
                        }
                    }

                    // Save the new name
                    if (!is_null($row[1])) {
                        $_name = trim($row[1]);
                        if (strlen($_name) > 0) {
                            $new_group = InstitutionGroup::create(array('id' => $row[0], 'name' => $_name));
                            $num_created++;
                        }
                    }
                }
            }
        }

        // Get the new full list of group names
        $new_groups = InstitutionGroup::orderBy('id', 'ASC')->get();

        // If we're replacing, reset any institutions' group-assignment if the group
        // still exists (exact name-match)
        if ($type == 'Full Replacement') {
            foreach ($original_groups as $inst_id => $old_groups) {
                foreach ($old_groups as $_id => $_name) {
                    $new_group = $new_groups->where('name', '=', $_name)->first();
                    if ($new_group) {
                        $new_group->institutions()->attach($inst_id);
                    }
                }
            }
        }

        // return the current full list of groups with a success message
        $msg  = 'Institution Groups imported successfully : ';
        $msg .= ($num_deleted > 0) ? $num_deleted . " removed, " : "";
        $msg .= $num_updated . " updated and " . $num_created . " added";
        if ($num_skipped > 0) {
            $msg .= ($num_skipped > 0) ? " (" . $num_skipped . " existing names/ids skipped)" : ".";
        }
        return response()->json(['result' => true, 'msg' => $msg, 'groups' => $new_groups->toArray()]);
    }
}
