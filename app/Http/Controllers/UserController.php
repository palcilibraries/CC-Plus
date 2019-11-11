<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Role;
use App\Institution;
use Hash;
//Enables us to output flash messaging
use Session;

// auth()->id()  : helper function returns an ID
// auth()->user() : returns a full user instance
// auth()->check() : returns a boolean for signed in or not
// auth()->guest() : boolean for guest (similar to check())

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
        $this->middleware(['role:Admin,Manager']);
        $roles = Role::pluck('name')->all();

        if (auth()->user()->hasRole("Admin")) {
            $data = User::orderBy('ID', 'ASC')->paginate(5);
        } else {    // is manager
            $data = User::orderBy('ID', 'ASC')->where('inst_id', '=', auth()->user()->inst_id)->paginate(5);
        }

        return view('users.index', compact('data', 'roles'))
               ->with('i', ($request->input('page', 1) - 1) * 10);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->middleware(['role:Admin,Manager']);
        // Disallow choosing roles higher current user's max role
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

        $this->middleware(['role:Admin,Manager']);
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

        return redirect()->route('users.index')
                        ->with('success', 'User created successfully');
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
            $institutions = Institution::pluck('name', 'id')->all();
        } else {
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)
                                       ->pluck('name', 'id');
        }

        // Set choices for roles; disallow choosing roles higher current user's max role
        $roles = Role::where('id', '<=', auth()->user()->maxRole())->pluck('name', 'id');
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
        abort_unless($user->canManage(), 403);

        // Validate form inputs
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:consodb.users,email,' . $id,
            'password' => 'same:confirm-password',
            'roles' => 'required',
            'inst_id' => 'required'
        ]);
        $request['is_active'] = isset($request['is_active']) ? 1 : 0;
        $input = $request->all();
        if (empty($input['password'])) {
            $input = array_except($input, array('password'));
        }
        $input = array_except($input, array('confirm-password'));

        // Update the record and assign roles
        $user->update($input);
        $user->roles()->detach();
        foreach ($request->input('roles') as $r) {
            if (auth()->user()->maxRole() >= $r) {
                $user->roles()->attach($r);
            }
        }

        return redirect()->route('users.index')
                         ->with('success', 'User updated successfully');
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
        abort_unless($user->canManage(), 403);

        if (auth()->id() == $id) {
            abort(403, 'Suicide not authorized; find an Admin or Manager to assist you.');
        }

        $user->delete();

        return redirect()->route('users.index')
                      ->with('success', 'User deleted successfully');
    }
}
