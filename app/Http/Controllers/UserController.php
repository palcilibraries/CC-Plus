<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Role;
use App\Institution;
use Hash;
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
        // abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        // $roles = Role::where('id', '<=', auth()->user()->maxRole())->pluck('name', 'id');
        //
        // if (auth()->user()->hasRole("Admin")) {
        //     $institutions = Institution::pluck('name', 'id')->all();
        // } else {    // is manager
        //     $institutions = Institution::where('id', '=', auth()->user()->inst_id)
        //                                  ->pluck('name', 'id');
        // }
        //
        // return view('users.create', compact('institutions', 'roles'));
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

        // Set role_string for table-view
        $_roles = "";
        $new_user = $user->toArray();
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
}
