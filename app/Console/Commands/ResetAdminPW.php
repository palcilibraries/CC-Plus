<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Consortium;
use App\GlobalSetting;
use DB;
use Hash;
use Route;

class ResetAdminPW extends Command
{
    /**
     * The name and signature for the Sushi Batch processing console command.
     * @var string
     */
    protected $signature = "ccplus:resetadminpw {consortium? : A Consortium ID, key-string, or 'template'}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset Server Admin password in Consortium Databases';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
       // Make sure the username is set
        $server_admin = config('ccplus.server_admin');
        if (strlen($server_admin) == 0 ) {
            $this->error('Server Administrator username is not properly defined (verify or reseed the server settings table) !');
            return 0;
        }
        // Prompt for a new password value
        $server_admin_pass = $this->ask("Enter a new password for the '" . $server_admin . "' user (required) ", false);
        if (!$server_admin_pass) {
            $this->error('A new password value is required.');
            return 0;
        }

       // Default to NOT updating the template database
        $update_con_template = false;

       // If ID or Key given as input, go get it
        $databases = array();
        $conarg = $this->argument('consortium');
        if ($conarg) {
           // Allo update to just the template database
            if ($conarg == 'template') {
                $update_con_template = true;
            } else {
                $consortium = Consortium::find($conarg);
                if (is_null($consortium)) {
                    $consortium = Consortium::where('ccp_key', '=', $conarg)->first();
                }
                if (is_null($consortium)) {
                    $this->line('Cannot Find Consortium: ' . $conarg);
                    return 0;
                }
                $databases[] = "ccplus_" . $consortium->ccp_key;
            }
       // User wants to update all the consortia?
        } else {
            $confirmed = $this->ask('Reset all consortia system-wide, including the template [Y]?');
            if ($confirmed == "") {
                $confirmed = "Y";
            }
            if ($confirmed != 'Y') {
                $this->line('Exiting with no changes applied.');
                return 0;
            }
            // Get all the IDs as an array
            $consortia = Consortium::get();
            foreach ($consortia as $con) {
                $databases[] = "ccplus_" . $con->ccp_key;
            }
            $update_con_template = true;
        }

       // Update the value in the global_settings table
        $hashed_password = Hash::make($server_admin_pass);
        $_setting = GlobalSetting::where('name', 'server_admin_pass')->first();
        if (!$_setting) {
            $this->error('Cannot load global admin setting from database!');
            return 0;
        }
        $_setting->value = $hashed_password;
        $_setting->save();

       // If we're updating the template, add it the list of databases
        if ($update_con_template) $databases[] = "ccplus_con_template";

       // Update all passwords for the requested databases
        foreach ($databases as $_db) {
           $pw_qry  = "UPDATE " . $_db . ".users SET password = '" . $hashed_password;
           $pw_qry .= "' where email='" . $server_admin . "'";
           $result = DB::statement($pw_qry);
           $this->line('<fg=cyan>' . $_db . ' Successfully Updated.');
        }

        // If we've reset the template, reset the role_user table to just the GlobalAdmin role
        // (to ensure that created consortia inherit the roles settings too)
        if ($update_con_template) {
            config(['database.connections.consodb.database' => 'ccplus_con_template']);
            $globalAdminRole = \App\Role::where('name','GlobalAdmin')->first();
            $user = \App\User::where('email', $server_admin)->first();
            if ($globalAdminRole && $user) {
                $user->roles()->detach();
                $user->roles()->attach($globalAdminRole->id);
            }
        }

        //Clear config cache:
        $exitCode = Artisan::call('config:cache');

        return 1;
    }
}
