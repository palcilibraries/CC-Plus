<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GlobalSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Make sure we're talking to the global database
        $_db = \Config::get('database.connections.globaldb.database');
        $table = $_db . ".global_settings";

        // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
            $password = Hash::make('ChangeMeNow!');
            DB::table($table)->insert([
                                 ['id' => 1, 'name' => 'root_url', 'value' => 'http://localhost/'],
                                 ['id' => 2, 'name' => 'server_admin', 'value' => 'ServerAdmin'],
                                 ['id' => 3, 'name' => 'server_admin_pass', 'value' => $password],
                                 ['id' => 4, 'name' => 'reports_path', 'value' => '/usr/local/stats_reports/'],
                                 ['id' => 5, 'name' => 'cookie_life', 'value' => '90'],
                                 ['id' => 6, 'name' => 'silence_days', 'value' => '10'],
                                 ['id' => 7, 'name' => 'max_harvest_retries', 'value' => '10'],
                                 ['id' => 8, 'name' => 'max_name_length', 'value' => '191'],
                                 ['id' => 9, 'name' => 'log_login_fails', 'value' => '0'],
                                 ['id' => 10, 'name' => 'debug_SQL_queries', 'value' => '0'],
                              ]);
        }
    }
}
