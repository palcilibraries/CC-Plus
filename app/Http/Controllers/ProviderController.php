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
            $providers = DB::table($p_table . ' as prv')
                      ->join($i_table . ' as inst', 'inst.id', '=', 'prv.inst_id')
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                             'prv.inst_id','inst.name as inst_name','day_of_month']);
       // Otherwise, get all consortia-wide providers and those that match user's inst_id
       // (exclude providers assigned to institutions.)
        } else {
            $providers = DB::table($p_table . ' as prv')
                      ->join($i_table . ' as inst', 'inst.id', '=', 'prv.inst_id')
                      ->where('prv.inst_id', 1)
                      ->orWhere('prv.inst_id', auth()->user()->inst_id)
                      ->orderBy('prov_name', 'ASC')
                      ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                             'prv.inst_id','inst.name as inst_name','day_of_month']);
        }

       // $institutions depends on whether current user is admin or Manager
        if (auth()->user()->hasRole("Admin")) {
            $institutions = Institution::orderBy('id', 'ASC')->get(['id','name'])->toArray();
            $institutions[0]['name'] = 'Entire Consortium';
        } else {  // Managers and Users limited their own inst
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)->get(['id','name'])->toArray();
        }
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)
                                 ->get(['id','name'])->toArray();

        return view('providers.index', compact('providers','institutions','master_reports'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return redirect()->route('providers.index');
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
          'inst_id' => 'required'
        ]);
        $input = $request->all();
        $provider = Provider::create($input);

        // Attach reports
        if (!is_null($request->input('master_reports'))) {
            foreach ($request->input('master_reports') as $r) {
                $provider->reports()->attach($r);
            }
        }

        // Build return object that matches what index does (above)
        $p_table = config('database.connections.consodb.database') . ".providers";
        $i_table = config('database.connections.consodb.database') . ".institutions";
        $data = DB::table($p_table . ' as prv')
                  ->join($i_table . ' as inst', 'inst.id', '=', 'prv.inst_id')
                  ->where('prv.id',$provider->id)
                  ->get(['prv.id as prov_id','prv.name as prov_name','prv.is_active',
                         'prv.inst_id','inst.name as inst_name','day_of_month'])
                  ->first();

        return response()->json(['result' => true, 'msg' => 'Provider successfully created',
                                 'provider' => $data]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
       // Build data to be passed based on whether the user is admin or Manager
        if (auth()->user()->hasRole("Admin")) {
            $provider = Provider::with(['reports:reports.id,reports.name','sushiSettings','sushiSettings.institution'])
                                ->findOrFail($id);
            $institutions = Institution::orderBy('id', 'ASC')->get(['id','name'])->toArray();
            $institutions[0]['name'] = 'Entire Consortium';

            // Setup an array of insts without settings for this provider
            $set_inst_ids = $provider->sushiSettings->pluck('inst_id');
            $set_inst_ids[] = 1;
            $unset_institutions = Institution::whereNotIn('id', $set_inst_ids)
                                             ->orderBy('id', 'ASC')->get(['id','name'])->toArray();
        } else {  // Managers/Users are limited their own inst
            $provider = Provider::with(['reports:reports.id,reports.name',
                                        'sushiSettings' => function ($query) {
                                            $query->where('inst_id', '=', auth()->user()->inst_id);
                                        },
                                        'sushiSettings.institution'])->findOrFail($id);
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)->get(['id','name'])->toArray();
            $unset_institutions = array();
            if (count($provider->sushiSettings) == 0) {
                $unset_institutions[] = Institution::where('id', auth()->user()->inst_id)->first()->toArray();
            }
        }
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)
                                 ->get(['id','name'])->toArray();

        return view('providers.show', compact('provider', 'institutions', 'unset_institutions', 'master_reports'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return redirect()->route('providers.show', [$id]);
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

        $provider->load('reports:reports.id,reports.name');
        return response()->json(['result' => true, 'msg' => 'Provider settings successfully updated',
                                 'provider' => $provider]);
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

        try {
            $provider->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }

        return response()->json(['result' => true, 'msg' => 'Provider successfully deleted']);
    }
}
