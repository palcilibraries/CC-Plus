<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Role;

class RoleController extends Controller
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
        $data = Role::orderBy('id', 'DESC')->paginate(5);
        return view('roles.index', compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('roles.create');
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
          'id' => 'required|unique:consodb.roles,id',
          'name' => 'required|unique:consodb.roles,name',
        ]);
        $input = $request->all();
        $role = Role::create($input);

        return redirect()->route('roles.index')
                        ->with('success', 'Role created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $role = Role::findOrFail($id);
        return view('roles.edit', compact('role'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $role = Role::findOrFail($id);
        return view('roles.edit', compact('role'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $this->validate($request, [
          'name' => 'required',
        ]);
        $role->name = $request->input('name');
        $role->save();

        return redirect()->route('roles.index')
                       ->with('success', 'Role updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return redirect()->route('roles.index')
                       ->with('success', 'Role deleted successfully');
    }
}
