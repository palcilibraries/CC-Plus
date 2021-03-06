<?php

namespace App\Http\Controllers;

use Hash;
use App\User;
use App\Role;
use App\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        if ($thisUser->hasRole("Admin")) {
            $user_data = User::with('roles','institution:id,name')->orderBy('name', 'ASC')->get();
        } else {    // is manager
            $user_data = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')
                             ->where('inst_id', '=', $thisUser->inst_id)->get();
        }

        // Make user role names one string, role IDs into an array, and status to a string for the view
        $data = array();
        foreach ($user_data as $rec) {
            $_roles = "";
            $user = $rec->toArray();
            $user['status'] = ($rec->is_active == 1) ? 'Active' : 'Inactive';
            $user['roles'] = array();
            foreach ($rec->roles as $role) {
                $user['roles'][] = $role->id;
                $_roles .= $role->name . ", ";
            }
            $user['role_string'] = rtrim(trim($_roles), ',');
            $data[] = $user;
        }

        // Admin gets a select-box of institutions (built-in create option), otherwise just the users' inst
        if ($thisUser->hasRole('Admin')) {
            $institutions = Institution::orderBy('name', 'ASC')->get(['id','name'])->toArray();
        } else {
            $institutions = Institution::where('id', '=', $thisUser->inst_id)
                                       ->get(['id','name'])->toArray();
        }

        // Set choices for roles; disallow choosing roles higher current user's max role
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())->orderBy('name', 'ASC')
                         ->get(['name', 'id'])->toArray();

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
        $thisUser = auth()->user();
        if (!$thisUser->hasAnyRole(['Admin','Manager'])) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:consodb.users,email',
            'password' => 'required|same:confirm_pass',
            'inst_id' => 'required'
        ]);
        $input = $request->all();
        if (!$thisUser->hasRole("Admin")) {     // managers only store to their institution
            $input['inst_id'] = $thisUser->inst_id;
        }
        if (!isset($input['is_active'])) {
            $input['is_active'] = 0;
        }

        // Make sure roles include "User"
        $user_role_id = Role::where('name', '=', 'User')->value('id');
        $new_roles = isset($input['roles']) ? $input['roles'] : array();
        if (!in_array($user_role_id, $new_roles)) {
            array_unshift($new_roles, $user_role_id);
        }

        // Create the user and attach roles (limited to current user maxRole)
        $viewer_role_id = Role::where('name', '=', 'Viewer')->value('id');
        $user = User::create($input);
        foreach ($new_roles as $r) {
            if (!$thisUser->hasRole("Admin") && $r == $viewer_role_id) {
                continue;   // only allow admin to set Viewer
            }
            if ($thisUser->maxRole() >= $r) {
                $user->roles()->attach($r);
            }
        }
        $user->load(['institution:id,name']);

        // Send email to the user about their new account
        // Set current consortium name if there are more than 1 active in this system
        $consortia = \App\Consortium::where('is_active',1)->get();
        $con_name = "";
        if ($consortia->count() > 1) {
            $current = $consortia->where('ccp_key',session('ccp_con_key'))->first();
            $con_name = ($current) ? $current->name : "";
        }
        $data = array('name' => $user->name, 'password' => $input['password']);
        Mail::to($input['email'])->send(new \App\Mail\NewUser($con_name,$data));

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
        $new_user['roles'] = $new_roles;

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
        $thisUser = auth()->user();
        $user = User::findOrFail($id);
        abort_unless($user->canManage(), 403);
        $user->roles = $user->roles()->pluck('role_id')->all();

        // Admin gets a select-box of institutions, otherwise just the users' inst
        if ($thisUser->hasRole('Admin')) {
            $institutions = Institution::orderBy('name', 'ASC')->get(['id','name'])->toArray();
        } else {
            $institutions = Institution::where('id', '=', $thisUser->inst_id)
                                       ->get(['id','name'])->toArray();
        }

        // Set choices for roles; disallow choosing roles higher current user's max role
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())->get(['name', 'id'])->toArray();

        return view('users.edit', compact('user','all_roles', 'institutions'));
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
        $thisUser = auth()->user();
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
        if (!$thisUser->hasRole("Admin")) {
            $input['inst_id'] = $thisUser->inst_id;
        }

        // Make sure roles include "User"
        $user_role_id = Role::where('name', '=', 'User')->value('id');
        $new_roles = isset($input['roles']) ? $input['roles'] : array();
        if (!in_array($user_role_id, $new_roles)) {
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
                if (!$thisUser->hasRole("Admin") && $r == $viewer_role_id) {
                    continue;
                }
                // ignore roles higher than current user's max
                if ($thisUser->maxRole() >= $r) {
                    $user->roles()->attach($r);
                }
            }
        }
        $user->load(['institution:id,name']);

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
        $updated_user['roles'] = $new_roles;

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
        $thisUser = auth()->user();

        // Admins access all, managers only access their inst, eveyone else gets an error
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        if ($thisUser->hasRole("Admin")) {
            $users = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')->get();
        } else {    // is manager
            $users = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')
                         ->where('inst_id', '=', $thisUser->inst_id)->get();
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
        $info_sheet->mergeCells('A1:D7');
        $info_sheet->getStyle('A1:D7')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:D7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Users tab represents a starting place for updating or importing settings. The table below\n";
        $top_txt .= "describes the datatype and order that the import expects. Any Import rows without an ID value\n";
        $top_txt .= "in column 'A' will be ignored. If values are missing/invalid for a column, but not required,\n";
        $top_txt .= "they will be set to the 'Default'. Any header row or columns beyond 'H' will be ignored.\n\n";
        $top_txt .= "Once the data sheet contains everything to be updated or inserted, save the sheet as a CSV\n";
        $top_txt .= "and import it into CC-Plus.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A9')->applyFromArray($head_style);
        $info_sheet->setCellValue('A9', "NOTE:");
        $info_sheet->mergeCells('B9:D11');
        $info_sheet->getStyle('B9:D11')->applyFromArray($info_style);
        $info_sheet->getStyle('B9:D11')->getAlignment()->setWrapText(true);
        $note_txt  = "When performing full-replacement imports, be VERY careful about changing or overwriting\n";
        $note_txt .= "existing ID value(s). The best approach is to add to, or modify, a full export to ensure\n";
        $note_txt .= "that existing user IDs are not accidently overwritten.";
        $info_sheet->setCellValue('B9', $note_txt);
        $info_sheet->getStyle('A13:D13')->applyFromArray($head_style);
        $info_sheet->setCellValue('A13', 'Column Name');
        $info_sheet->setCellValue('B13', 'Data Type');
        $info_sheet->setCellValue('C13', 'Description');
        $info_sheet->setCellValue('D13', 'Default');
        $info_sheet->setCellValue('A14', 'Id');
        $info_sheet->setCellValue('B14', 'Integer');
        $info_sheet->setCellValue('C14', 'Unique CC-Plus User ID - required');
        $info_sheet->setCellValue('A15', 'Email');
        $info_sheet->setCellValue('B15', 'String');
        $info_sheet->setCellValue('C15', 'Email address - required');
        $info_sheet->setCellValue('A16', 'Password');
        $info_sheet->setCellValue('B16', 'String');
        $info_sheet->setCellValue('C16', 'Password (will be encrypted)');
        $info_sheet->setCellValue('D16', 'NULL - no change');
        $info_sheet->setCellValue('A17', 'Name');
        $info_sheet->setCellValue('B17', 'String');
        $info_sheet->setCellValue('C17', 'Full name');
        $info_sheet->setCellValue('D17', 'NULL');
        $info_sheet->setCellValue('A18', 'Phone');
        $info_sheet->setCellValue('B18', 'String');
        $info_sheet->setCellValue('C18', 'Phone number');
        $info_sheet->setCellValue('D18', 'NULL');
        $info_sheet->setCellValue('A19', 'Active');
        $info_sheet->setCellValue('B19', 'String (Y or N)');
        $info_sheet->setCellValue('C19', 'Make the user active?');
        $info_sheet->setCellValue('D19', 'Y');
        $info_sheet->setCellValue('A20', 'Role(s)');
        $info_sheet->setCellValue('B20', 'Comma-separated strings');
        $info_sheet->setCellValue('C20', 'Admin, Manager, User, or Viewer');
        $info_sheet->setCellValue('D20', 'User');
        // IF you're planning to add these back in, note that the import function below also needs to
        // updated to account for the new column.
        //-------------------------------------------------------------------------------------------
        // $info_sheet->setCellValue('A21', 'PWChangeReq');
        // $info_sheet->setCellValue('B21', 'String (Y or N)');
        // $info_sheet->setCellValue('C21', 'Force user to change password');
        // $info_sheet->setCellValue('D21', 'N');
        $info_sheet->setCellValue('A21', 'Institution ID');
        $info_sheet->setCellValue('B21', 'Integer');
        $info_sheet->setCellValue('C21', 'Unique CC-Plus Institution ID (1=Staff)');
        $info_sheet->setCellValue('D21', '1');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 25; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the user data into a new sheet
        $users_sheet = $spreadsheet->createSheet();
        $users_sheet->setTitle('Users');
        $users_sheet->getRowDimension('1')->setRowHeight(15);
        $users_sheet->setCellValue('A1', 'Id');
        $users_sheet->setCellValue('B1', 'Email');
        $users_sheet->setCellValue('C1', 'Password');
        $users_sheet->setCellValue('D1', 'Name');
        $users_sheet->setCellValue('E1', 'Phone');
        $users_sheet->setCellValue('F1', 'Active');
        $users_sheet->setCellValue('G1', 'Role(s)');
        // $users_sheet->setCellValue('H1', 'PWChangeReq');
        if ($thisUser->hasRole('Admin')) {
            $users_sheet->setCellValue('H1', 'Institution ID');
            $users_sheet->setCellValue('J1', 'Institution');
        }
        $row = 2;
        foreach ($users as $user) {
            $users_sheet->getRowDimension($row)->setRowHeight(15);
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
            // $_pwcr = ($user->password_change_required) ? "Y" : "N";
            // $users_sheet->setCellValue('H' . $row, $_pwcr);
            if ($thisUser->hasRole('Admin')) {
                $users_sheet->setCellValue('H' . $row, $user->inst_id);
                $_inst = ($user->inst_id == 1) ? "Staff" : $user->institution->name;
                $users_sheet->setCellValue('J' . $row, $_inst);
            }
            $row++;
        }

        // Auto-size the columns
        $user_columns = array('A','B','C','D','E','F','G','H','I','J');
        foreach ($user_columns as $col) {
            $users_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        if ($thisUser->hasRole('Admin')) {
            $fileName = "CCplus_" . session('ccp_con_key', '') . "_Users." . $type;
        } else {
            $fileName = "CCplus_" . preg_replace('/ /', '', $thisUser->institution->name) . "_Users." . $type;
        }

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
     * Import users from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Only Admins can import user data
        abort_unless(auth()->user()->hasRole(['Admin']), 403);

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

        // Get existing user data
        $users = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')->get();
        $institutions = Institution::get();

        // Process the input rows
        $num_skipped = 0;
        $num_updated = 0;
        $num_created = 0;
        foreach ($rows as $row) {
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0]) || sizeof($row) < 7) {
                continue;
            }
            $cur_user_id = intval($row[0]);

            // Update/Add the user data/settings
            // Check ID and name columns for silliness or errors
            $_email = trim($row[1]);
            $current_user = $users->where("id", "=", $cur_user_id)->first();
            if (!is_null($current_user)) {      // found existing ID
                if (strlen($_email) < 1) {       // If import email empty, use current value
                    $_name = trim($current_user->email);
                } else {                        // trap changing an email to one that already exists
                    $existing_user = $users->where("name", "=", $_email)->first();
                    if (!is_null($existing_user)) {
                        $_email = trim($current_user->email);     // override, use current - no change
                    }
                }
            } else {        // existing ID not found, try to find by name
                $current_user = $users->where("email", "=", $_email)->first();
                if (!is_null($current_user)) {
                    $_email = trim($current_user->email);
                }
            }

            // If we're creating a user, but the password field is empty, skip it
            if (is_null($current_user) && $row[2] == '') {
                $num_skipped++;
                continue;
            }

            // Dont store/create anything if email is still empty
            if (strlen($_email) < 1) {
                $num_skipped++;
                continue;
            }

            // Enforce defaults
            $_name = ($row[3] == '') ? $_email : $row[3];
            $_phone = ($row[4] == '') ? $_email : $row[4];
            $_active = ($row[5] == 'N') ? 0 : 1;
            $_inst = ($row[7] == '') ? 0 : intval($row[7]);
            $user_inst = $institutions->where('id', $_inst)->first();
            if (!$user_inst) {
                $num_skipped++;
                continue;
            }

            // Put user data columns into an array
            $_user = array('id' => $cur_user_id, 'email' => $_email, 'name' => $_name, 'phone' => $_phone,
                           'is_active' => $_active, 'inst_id' => $_inst);

            // Only include password if it has a value
            if ($row[2] != '') {
                $_user['password'] = $row[2];
            }

            // Update or create the User record
            if (is_null($current_user)) {      // Create
                $current_user = User::create($_user);
                $cur_user_id = $current_user->id;
                $num_created++;
            } else {                            // Update
                $current_user->update($_user);
                $num_updated++;
            }

            // Set roles
            $import_roles = preg_replace('/,,/', ',',preg_replace('/ /', ',',$row[6]));
            $_roles = preg_split('/,/', $import_roles);
            $role_ids = array();
            $sawUser = false;
            foreach ($_roles as $r) {
                $rstr = ucwords(trim($r));
                if ($rstr == 'User') {
                    $sawUser = true;
                }
                $role = Role::where('name', '=', $rstr)->first();
                if ($role) {
                    $role_ids[] = $role->id;
                }
            }
            if (!$sawUser) {
                $role_ids[] = Role::where('name', '=', 'User')->value('id');
            }
            $current_user->roles()->detach();
            foreach ($role_ids as $_r) {
                $current_user->roles()->attach($_r);
            }
        }

        // Recreate the users list (like index does) to be returned to the caller
        $user_data = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')->get();
        $users = $user_data->map(function($user) {
            $user->status = ($user->is_active == 1) ? 'Active' : 'Inactive';
            $_roles = "";
            foreach ($user->roles as $role) {
                $_roles .= $role->name . ", ";
            }
            $user->role_string = rtrim(trim($_roles), ',');
            return $user;
        });

        // return the current full list of users with a success message
        $detail = "";
        $detail .= ($num_updated > 0) ? $num_updated . " updated" : "";
        if ($num_created > 0) {
            $detail .= ($detail != "") ? ", " . $num_created . " added" : $num_created . " added";
        }
        if ($num_skipped > 0) {
            $detail .= ($detail != "") ? ", " . $num_skipped . " skipped" : $num_skipped . " skipped";
        }
        $msg  = 'Import successful, Users : ' . $detail;

        return response()->json(['result' => true, 'msg' => $msg, 'users' => $users]);
    }
}
