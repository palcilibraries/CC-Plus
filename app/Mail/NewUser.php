<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewUser extends Mailable
{
    use Queueable, SerializesModels;

    public $consortium;
    public $user_data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($consortium, $user_data)
    {
        $this->consortium = $consortium;
        $this->user_data = $user_data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = "Your new CC-Plus account";
        return $this->subject($subject)->view('email/newUser');
    }
}
