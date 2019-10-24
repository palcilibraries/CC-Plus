<?php

// app/Http/Controllers/ConsortiumController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Consortium;

class ConsortiumController extends Controller
{

    public function __construct()
    {
        // governed by policy...
        // $this->middleware(['auth']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $consortia = Consortium::orderby('name')->paginate(10); // limit to 10 at a time

        return view('consortia.index', compact('consortia'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('consortia.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //Validating title and body field
        $this->validate($request, [
            'ccp_key' => 'required|max:10',
            'name' => 'required',
            ]);

        $consortium = Consortium::create($request->only('ccp_key', 'name', 'email', 'is_active'));

        //Display a successful message upon save
        return redirect()->route('consortia.index')
            ->with('flash_message', 'Consortium ' . $consortium->name .
                   ' created with key: ' . $consortium->ccp_key);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $consortium = Consortium::findOrFail($id); //Find consortium w/ id = $id

        return view('consortia.show', compact('consortium'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $consortium = Consortium::findOrFail($id);

        return view('consortia.edit', compact('consortium'));
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
        $this->validate($request, [
            'ccp_key' => 'required|max:10',
            'name' => 'required',
        ]);

        $consortium = Consortium::findOrFail($id);
        $consortium->ccp_key = $request->input('ccp_key');
        $consortium->name = $request->input('name');
        $consortium->email = $request->input('email');
        $consortium->is_active = $request->has('is_active');
        $consortium->save();

        return redirect()->route(
            'consortia.show',
            $consortium->id
        )->with(
            'flash_message',
            'Consortium: ' . $consortium->name . ' updated'
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $consortium = Consortium::findOrFail($id);
        $consortium->delete();

        return redirect()->route('consortia.index')
            ->with(
                'flash_message',
                'Consortium successfully deleted!'
            );
    }
}
