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
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        if (auth()->user()->hasRole("Admin")) {
            $users = User::orderBy('id', 'ASC')->get();
        } else {    // is manager
            $users = User::orderBy('ID', 'ASC')->where('inst_id', '=', auth()->user()->inst_id)->get();
        }

        // Store data elements and roles in an array that simplifies them for Vue
        $data = array();
        foreach ($users as $user) {
            $_roles = "";
            foreach ($user->roles()->get() as $role) {
                $_roles .= $role->name . ", ";
            }
            $_roles = rtrim(trim($_roles),',');
            $u_data = array(
                "id" => $user->id,
                "name" => $user->name,
                "inst" => $user->institution->name,
                "inst_id" => $user->inst_id,
                "is_active" => $user->is_active,
                "email" => $user->email,
                "roles" => $_roles,
                "last_login" => $user->last_login
            );
            $data[] = $u_data;
        }

        return view('users.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        $roles = Role::where('id', '<=', auth()->user()->maxRole())->pluck('name', 'id');

        if (auth()->user()->hasRole("Admin")) {
            $institutions = Institution::pluck('name', 'id')->all();
        } else {    // is manager
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)
                                         ->pluck('name', 'id');
        }

        return view('users.create', compact('institutions', 'roles'));
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
            'password' => 'required|same:confirm-password',
            'inst_id' => 'required',
            'roles' => 'required'
        ]);

        $input = $request->all();
        $input['password'] = Hash::make($input['password']);
        if (!auth()->user()->hasRole("Admin")) {     // managers only store to their institution
            $input['inst_id'] = auth()->user()->inst_id;
        }

        $user = User::create($input);
        foreach ($request->input('roles') as $r) {
            $user->roles()->attach($r);
        }

        return response()->json(['result' => true, 'msg' => 'User successfully created']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
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
        $user = User::findOrFail($id);
        abort_unless($user->canManage(), 403);

        // Admin gets a select-box of institutions, otherwise just the users' inst
        if (auth()->user()->hasRole('Admin')) {
            $institutions = Institution::orderBy('id', 'ASC')->get(['id','name'])->toArray();
        } else {
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)
                                       ->get(['id','name'])->toArray();
        }

        // Set choices for roles; disallow choosing roles higher current user's max role
        // $roles = Role::where('id', '<=', auth()->user()->maxRole())->pluck('name', 'id');
        $roles = Role::where('id', '<=', auth()->user()->maxRole())->get(['name', 'id'])->toArray();
        $user_roles = $user->roles()->pluck('role_id')->all();

        return view('users.edit', compact('user', 'roles', 'user_roles', 'institutions'));
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

        // Update the record and assign roles
        $user->update($input);
        $user->roles()->detach();
        foreach ($request->input('roles') as $r) {
            if (auth()->user()->maxRole() >= $r) {
                $user->roles()->attach($r);
            }
        }
        return response()->json(['result' => true, 'msg' => 'User settings successfully updated']);
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
