<?php

namespace App\Http\Controllers;

use Hash;
use App\User;
use App\Role;
use App\Institution;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

//Enables us to output flash messaging
use Session;

class UserController extends Controller
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

        // Admins see all, managers see only their inst, eveyone else gets an error
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        if (auth()->user()->hasRole("Admin")) {
            $users = User::with('roles', 'institution:id,name')->orderBy('id', 'ASC')->get();
        } else {    // is manager
            $users = User::with('roles', 'institution:id,name')->orderBy('ID', 'ASC')
                         ->where('inst_id', '=', auth()->user()->inst_id)->get();
        }

        // Add user's roles as a string in the data array we're sending to the view
        $data = array();
        foreach ($users as $_u) {
            $_roles = "";
            $new_u = $_u->toArray();
            $new_u['inst_name'] = $_u->institution->name;
            $new_u['status'] = ($_u->is_active == 1) ? 'Active' : 'Inactive';
            foreach ($_u->roles as $role) {
                $_roles .= $role->name . ", ";
            }
            $_roles = rtrim(trim($_roles), ',');
            $new_u['role_string'] = $_roles;
            array_push($data, $new_u);
        }

        // Admin gets a select-box of institutions, otherwise just the users' inst
        if (auth()->user()->hasRole('Admin')) {
            $institutions = Institution::orderBy('id', 'ASC')->get(['id','name'])->toArray();
        } else {
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)
                                       ->get(['id','name'])->toArray();
        }

        // Set choices for roles; disallow choosing roles higher current user's max role
        $all_roles = Role::where('id', '<=', auth()->user()->maxRole())->get(['name', 'id'])->toArray();

        return view('users.index', compact('data', 'institutions', 'all_roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasAnyRole(['Admin','Manager'])) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:consodb.users,email',
            'password' => 'required|same:confirm_pass',
            'inst_id' => 'required'
        ]);

        $input = $request->all();
        if (!auth()->user()->hasRole("Admin")) {     // managers only store to their institution
            $input['inst_id'] = auth()->user()->inst_id;
        }
        if (!isset($input['is_active'])) {
            $input['is_active'] = 0;
        }

        // Make sure roles include "User"
        $user_role_id = Role::where('name', '=', 'User')->value('id');
        $new_roles = isset($input['roles']) ? $input['roles'] : array();
        if (!in_array($user_role_id,$new_roles)) {
            array_unshift($new_roles, $user_role_id);
        }

        // Create the user and attach roles (limited to current user maxRole)
        $viewer_role_id = Role::where('name', '=', 'Viewer')->value('id');
        $user = User::create($input);
        foreach ($new_roles as $r) {
            if (!auth()->user()->hasRole("Admin") && $r == $viewer_role_id) {
                continue;   // only allow admin to set Viewer
            }
            if (auth()->user()->maxRole() >= $r) {
                $user->roles()->attach($r);
            }
        }
        $user->load(['roles','institution:id,name']);

        // Setup array to hold new user to match index fields
        $_roles = "";
        $new_user = $user->toArray();
        $new_user['inst_name'] = $user->institution->name;
        $new_user['status'] = ($user->is_active == 1) ? 'Active' : 'Inactive';
        foreach ($user->roles as $role) {
            $_roles .= $role->name . ", ";
        }
        $_roles = rtrim(trim($_roles), ',');
        $new_user['permission'] = $user->maxRoleName();
        $new_user['role_string'] = $_roles;

        return response()->json(['result' => true, 'msg' => 'User successfully created', 'user' => $new_user]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        abort_unless($user->canManage(), 403);

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // $user = User::with('roles')->findOrFail($id);
        $user = User::findOrFail($id);
        $user_roles = $user->roles()->pluck('role_id')->all();
        abort_unless($user->canManage(), 403);

        // Admin gets a select-box of institutions, otherwise just the users' inst
        if (auth()->user()->hasRole('Admin')) {
            $institutions = Institution::orderBy('id', 'ASC')->get(['id','name'])->toArray();
        } else {
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)
                                       ->get(['id','name'])->toArray();
        }

        // Set choices for roles; disallow choosing roles higher current user's max role
        $all_roles = Role::where('id', '<=', auth()->user()->maxRole())->get(['name', 'id'])->toArray();

        return view('users.edit', compact('user', 'user_roles', 'all_roles', 'institutions'));
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
        $user = User::findOrFail($id);
        if (!$user->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

        // Validate form inputs
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:consodb.users,email,' . $id,
            'password' => 'same:confirm_pass',
            'roles' => 'required',
            'inst_id' => 'required'
        ]);
        $input = $request->all();
        if (empty($input['password'])) {
            $input = array_except($input, array('password'));
        }
        $input = array_except($input, array('confirm_pass'));

        // Only admins can change inst_id
        if (!auth()->user()->hasRole("Admin")) {
            $input['inst_id'] = auth()->user()->inst_id;
        }

        // Make sure roles include "User"
        $user_role_id = Role::where('name', '=', 'User')->value('id');
        $new_roles = isset($input['roles']) ? $input['roles'] : array();
        if (!in_array($user_role_id,$new_roles)) {
            array_unshift($new_roles, $user_role_id);
        }

        // Update the user record
        $input = array_except($input, array('roles'));
        $user->update($input);

        // Update roles (silently ignore roles if user saving their own record)
        $viewer_role_id = Role::where('name', '=', 'Viewer')->value('id');
        if (auth()->id() != $id) {
            $user->roles()->detach();
            foreach ($new_roles as $r) {
                // Current user must be an admin to set Viewer role
                if (!auth()->user()->hasRole("Admin") && $r == $viewer_role_id) {
                    continue;
                }
                // ignore roles higher than current user's max
                if (auth()->user()->maxRole() >= $r) {
                    $user->roles()->attach($r);
                }
            }
        }
        $user->load(['roles','institution:id,name']);

        // Setup array to hold updated user record
        $_roles = "";
        $updated_user = $user->toArray();
        $updated_user['inst_name'] = $user->institution->name;
        $updated_user['status'] = ($user->is_active == 1) ? 'Active' : 'Inactive';
        foreach ($user->roles as $role) {
            $_roles .= $role->name . ", ";
        }
        $_roles = rtrim(trim($_roles), ',');
        $updated_user['permission'] = $user->maxRoleName();
        $updated_user['role_string'] = $_roles;

        return response()->json(['result' => true, 'msg' => 'User settings successfully updated',
                                 'user' => $updated_user]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if (!$user->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        if (auth()->id() == $id) {
            return response()->json(['result' => false,
                                     'msg' => 'Suicide forbidden (403); have an Admin or Manager assist you.']);
        }
        $user->delete();
        return response()->json(['result' => true, 'msg' => 'User successfully deleted']);
    }

    /**
     * Export user records from the database.
     *
     * @param  string  $type    // 'xls' or 'xlsx'
     */
    public function export($type)
    {
        // Admins access all, managers only access their inst, eveyone else gets an error
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        if (auth()->user()->hasRole("Admin")) {
            $users = User::with('roles', 'institution:id,name')->orderBy('id', 'ASC')->get();
        } else {    // is manager
            $users = User::with('roles', 'institution:id,name')->orderBy('ID', 'ASC')
                         ->where('inst_id', '=', auth()->user()->inst_id)->get();
        }

        // Setup styles array for headers
        $head_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E7');
        $top_txt  = "The Users tab represents a starting place for updating or importing settings. The table below\n";
        $top_txt .= "describes the datatype and order that the import expects. Any Import rows without an ID value\n";
        $top_txt .= "in column 'A' will be ignored. If values are missing/invalid for a column, but not required,\n";
        $top_txt .= "they will be set to the 'Default'. Any header row or columns beyond 'I' will be ignored.\n\n";
        $top_txt .= "Once the data sheet contains everything to be updated or inserted, save the sheet as a CSV\n";
        $top_txt .= "and import it into CC-Plus.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A9')->applyFromArray($head_style);
        $info_sheet->setCellValue('A9', "NOTE:");
        $info_sheet->mergeCells('B9:E11');
        $note_txt  = "When performing full-replacement imports, be VERY careful about changing or overwriting\n";
        $note_txt .= "existing ID value(s). The best approach is to add to, or modify, a full export to ensure\n";
        $note_txt .= "that existing user IDs are not accidently overwritten.";
        $info_sheet->setCellValue('B9', $note_txt);
        $info_sheet->getStyle('A13:D13')->applyFromArray($head_style);
        $info_sheet->setCellValue('A13', 'Column Name');
        $info_sheet->setCellValue('B13', 'Data Type');
        $info_sheet->setCellValue('C13', 'Description');
        $info_sheet->setCellValue('D13', 'Default');
        $info_sheet->setCellValue('A14','Id');
        $info_sheet->setCellValue('B14','Integer');
        $info_sheet->setCellValue('C14','Unique CC-Plus User ID - required');
        $info_sheet->setCellValue('A15','Email');
        $info_sheet->setCellValue('B15','String');
        $info_sheet->setCellValue('C15','Email address - required');
        $info_sheet->setCellValue('A16','Password');
        $info_sheet->setCellValue('B16','String');
        $info_sheet->setCellValue('C16','Password (will be encrypted)');
        $info_sheet->setCellValue('D16','NULL - no change');
        $info_sheet->setCellValue('A17','Name');
        $info_sheet->setCellValue('B17','String');
        $info_sheet->setCellValue('C17','Full name');
        $info_sheet->setCellValue('D17','NULL');
        $info_sheet->setCellValue('A18','Phone');
        $info_sheet->setCellValue('B18','String');
        $info_sheet->setCellValue('C18','Phone number');
        $info_sheet->setCellValue('D18','NULL');
        $info_sheet->setCellValue('A19','Active');
        $info_sheet->setCellValue('B19','String (Y or N)');
        $info_sheet->setCellValue('C19','Make the user active?');
        $info_sheet->setCellValue('D19','Y');
        $info_sheet->setCellValue('A20','Role(s)');
        $info_sheet->setCellValue('B20','Comma-separated strings');
        $info_sheet->setCellValue('C20','Admin, Manager, User, or Viewer');
        $info_sheet->setCellValue('D20','User');
        $info_sheet->setCellValue('A21','PWChangeReq');
        $info_sheet->setCellValue('B21','String (Y or N)');
        $info_sheet->setCellValue('C21','Force user to change password');
        $info_sheet->setCellValue('D21','N');
        $info_sheet->setCellValue('A22','Institution ID');
        $info_sheet->setCellValue('B22','Integer');
        $info_sheet->setCellValue('C22','Unique CC-Plus Institution ID (1=Staff)');
        $info_sheet->setCellValue('D22','1');

        // Load the user data into a new sheet
        $users_sheet = $spreadsheet->createSheet();
        $users_sheet->setTitle('Users');
        $users_sheet->setCellValue('A1', 'Id');
        $users_sheet->setCellValue('B1', 'Email');
        $users_sheet->setCellValue('C1', 'Password');
        $users_sheet->setCellValue('D1', 'Name');
        $users_sheet->setCellValue('E1', 'Phone');
        $users_sheet->setCellValue('F1', 'Active');
        $users_sheet->setCellValue('G1', 'Role(s)');
        $users_sheet->setCellValue('H1', 'PWChangeReq');
        if (auth()->user()->hasRole('Admin')) {
            $users_sheet->setCellValue('I1', 'Institution ID');
            $users_sheet->setCellValue('J1', 'Institution');
        }
        $row = 2;
        foreach ($users as $user) {
            $users_sheet->setCellValue('A' . $row, $user->id);
            $users_sheet->setCellValue('B' . $row, $user->email);
            $users_sheet->setCellValue('D' . $row, $user->name);
            $users_sheet->setCellValue('E' . $row, $user->phone);
            $_stat = ($user->is_active) ? "Y" : "N";
            $users_sheet->setCellValue('F' . $row, $_stat);
            $_roles = "";
            foreach ($user->roles as $role) {
                $_roles .= $role->name . ", ";
            }
            $_roles = rtrim(trim($_roles), ',');
            $users_sheet->setCellValue('G' . $row, $_roles);
            $_pwcr = ($user->password_change_required) ? "Y" : "N";
            $users_sheet->setCellValue('H' . $row, $_pwcr);
            if (auth()->user()->hasRole('Admin')) {
                $users_sheet->setCellValue('I' . $row, $user->inst_id);
                $_inst = ($user->inst_id == 1) ? "Staff" : $user->institution->name;
                $users_sheet->setCellValue('J' . $row, $_inst);
            }
            $row++;
        }
        if (auth()->user()->hasRole('Admin')) {
            $fileName = "CCplus_" . session('ccp_con_key', '') . "_Users." . $type;
        } else {
            $fileName = "CCplus_" . preg_replace('/ /','',auth()->user()->institution->name) . "_Users." . $type;
        }
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
