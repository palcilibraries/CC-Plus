<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use DB;
use Mail;
use Hash;
use App\User;

// CC+ needs to include the consortium as a part of validating and handling users,
// so we're not using the handy-dandy Laravel trait for doing this
class ForgotPasswordController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Display forgot password form
     *
     * @return response()
    */
    public function showForgotForm()
    {
        if (env('MAIL_HOST') == "smtp.mailtrap.io" && is_null(env('MAIL_USERNAME')) ) {
            return back()->withInput()->with('error', 'Email service has not yet been properly configured.');
        }
       return view('auth.forgotPassword');
    }

    /**
     * Accept (POST) input from the forgot password form
     *
     * @return response()
     */
    public function submitForgotForm(Request $request)
    {
        // consortium will just be passed along, but if it's missing in the request then the middleware
        // never saw it .. so nothing else will work beyond this .. so require it now.
        $request->validate([ 'email' => 'required|email|exists:consodb.users', 'consortium' => 'required' ]);

        $resets_table = config('database.connections.consodb.database') . ".password_resets";
        $token = Str::random(64);
        DB::table($resets_table)
          ->insert([ 'email' => $request->email, 'token' => $token, 'created_at' => Carbon::now() ]);
        Mail::to($request->email)->send(new \App\Mail\ResetPassword($request->consortium, $token));
        return back()->with('message', 'Password reset link has been sent to your email address');
    }

    /**
     * Display the form for resetting user password
     *
     * @return response()
     */
    public function showResetForm($consortium, $token) {
        return view('auth.forgotPasswordLink', ['token' => $token, 'consortium' => $consortium]);
    }

    /**
     * Accept (POST) input from the reset password form
     *
     * @return response()
     */
    public function submitResetForm(Request $request)
    {
        // consortium isn't used here, but if it's missing in the request then the middleware
        // never saw it .. so the update will fail .. so require it now.
        $request->validate([
            'email' => 'required|email|exists:consodb.users',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required',
            'consortium' => 'required'
        ]);
        $resets_table = config('database.connections.consodb.database') . ".password_resets";
        $updatePassword = DB::table($resets_table)->where(['email' => $request->email, 'token' => $request->token])
                            ->first();
        if (!$updatePassword) {
            return back()->withInput()->with('error', 'Invalid token - Password reset failed!');
        }
        $user = User::where('email', $request->email)->update(['password' => Hash::make($request->password)]);
        DB::table($resets_table)->where(['email'=> $request->email])->delete();
        return redirect('/login')->with('message', 'Your password has been successfully updated!');
    }
}
