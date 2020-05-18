<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Institution;
use App\InstitutionType;
use App\InstitutionGroup;
use App\Provider;
use App\Role;

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
            $institutions = Institution::with('institutionType','institutionGroups')->orderBy('name', 'ASC')
                                       ->get(['id','name','type_id','is_active']);

            $data = array();
            foreach ($institutions as $inst) {
                $_groups = "";
                foreach ($inst->institutionGroups as $group) {
                    $_groups .= $group->name . ", ";
                }
                $i_data = $inst->toArray();
                $i_data['type'] = $inst->institutionType->name;
                $i_data['groups'] = rtrim(trim($_groups), ',');
                $data[] = $i_data;
            }
            $types = InstitutionType::get(['id','name'])->toArray();
            $all_groups = InstitutionGroup::get(['id','name'])->toArray();

            return view('institutions.index', compact('data', 'types', 'all_groups'));
        } else {    // not admin, load the edit view for user's inst
            return redirect()->route('institutions.show', auth()->user()->inst_id);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // abort_unless(auth()->user()->hasRole("Admin"), 403);
        // $types = InstitutionType::pluck('name', 'id')->all();
        // $groups = InstitutionGroup::pluck('name', 'id')->all();
        //
        // return view('institutions.create', compact('types', 'groups'));
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
            return response()->json(['result' => false, 'msg' => 'Create failed (403) - Forbidden']);
        }
        $this->validate($request, [
          'name' => 'required',
        ]);
        $input = $request->all();
        $institution = Institution::create($input);
        $new_id = $institution->id;

        // Attach groups and build a string of the names
        $_groups = "";
        if (isset($input['institutiongroups'])) {
            foreach ($request->input('institutiongroups') as $g) {
                $institution->institutionGroups()->attach($g);
                $group = InstitutionGroup::where('id',$g)->first();
                $_groups .= ($group) ? $group->name . ", " : "";
            }
        }

        // Setup a return object that matches what index does (above)
        $data = Institution::where('id',$new_id)->get(['id','name','type_id','is_active'])->first()->toArray();
        $data['type'] = $institution->institutionType->name;
        $data['groups'] = rtrim(trim($_groups), ',');

        return response()->json(['result' => true, 'msg' => 'Institution successfully created',
                                 'institution' => $data]);
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
            abort_unless(auth()->user()->inst_id == $id, 403);
        }

        $institution = Institution::
                with('institutionType', 'sushiSettings', 'sushiSettings.provider', 'users', 'users.roles')
                ->find($id);

        // Add user's highest role as "permission" as a separate array
        // $u_data = User::where('inst_id',$id)->with('roles')->orderBy('id', 'ASC')->get();
        $users = array();
        foreach ($institution->users as $inst_user) {
            $new_u = $inst_user->toArray();
            $new_u['permission'] = $inst_user->maxRoleName();
            array_push($users, $new_u);
        }

        // Related models we'll be passing
        $types = InstitutionType::get(['id','name'])->toArray();
        $all_groups = InstitutionGroup::get(['id','name'])->toArray();
        $inst_groups = $institution->institutionGroups()->pluck('institution_group_id')->all();

        // Roles are limited to current user's max role
        $all_roles = Role::where('id', '<=', auth()->user()->maxRole())->get(['name', 'id'])->toArray();

        // Get id+name pairs for accessible providers without settings
        $set_provider_ids = $institution->sushiSettings->pluck('prov_id');
        $unset_providers = Provider::whereNotIn('id', $set_provider_ids)
                           ->where(function ($query) use ($id) {
                               $query->where('inst_id', 1)->orWhere('inst_id', $id);
                           })
                           ->orderBy('id', 'ASC')->get(['id','name'])->toArray();
        return view(
            'institutions.show',
            compact('institution', 'users', 'unset_providers', 'types', 'inst_groups', 'all_groups', 'all_roles')
        );
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
            abort_unless(auth()->user()->inst_id == $id, 403);
        }
        $institution = Institution::with('institutionType')->findOrFail($id);

        $types = InstitutionType::get(['id','name'])->toArray();
        $all_groups = InstitutionGroup::get(['id','name'])->toArray();
        $providers = Provider::orderBy('id', 'ASC')->get(['id','name'])->toArray();
        $inst_groups = $institution->institutionGroups()->pluck('institution_group_id')->all();

        // Roles are limited to current user's max role
        $all_roles = Role::where('id', '<=', auth()->user()->maxRole())->get(['name', 'id'])->toArray();

        return view('institutions.edit', compact(
            'institution',
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

        return response()->json(['result' => true, 'msg' => 'Settings successfully updated']);
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

        try {
            $institution->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }

        return response()->json(['result' => true, 'msg' => 'Institution successfully deleted']);
    }
}
