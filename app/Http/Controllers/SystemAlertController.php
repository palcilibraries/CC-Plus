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
        $input = $request->all();
        $alert = SystemAlert::create($input);
        return response()->json(['result' => true, 'alert' => $alert]);
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
         $this->validate($request, ['severity' => 'required', 'text' => 'required']);
         $input = $request->all();

        // Update it
         $alert->update($input);
         return response()->json(['result' => true, 'alert' => $alert]);
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
