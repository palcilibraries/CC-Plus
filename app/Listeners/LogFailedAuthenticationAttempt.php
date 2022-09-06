<?php

namespace App\Listeners;

use Storage;
use Illuminate\Auth\Events\Failed;

class LogFailedAuthenticationAttempt
{
    /*
     * Handle the event.
     *
     * @param  Failed  $event
     * @return void
     */
    public function handle(Failed $event)
    {
        if (config('ccplus.log_login_fails')) {
            $message = "";
            $prefix = " :: Consortium " . session('ccp_con_key') . " :: ";
            foreach ($event->credentials as $key => $cred) {
              $message .= ($message == "") ? "" : ", ";
              $message .= $key . "=" . $cred;
            }
            Storage::append('login_fails.log', date('Y-m-d H:is') . $prefix . $message);
        }
    }
}
