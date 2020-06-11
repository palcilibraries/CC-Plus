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
        $data = InstitutionGroup::orderBy('id', 'DESC')->paginate(10);
        return view('institutiongroups.index', compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 10);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('institutiongroups.create');
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

        return redirect()->route('institutiongroups.index')
                      ->with('success', 'Institution Group created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $group = InstitutionGroup::findOrFail($id);
        return view('institutiongroups.edit', compact('group'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $group = InstitutionGroup::with('institutions')->findOrFail($id);

        $member_ids = $group->institutions->pluck('id');
        $not_members = Institution::whereNotIn('id', $member_ids)
                           ->where(function ($query) use ($id) {
                               $query->where('id', '<>', 1)->where('is_active', true);
                           })
                           ->orderBy('name', 'ASC')->get(['id','name'])->toArray();

        return view('institutiongroups.edit', compact('group','not_members'));
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
        $group = InstitutionGroup::findOrFail($id);
        $this->validate($request, [
          'name' => 'required',
        ]);
        // Update group name
        $group->name = $request->input('name');
        $group->save();

        // Reset membership assignments
        $group->institutions()->detach();
        foreach ($request->institutions as $inst) {
            $group->institutions()->attach($inst['id']);
        }
        return response()->json(['result' => true, 'msg' => 'Group updated successfully']);
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
        return redirect()->route('institutiongroups.index')
                      ->with('success', 'Institution Group deleted successfully');
    }

    /**
     * Export institution types from the database.
     *
     * @param  string  $type    // 'xls' or 'xlsx'
     * @return \Illuminate\Http\Response
     */
    public function export($type)
    {
        // Get all types
        $groups = InstitutionGroup::orderBy('id', 'ASC')->get();

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
        $info_sheet->setCellValue('A10','Id');
        $info_sheet->setCellValue('B10','Integer');
        $info_sheet->setCellValue('C10','Unique CC-Plus InstitutionGroup ID - required');
        $info_sheet->setCellValue('A11','Name');
        $info_sheet->setCellValue('B11','String');
        $info_sheet->setCellValue('C11','Institution Group Name - required');

        // Load the type data into a new sheet
        $group_sheet = $spreadsheet->createSheet();
        $group_sheet->setTitle('Institution Groups');
        $group_sheet->setCellValue('A1', 'Id');
        $group_sheet->setCellValue('B1', 'Name');
        $row = 2;
        foreach ($groups as $group) {
            $group_sheet->setCellValue('A' . $row, $group->id);
            $group_sheet->setCellValue('B' . $row, $group->name);
            $row++;
        }
        $fileName = "CCplus_" . session('ccp_con_key', '') . "_InstitutionGroups." . $type;
        if ($type == 'xlsx') {
            $writer = new Xlsx($spreadsheet);
        } else if ($type == 'xls') {
            $writer = new Xls($spreadsheet);
        }

        // redirect output to client browser
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save('php://output');
    }
}
