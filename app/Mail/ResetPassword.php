<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $consortium;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($consortium, $token)
    {
        $this->token = $token;
        $this->consortium = $consortium;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = "CC-Plus password reset request";
        return $this->subject($subject)->view('email/forgotPassword');
    }
}
