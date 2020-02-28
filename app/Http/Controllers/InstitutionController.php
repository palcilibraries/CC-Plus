<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Institution;
use App\InstitutionType;
use App\InstitutionGroup;
use App\Provider;
//Enables us to output flash messaging
use Session;

class InstitutionController extends Controller
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
        $groups = InstitutionGroup::pluck('name', 'id');
        if (auth()->user()->hasRole("Admin")) { // show them all
            $data = Institution::orderBy('name', 'ASC')->paginate(5);
            return view('institutions.index', compact('data', 'groups'))
               ->with('i', ($request->input('page', 1) - 1) * 10);
        } else {    // is manager, load the edit view
            return redirect()->route('institutions.edit', auth()->user()->inst_id);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->middleware(['role:Admin']);
        $types = InstitutionType::pluck('name', 'id')->all();
        $groups = InstitutionGroup::pluck('name', 'id')->all();

        return view('institutions.create', compact('types', 'groups'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->middleware(['role:Admin']);
        $this->validate($request, [
          'name' => 'required',
        ]);
        $input = $request->all();
        $institution = Institution::create($input);

        return redirect()->route('institutions.index')
                      ->with('success', 'Institution created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $institution = Institution::findOrFail($id);
        abort_unless($institution->canManage(), 403);
        $groups = InstitutionGroup::pluck('name', 'id');

        return view('institutions.show', compact('institution', 'groups'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $institution = Institution::findOrFail($id);
        abort_unless($institution->canManage(), 403);
        $_inst = $institution->toArray();

        $types = InstitutionType::get(['id','name'])->toArray();
        $all_groups = InstitutionGroup::get(['id','name'])->toArray();
        $providers = Provider::orderBy('id', 'ASC')->get(['id','name'])->toArray();
        $inst_groups = $institution->institutionGroups()->pluck('institution_group_id')->all();

        return view('institutions.edit', compact(
            'institution',
            '_inst',
            'types',
            'all_groups',
            'inst_groups',
            'providers'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $institution = Institution::findOrFail($id);
        abort_unless($institution->canManage(), 403);

       // Validate form inputs
        $this->validate($request, [
            'name' => 'required',
            'is_active' => 'required',
            'type_id' => 'required',
        ]);
        $input = $request->all();

       // Update the record and assign groups
        $institution->update($input);
        $institution->institutionGroups()->detach();
        if (isset($input['institutiongroups'])) {
            foreach ($request->input('institutiongroups') as $g) {
                $institution->institutionGroups()->attach($g);
            }
        }

        // return redirect()->route('institutions.index')
        //                ->with('success', 'Institution updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->middleware(['role:Admin']);
        $institution = Institution::findOrFail($id);
        $institution->delete();

        return redirect()->route('institutions.index')
                      ->with('success', 'Institution deleted successfully');
    }
}
