<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

       // If consodb not set, seed the template database
        $_db = \Config::get('database.connections.consodb.database');
        if ($_db == 'db-name-isbad'  || $_db == '') {
            $_db = \Config::get('database.connections.con_template.database');
        }
        $table = $_db . '.roles';

       // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
            DB::table($table)->insert([
            ['id' =>  1, 'name' => 'User'],
            ['id' => 25, 'name' => 'Manager'],
            ['id' => 50, 'name' => 'Viewer'],
            ['id' => 99, 'name' => 'Admin'],
            ['id' => 999, 'name' => 'GlobalAdmin']
            ]);
        }
    }
}
