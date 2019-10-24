<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GlobalAdminController extends Controller
{
    
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:GlobalAdmin');
    }

    //Index method for GlobalAdmin Controller
    public function index()
    {
        return view('globaladmin.home');
    }
}
