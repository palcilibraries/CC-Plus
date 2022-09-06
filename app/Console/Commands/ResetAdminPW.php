<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Consortium;
use DB;
use Hash;

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
       // Make sure the password is set in .env
        $server_admin = config('ccplus.server_admin');
        $server_admin_pass = config('ccplus.server_admin_pass');
        if (strlen($server_admin) == 0 || strlen($server_admin_pass) == 0) {
            $this->error('Global Administrator credential not properly defined!');
            return 0;
        }

       // Default to NOT updating the template database
        $update_con_template = false;

       // If ID or Key given as input, go get it
        $conarg = $this->argument('consortium');
        if ($conarg) {
            $consortia = array();
            $databases = array();
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
                $consortia[] = $consortium;
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
                $databases = "ccplus_" . $con->con_key;
            }
            $update_con_template = true;
        }

       // If we're updating the template, add it the list of databases
        if ($update_con_template) $databases[] = "ccplus_con_template";

       // Update all passwords for the requested databases
        foreach ($databases as $_db) {
           $pw_qry  = "UPDATE " . $_db . ".users SET password = '" . Hash::make($server_admin_pass);
           $pw_qry .= "' where email='" . $server_admin . "'";
           $result = DB::statement($pw_qry);
           $this->line('<fg=cyan>' . $_db . ' Successfully Updated.');
        }

        // If we've reset the template, reset the role_user table to just the superAdmin role
        // (to ensure that created consortia inherit the roles settings too)
        if ($update_con_template) {
            config(['database.connections.consodb.database' => 'ccplus_con_template']);
            $super_role = \App\Role::where('name','SuperUser')->first();
            $user = \App\User::where('email', $server_admin)->first();
            if ($super_role && $user) {
                $user->roles()->detach();
                $user->roles()->attach($super_role->id);
            }
        }

        return 1;
    }
}
