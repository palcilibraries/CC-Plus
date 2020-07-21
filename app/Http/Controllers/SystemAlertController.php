<?php

namespace App\Http\Controllers;

use App\SystemAlert;
use Illuminate\Http\Request;

class SystemAlertController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);

       // Validate form inputs
        $this->validate($request, ['is_active' => 'required', 'severity_id' => 'required', 'text' => 'required']);

        $input = $request->all();
        $alert = SystemAlert::create($input);

        // return all via JSON as a sorted object
        $alerts = SystemAlert::with('severity')->orderBy('severity_id', 'DESC')->orderBy('updated_at','DESC')->get();
        return response()->json(['result' => true, 'alerts' => $alerts]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\SystemAlert  $systemAlert
     * @return \Illuminate\Http\Response
     */
    public function show(SystemAlert $systemAlert)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\SystemAlert  $systemAlert
     * @return \Illuminate\Http\Response
     */
    public function edit(SystemAlert $systemAlert)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  SystemAlert  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
         abort_unless(auth()->user()->hasRole('Admin'), 403);
         $alert = SystemAlert::findOrFail($id);

        // Validate form inputs
         $this->validate($request, ['is_active' => 'required', 'severity_id' => 'required', 'text' => 'required']);
         $input = $request->all();

        // Update it
         $alert->update($input);

        // return all via JSON as a sorted object
         $alerts = SystemAlert::with('severity')->orderBy('severity_id', 'DESC')->orderBy('updated_at','DESC')->get();
         return response()->json(['result' => true, 'alert' => $alerts]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\SystemAlert  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
        $alert = SystemAlert::findOrFail($id);

        try {
            $alert->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }
        return response()->json(['result' => true]);
    }
}
