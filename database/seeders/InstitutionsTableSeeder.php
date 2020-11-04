<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class InstitutionsTableSeeder extends Seeder
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
        if ($_db == 'db-name-isbad' || $_db == '') {
            $_db = \Config::get('database.connections.con_template.database');
        }
        $table = $_db . '.institutions';

     // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
            DB::table($table)->insert(['name' => 'Consortium Staff','type_id' => 1, 'is_active' => 1,
                                   'notes' => '']);
        }
    }
}
