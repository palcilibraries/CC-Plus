<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Hash;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login or logout.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /** --lsr : 7/25/19--
     * Override Foundation class login traits from
     * Illuminate\Foundation\Auth\AuthenticatesUsers
     * ---------------------------------------------
     */
    public function login(Request $request)
    {
        // AssignConsortiumDB middleware tests+sets the session variable
        // so we'll be attempting against the right database

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (
            method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)
        ) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        // Check for ServerAdmin credential
        if (!is_null(config('ccplus.global_admin')) &&
            !is_null(config('ccplus.global_admin_pass')) &&
            $request->email == config('ccplus.global_admin') &&
            Hash::check($request->password, config('ccplus.global_admin_pass'))
           ) {
            if ($this->attemptLogin($request)) {
                return $this->sendLoginResponse($request);
            }
        }

        if ($this->attemptLogin($request)) {
            if (Auth::user()->is_active) {
                return $this->sendLoginResponse($request);
            } else {    // Credentials right, but account marked inactive
                $this->incrementLoginAttempts($request);
                return response()->json([ 'error' => 'This account is not active.'], 401);
            }
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);
        return $this->sendFailedLoginResponse($request);
    }

    protected function authenticated(Request $request, $user)
    {
        $user->last_login = now();
        $user->save();
        if ($user->email == config('ccplus.global_admin')) {
            return redirect("/global/instances");
        } else {
            return redirect("/");
        }
    }

    public function logout(Request $request) {
        Auth::logout();
        session(['ccp_con_key' => '']);
        $request->session()->invalidate();
        return redirect('/login');
    }
}
