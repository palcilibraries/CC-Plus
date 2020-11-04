<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class SeveritiesTableSeeder extends Seeder
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
        $table = $_db . ".severities";

        // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
            DB::table($table)->insert([
        // id's 0-10 are for alerts.
        // Will need to add another column if this gets way more complicated.
            ['id' => 0, 'name' => 'Info'],
            ['id' => 1, 'name' => 'Low'],
            ['id' => 2, 'name' => 'Medium'],
            ['id' => 9, 'name' => 'High'],
        // id's => 10 are for sushi.
        // A 'type' column would make this more explicit, but this is simple for now
            ['id' => 10, 'name' => 'Debug'],
            ['id' => 11, 'name' => 'Warning'],
            ['id' => 12, 'name' => 'Error'],
            ['id' => 99, 'name' => 'Fatal'],
            ]);
        }
    }
}
