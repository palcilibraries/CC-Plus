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
          ['id' => 1, 'model' => '\App\Provider', 'table_name' => 'providers', 'report_column' => 'prov_id'],
          ['id' => 2, 'model' => '\App\Platform', 'table_name' => 'platforms', 'report_column' => 'plat_id'],
          ['id' => 3, 'model' => '\App\Institution', 'table_name' => 'institutions', 'report_column' => 'inst_id'],
          ['id' => 4, 'model' => '\App\DataType', 'table_name' => 'datatypes', 'report_column' => 'datatype_id'],
          ['id' => 5, 'model' => '\App\AccessType', 'table_name' => 'accesstypes', 'report_column' => 'accesstype_id'],
          ['id' => 6, 'model' => '\App\AccessMethod', 'table_name' => 'accessmethods',
                      'report_column' => 'accessmethod_id'],
          ['id' => 7, 'model' => '\App\SectionType', 'table_name' => 'sectiontypes',
                      'report_column' => 'sectiontype_id'],
          ['id' => 8, 'model' => '\App\InstitutionGroup', 'table_name' => 'institutiongroups',
                      'report_column' => 'institutiongroup_id'],
          ]);
      }
    }
}
