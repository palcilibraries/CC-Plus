<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class SectionTypesTableSeeder extends Seeder
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
      $table = $_db . ".sectiontypes";

     // Make sure table is empty
      if (DB::table($table)->get()->count() == 0) {
          DB::table($table)->insert([
                                ['id' => 1, 'name' => ' '],
                                ['id' => 2, 'name' => 'Article'],
                                ['id' => 3, 'name' => 'Chapter'],
                                ['id' => 4, 'name' => 'Book'],
                                ['id' => 5, 'name' => 'Other'],
                                ['id' => 6, 'name' => 'Section'],
                             ]);
      }
    }
}
