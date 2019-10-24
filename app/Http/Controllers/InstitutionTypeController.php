<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\InstitutionType;

class InstitutionTypeController extends Controller
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
        $data = InstitutionType::orderBy('id', 'DESC')->paginate(10);
        return view('institutiontypes.index', compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 10);
    }

      /**
       * Show the form for creating a new resource.
       *
       * @return \Illuminate\Http\Response
       */
    public function create()
    {
        return view('institutiontypes.create');
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
            'name' => 'required|unique:consodb.institutiontypes,name',
        ]);
        $type = InstitutionType::create(['name' => $request->input('name')]);

        return redirect()->route('institutiontypes.index')
                        ->with('success', 'Institution Type created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $type = InstitutionType::findOrFail($id);
        return view('institutiontypes.edit', compact('type'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $type = InstitutionType::findOrFail($id);
        return view('institutiontypes.edit', compact('type'));
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
        $type = InstitutionType::findOrFail($id);
        $this->validate($request, [
          'name' => 'required',
        ]);
        $type->name = $request->input('name');
        $type->save();

        return redirect()->route('institutiontypes.index')
                      ->with('success', 'Institution Type updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $type = InstitutionType::findOrFail($id);
        $type->delete();
        return redirect()->route('institutiontypes.index')
                      ->with('success', 'Institution Type deleted successfully');
    }
}
