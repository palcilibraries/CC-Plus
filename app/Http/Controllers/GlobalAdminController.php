<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Consortium;
use App\GlobalSetting;
use Hash;

class GlobalAdminController extends Controller
{

    public function __construct()
    {
        $this->middleware(['auth','role:SuperUser']);
    }

    /**
     * Index method for GlobalAdmin Controller
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $consortia = Consortium::orderby('name')->get();
        return view('globaladmin.home', compact('consortia'));
    }

}
