<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class ConnectionFieldSeeder extends Seeder
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
       $table = $_db . ".connection_fields";

      // Make sure table is empty
       if (DB::table($table)->get()->count() == 0) {
           DB::table($table)->insert([
                                 ['id' => 1, 'name' => 'customer_id', 'label' => 'Customer ID'],
                                 ['id' => 2, 'name' => 'requestor_id', 'label' => 'Requestor ID'],
                                 ['id' => 3, 'name' => 'api_key', 'label' => 'API Key'],
                                 ['id' => 4, 'name' => 'extra_args', 'label' => 'Extra Args'],
                              ]);
       }
    }
}
