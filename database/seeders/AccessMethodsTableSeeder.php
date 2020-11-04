<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class AccessMethodsTableSeeder extends Seeder
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
      $table = $_db . ".accessmethods";

     // Make sure table is empty
      if (DB::table($table)->get()->count() == 0) {
          DB::table($table)->insert([
                                ['id' => 1, 'name' => 'Regular'],
                                ['id' => 2, 'name' => 'TDM'],
                             ]);
      }
    }
}
