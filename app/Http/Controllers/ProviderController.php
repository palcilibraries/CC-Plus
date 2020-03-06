<?php

namespace App\Http\Controllers;

use DB;
use App\Provider;
use App\Institution;
use App\Report;
use Illuminate\Http\Request;

class ProviderController extends Controller
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
        $p_table = config('database.connections.consodb.database') . ".providers";
        $i_table = config('database.connections.consodb.database') . ".institutions";
       // Admins get list of all providers
        if (auth()->user()->hasRole("Admin")) {
            $data = DB::table($p_table.' as prv')
                      ->join($i_table .' as inst', 'inst.id', '=', 'prv.inst_id')
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                             'prv.inst_id','inst.name as inst_name','day_of_month']);
       // Otherwise, get all consortia-wide providers and those that match user's inst_id
       // (exclude providers assigned to institutions.)
        } else  {
            $data = DB::table($p_table.' as prv')
                      ->join($i_table .' as inst', 'inst.id', '=', 'prv.inst_id')
                      ->where('prv.inst_id', 1)
                      ->orWhere('prv.inst_id', auth()->user()->inst_id)
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                             'prv.inst_id','inst.name as inst_name','day_of_month']);
        }
        return view('providers.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort_unless(auth()->user()->hasRole("Admin"), 403);
        $institutions = Institution::pluck('name', 'id')->all();
        $institutions[1] = 'Entire Consortium';

        return view('providers.create', compact('institutions'));
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
          'name' => 'required'
        ]);
        $input = $request->all();
        $provider = Provider::create($input);

        return response()->json(['result' => true, 'msg' => 'Provider successfully created']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $provider = Provider::findOrFail($id);
        return view('providers.show', compact('provider'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
       // Limit edit form access to admins and managers
        abort_unless(auth()->user()->hasAnyRole(["Admin","Manager"]), 403);
        $provider = Provider::findOrFail($id);
        $provider_reports = $provider->reports()->pluck('report_id')->all();
        $_prov = $provider->toArray();

       // Build $institutions based on whether the user is admin or Manager
        if (auth()->user()->hasRole("Admin")) {
            $institutions = Institution::orderBy('id', 'ASC')->get(['id','name'])->toArray();
            $sushi_insts = $institutions;
            $institutions[0]['name'] = 'Entire Consortium';
            array_shift($sushi_insts);  // toss off "Entire Consortium" as an option for sushi settings
        } else {  // Manager limited their own inst
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)->get(['id','name'])->toArray();
            $sushi_insts = $institutions;
        }

       // Get all R5 master reports and which are currently enabled
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)
                                 ->get(['id','name'])->toArray();

        return view('providers.edit', compact(
            'provider',
            '_prov',
            'institutions',
            'master_reports',
            'provider_reports',
            'sushi_insts'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $provider = Provider::findOrFail($id);
        if (!$provider->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

      // Validate form inputs
        $this->validate($request, [
            'name' => 'required',
            'is_active' => 'required',
            'inst_id' => 'required',
        ]);
        $input = $request->all();

      // Update the record and assign reports in master_reports
        $provider->update($input);
        $provider->reports()->detach();
        if (!is_null($request->input('master_reports'))) {
            foreach ($request->input('master_reports') as $r) {
                $provider->reports()->attach($r);
            }
        }
        return response()->json(['result' => true, 'msg' => 'Provider settings successfully updated']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $provider = Provider::findOrFail($id);
        if (!$provider->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $provider->delete();
        return response()->json(['result' => true, 'msg' => 'Provider successfully deleted']);
    }
}
