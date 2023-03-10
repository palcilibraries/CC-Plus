<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Consortium;
use DB;

class ServerAdminController extends Controller
{

    public function __construct()
    {
        $this->middleware(['auth','role:ServerAdmin']);
    }

    /**
     * Index method for ServerAdmin Controller
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $consortia = Consortium::orderby('name')->get();
        return view('serveradmin.home', compact('consortia'));
    }

    /**
     * Change instnance method for ServerAdmin Controller
     *
     * @return \Illuminate\Http\Response
     */
    public function changeInstance(Request $request)
    {

        // Get input arguments from the request
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => 'Error decoding input!']);
        }

        // Update the active configuration and the sesttion to use the new key
        $conso_db = "ccplus_" . $input['ccp_key'];
        config(['database.connections.consodb.database' => $conso_db]);
        session(['ccp_con_key' => $input['ccp_key']]);
        try {
            DB::reconnect('consodb');
        } catch (\Exception $e) {
            return response()->json(['result' => 'Error decoding input!']);
        }
        return response()->json(['result' => 'success']);
    }
}
