<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Institution;
use App\InstitutionType;
use App\InstitutionGroup;
use App\Provider;

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
        $groups = InstitutionGroup::pluck('name', 'id');
        if (auth()->user()->hasRole("Admin")) { // show them all
            $institutions = Institution::orderBy('name', 'ASC')->get();
            $data = array();
            foreach ($institutions as $inst) {
                $_groups = "";
                foreach ($inst->institutionGroups()->get() as $group) {
                    $_groups .= $group->name . ", ";
                }
                $_groups = rtrim(trim($_groups),',');
                $i_data = array(
                    "id" => $inst->id,
                    "name" => $inst->name,
                    "type" => $inst->institutionType->name,
                    "is_active" => $inst->is_active,
                    "groups" => $_groups
                );
                $data[] = $i_data;
            }
            return view('institutions.index', compact('data'));

        } else {    // not admin, load the edit view for user's inst
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
        abort_unless(auth()->user()->hasRole("Admin"), 403);
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
        if (!auth()->user()->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $this->validate($request, [
          'name' => 'required',
        ]);
        $input = $request->all();
        $institution = Institution::create($input);

        return response()->json(['result' => true, 'msg' => 'Institution successfully created']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!auth()->user()->hasRole("Admin")) {
            abort_unless(auth()->user()->inst_id==$id, 403);
        }
        // $institution = Institution::findOrFail($id);
        // $groups = InstitutionGroup::pluck('name', 'id');
        //
        // return view('institutions.show', compact('institution', 'groups'));

        $institution = Institution::findOrFail($id);
        $_inst = $institution->toArray();
        $types = InstitutionType::get(['id','name'])->toArray();
        $all_groups = InstitutionGroup::get(['id','name'])->toArray();
        $providers = Provider::orderBy('id', 'ASC')->get(['id','name'])->toArray();
        $inst_groups = $institution->institutionGroups()->pluck('institution_group_id')->all();

        return view('institutions.show', compact(
            'institution',
            '_inst',
            'types',
            'all_groups',
            'inst_groups',
            'providers'
        ));

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!auth()->user()->hasRole("Admin")) {
            abort_unless(auth()->user()->inst_id==$id, 403);
        }
        $institution = Institution::findOrFail($id);
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
        if (!$institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

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

        return response()->json(['result' => true, 'msg' => 'Institution settings successfully updated']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $institution = Institution::findOrFail($id);
        $institution->delete();

        return response()->json(['result' => true, 'msg' => 'Institution successfully deleted']);
    }
}
