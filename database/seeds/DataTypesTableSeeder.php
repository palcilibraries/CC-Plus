<?php

use Illuminate\Database\Seeder;

class DataTypesTableSeeder extends Seeder
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
      $table = $_db . ".datatypes";

     // Make sure table is empty
      if (DB::table($table)->get()->count() == 0) {
          DB::table($table)->insert([
                                ['id' => 1, 'name' => 'Journal'],
                                ['id' => 2, 'name' => 'Book'],
                                ['id' => 3, 'name' => 'Article'],
                                ['id' => 4, 'name' => 'Multimedia'],
                                ['id' => 5, 'name' => 'Platform'],
                                ['id' => 6, 'name' => 'Database'],
                                ['id' => 7, 'name' => 'Report'],
                                ['id' => 8, 'name' => 'Unknown'],
                             ]);
      }
    }
}
