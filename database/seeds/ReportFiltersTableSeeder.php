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
          ['id' => 1, 'attrib' => 'Provider', 'model' => '\App\Provider', 'table_name' => 'providers',
                      'report_column' => 'prov_id'],
          ['id' => 2, 'attrib' => 'Platform', 'model' => '\App\Platform', 'table_name' => 'platforms',
                      'report_column' => 'plat_id'],
          ['id' => 3, 'attrib' => 'Institution', 'model' => '\App\Institution', 'table_name' => 'institutions',
                      'report_column' => 'inst_id'],
          ['id' => 4, 'attrib' => 'Data_Type', 'model' => '\App\DataType', 'table_name' => 'datatypes',
                      'report_column' => 'datatype_id'],
          ['id' => 5, 'attrib' => 'Access_Type', 'model' => '\App\AccessType', 'table_name' => 'accesstypes',
                      'report_column' => 'accesstype_id'],
          ['id' => 6, 'attrib' => 'Access_Method', 'model' => '\App\AccessMethod', 'table_name' => 'accessmethods',
                      'report_column' => 'accessmethod_id'],
          ['id' => 7, 'attrib' => 'Section_Type', 'model' => '\App\SectionType', 'table_name' => 'sectiontypes',
                      'report_column' => 'sectiontype_id'],
          ['id' => 8, 'attrib' => 'Institution_Group', 'model' => '\App\InstitutionGroup',
                      'table_name' => 'institutiongroups', 'report_column' => 'institutiongroup_id'],
          ['id' => 9, 'attrib' => 'YOP', 'model' => null, 'table_name' => null, 'report_column' => 'yop'],
          ]);
      }
    }
}
