<?php

namespace App\Http\Controllers;

use App\Provider;
use App\Institution;
use App\Report;
use Illuminate\Http\Request;
//Enables us to output flash messaging
use Session;

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
        $this->middleware(['role:Admin,Manager']);
        $data = Provider::orderBy('name', 'ASC')->paginate(10);

        return view('providers.index', compact('data'))
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
        if (auth()->user()->hasRole("Admin")) {
            $institutions = Institution::pluck('name', 'id')->all();
            $institutions[1] = 'Entire Consortium';
        } else {    // is manager
            $institutions = Institution::where('id', '=', auth()->user()->inst_id)
                                       ->pluck('name', 'id');
        }
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
        $this->middleware(['role:Admin,Manager']);

        $this->validate($request, [
          'name' => 'required'
        ]);
        $input = $request->all();
        $provider = Provider::create($input);

        return redirect()->route('providers.index')
                      ->with('success', 'Provider created successfully');
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
        abort_unless($provider->canManage(), 403);
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
        $provider = Provider::findOrFail($id);
        // abort_unless($provider->canManage(), 403);
        $provider_reports = $provider->reports()->pluck('report_id')->all();
        $_prov = $provider->toArray();

       // Build $institutions based on whether the provider is
       // Inst-specific and/or whether user is admin or Manager
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
        abort_unless($provider->canManage(), 403);
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

        // return redirect()->route('providers.index')
        //                ->with('success', 'Provider updated successfully');
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
        abort_unless($provider->canManage(), 403);
        $provider->delete();

        return redirect()->route('providers.index')
                      ->with('success', 'Provider deleted successfully');
    }
}
