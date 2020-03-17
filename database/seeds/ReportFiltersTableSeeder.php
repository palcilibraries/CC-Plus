<?php

use Illuminate\Database\Seeder;

class ReportFiltersTableSeeder extends Seeder
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
      $table = $_db . ".reportfilters";

      if (DB::table($table)->get()->count() == 0) {
          DB::table($table)->insert([
          // TR report filters
            ['id' => 1, 'report_id' => 1, 'is_global' => 1,
                        'table_name' => 'accessmethods', 'report_column' => 'accessmethod_id'],
            ['id' => 2, 'report_id' => 1, 'is_global' => 1,
                        'table_name' => 'accesstypes', 'report_column' => 'accesstype_id'],
            ['id' => 3, 'report_id' => 1, 'is_global' => 1,
                        'table_name' => 'datatypes', 'report_column' => 'datatype_id'],
            ['id' => 4, 'report_id' => 1, 'is_global' => 1,
                        'table_name' => 'sectiontypes', 'report_column' => 'sectiontype_id'],
            ['id' => 5, 'report_id' => 1, 'is_global' => 1,
                        'table_name' => 'platforms', 'report_column' => 'plat_id'],
            ['id' => 6, 'report_id' => 1, 'is_global' => 0,
                        'table_name' => 'institutions', 'report_column' => 'inst_id'],
            ['id' => 7, 'report_id' => 1, 'is_global' => 0,
                        'table_name' => 'providers', 'report_column' => 'prov_id'],

          // DR report filters
            ['id' => 8, 'report_id' => 2, 'is_global' => 1,
                        'table_name' => 'accessmethods', 'report_column' => 'accessmethod_id'],
            ['id' => 9, 'report_id' => 2, 'is_global' => 1,
                         'table_name' => 'datatypes', 'report_column' => 'datatype_id'],
            ['id' => 10, 'report_id' => 2, 'is_global' => 1,
                         'table_name' => 'platforms', 'report_column' => 'plat_id'],
            ['id' => 11, 'report_id' => 2, 'is_global' => 0,
                         'table_name' => 'institutions', 'report_column' => 'inst_id'],
            ['id' => 12, 'report_id' => 2, 'is_global' => 0,
                         'table_name' => 'providers', 'report_column' => 'prov_id'],

          // PR report filters
            ['id' => 13, 'report_id' => 3, 'is_global' => 1,
                         'table_name' => 'accessmethods', 'report_column' => 'accessmethod_id'],
            ['id' => 14, 'report_id' => 3, 'is_global' => 1,
                         'table_name' => 'datatypes', 'report_column' => 'datatype_id'],
            ['id' => 15, 'report_id' => 3, 'is_global' => 0,
                         'table_name' => 'institutions', 'report_column' => 'inst_id'],
            ['id' => 16, 'report_id' => 3, 'is_global' => 0,
                         'table_name' => 'providers', 'report_column' => 'prov_id'],

          // IR report filters
            ['id' => 17, 'report_id' => 4, 'is_global' => 1,
                         'table_name' => 'accessmethods', 'report_column' => 'accessmethod_id'],
            ['id' => 18, 'report_id' => 4, 'is_global' => 1,
                         'table_name' => 'accesstypes', 'report_column' => 'accesstype_id'],
            ['id' => 19, 'report_id' => 4, 'is_global' => 1,
                         'table_name' => 'datatypes', 'report_column' => 'datatype_id'],
            ['id' => 20, 'report_id' => 4, 'is_global' => 1,
                         'table_name' => 'platforms', 'report_column' => 'plat_id'],
            ['id' => 21, 'report_id' => 4, 'is_global' => 0,
                         'table_name' => 'institutions', 'report_column' => 'inst_id'],
            ['id' => 22, 'report_id' => 4, 'is_global' => 0,
                         'table_name' => 'providers', 'report_column' => 'prov_id'],
            ]);
      }
    }
}
