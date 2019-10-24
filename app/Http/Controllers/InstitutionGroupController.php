<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\InstitutionGroup;

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
        $group = InstitutionGroup::findOrFail($id);
        return view('institutiongroups.edit', compact('group'));
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
        $group->name = $request->input('name');
        $group->save();

        return redirect()->route('institutiongroups.index')
                      ->with('success', 'Institution Group updated successfully');
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
}
