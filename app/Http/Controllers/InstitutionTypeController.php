<?php

namespace App\Http\Controllers;

use App\InstitutionType;
use App\Institution;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class InstitutionTypeController extends Controller
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
        $data = InstitutionType::orderBy('id', 'DESC')->get()->toArray();
        return view('institutiontypes.index', compact('data'));
    }

      /**
       * Show the form for creating a new resource.
       *
       * @return \Illuminate\Http\Response
       */
    public function create()
    {
        return view('institutiontypes.create');
    }

      /**
       * Store a newly created resource in storage.
       *
       * @param  \Illuminate\Http\Request  $request
       * @return \Illuminate\Http\Response
       */
    public function store(Request $request)
    {
        // $this->validate($request, [
        //     'name' => 'required|unique:consodb.institutiontypes,name',
        // ]);
        $test = InstitutionType::where('name', '=', $request->input('name'))->first();
        if ($test) {
            return response()->json(['result' => false, 'msg' => 'An existing type with that name already exists']);
        }
        $type = InstitutionType::create(['name' => $request->input('name')]);

        return response()->json(['result' => true, 'msg' => 'New institution type successfully created',
                                 'type' => $type]);
        // return redirect()->route('institutiontypes.index')
        //                 ->with('success', 'Institution Type created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $type = InstitutionType::findOrFail($id);
        return view('institutiontypes.edit', compact('type'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $type = InstitutionType::findOrFail($id);
        return view('institutiontypes.edit', compact('type'));
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
        $type = InstitutionType::findOrFail($id);
        $this->validate($request, ['name' => 'required']);

        // Don't save if the name already exists for another ID
        $test = InstitutionType::where('name', '=', $request->input('name'))->first();
        if ($test) {
            if ($test->id != $type->id) {   // allow (re)saving a type with an unchanged name
                return response()->json(['result' => false, 'msg' => 'Another type with that name already exists']);
            }
        }
        $type->name = $request->input('name');
        $type->save();

        return response()->json(['result' => true, 'msg' => 'Institution type successfully updated',
                                 'type' => $type]);
        // return redirect()->route('institutiontypes.index')
        //               ->with('success', 'Institution Type updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $type = InstitutionType::findOrFail($id);

        // Update all institutions that have this type before deleting it
        $institutions = Institution::where('institutiontype_id', '=', $id);
        foreach ($institutions as $inst) {
            $inst->institutiontype_id = 1;  // reset type to the default (Not classified)
            $inst->save();
        }
        $type->delete();
        // return redirect()->route('institutiontypes.index')
        //               ->with('success', 'Institution Type deleted successfully');
        return response()->json(['result' => true, 'msg' => 'Institution type successfully deleted']);
    }

    /**
     * Export institution types from the database.
     *
     * @param  string  $type    // 'xls' or 'xlsx'
     */
    public function export($output_type)
    {
        // Get all types
        $types = InstitutionType::orderBy('id', 'ASC')->get();

        // Setup styles array for headers
        $head_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E6');
        $top_txt  = "The Institution Types tab represents a starting place for updating or importing settings.\n";
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
        $info_sheet->setCellValue('A10','Id');
        $info_sheet->setCellValue('B10','Integer');
        $info_sheet->setCellValue('C10','Unique CC-Plus InstitutionType ID - required');
        $info_sheet->setCellValue('A11','Name');
        $info_sheet->setCellValue('B11','String');
        $info_sheet->setCellValue('C11','Institution Type Name - required');

        // Load the type data into a new sheet
        $type_sheet = $spreadsheet->createSheet();
        $type_sheet->setTitle('Institution Types');
        $type_sheet->setCellValue('A1', 'Id');
        $type_sheet->setCellValue('B1', 'Name');
        $row = 2;
        foreach ($types as $type) {
            $type_sheet->setCellValue('A' . $row, $type->id);
            $type_sheet->setCellValue('B' . $row, $type->name);
            $row++;
        }
        $fileName = "CCplus_" . session('ccp_con_key', '') . "_InstitutionTypes." . $output_type;
        if ($output_type == 'xlsx') {
            $writer = new Xlsx($spreadsheet);
        } else if ($output_type == 'xls') {
            $writer = new Xls($spreadsheet);
        }

        // redirect output to client browser
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save('php://output');
    }

    /**
     * Import institution types from a CSV to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Handle and validate inputs
        $this->validate($request, ['type' => 'required', 'csvfile' => 'required']);
        $type = $request->input('type');
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }

        // Set database table to be updated
        $table = config('database.connections.consodb.database') . '.institutiontypes';

        // Turn the CSV data into an array
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);

        $rows = array_map("str_getcsv", explode("\n", $csvData));

        // If input file is empty, return w/ error string
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }
        $num_deleted = 0;
        $num_updated = 0;
        $num_created = 0;

        // If user requested full replacement, we want to delete the existing types (except id:1)
        // BUT - since Instiution Type is a foreign key for Institution... we can't just trash it.
        if ($request->input('type') == 'Full Replacement') {

            // Get all institutions, and save the current ID => type as a separate array
            $institutions = Institution::get();
            $original_types = array();
            foreach ($institutions as $inst) {
                $original_types[$inst->id] = $inst->type_id;
                $inst->type_id = 1;
                $inst->save();
            }

            // Okay, toss the types
            $num_deleted = InstitutionType::count() - 1;
            InstitutionType::where('id', '<>', 1)->delete();
        }

        // Process the input rows
        foreach ($rows as $row) {
            if (isset($row[0])) {
                // Ignore bad/missing ID
                if ($row[0] != "" && is_numeric($row[0]) && $row[0] > 1) {

                    // If adding/updating types, check if it already exists
                    $existing_type = InstitutionType::where("id", "=", $row[0])->first();

                    if (!is_null($existing_type)) {
                        if (!is_null($row[1])) {
                            $existing_type->name = $row[1];
                            $num_updated++;
                        }
                    } else {
                        if (!is_null($row[1])) {
                            $new_type = InstitutionType::create(array('id' => $row[0], 'name' => $row[1]));
                            $num_created++;
                        }
                    }
                }
            }
        }

        // Get the new full list of types
        $types = InstitutionType::orderBy('id', 'ASC')->get();
        $new_ids = $types->pluck('id')->values()->toArray();

        // Reset type for institutions if the type still exists, otherwise leave it as 1 (not classified)
        foreach ($original_types as $id => $type) {
            $inst = $institutions->where('id', $id)->first();
            if (in_array($type, $new_ids)) {
                $inst->type_id = $type;
                $inst->save();
            } else {
            }
        }

        // return the current full list of types with a success message
        $msg  = 'Institution Types imported successfully : ';
        $msg .= ($num_deleted>0) ? $num_deleted . " removed, " : "";
        $msg .= $num_updated . " updated and " . $num_created . " added.";
        return response()->json(['result' => true, 'msg' => $msg, 'types' => $types->toArray()]);
    }
}
