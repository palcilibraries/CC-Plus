<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin,Manager');
    }

    //Index method for Admin Controller
    public function index()
    {
        return view('admin.dashboard');
    }
}
