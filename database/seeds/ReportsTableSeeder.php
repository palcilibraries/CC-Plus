<?php

use Illuminate\Database\Seeder;

class ReportsTableSeeder extends Seeder
{
    /**
     * Seed the reports table in the global database
     *
     * @return void
     */
    public function run()
    {

      // Make sure we're talking to the global database
        $_db = \Config::get('database.connections.globaldb.database');
        $table = $_db . ".reports";

       // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
            DB::table($table)->insert(['id' => 1,'name' => 'TR','legend' => 'Title Master Report']);
            DB::table($table)->insert(['id' => 2,'name' => 'DR','legend' => 'Database Master Report']);
            DB::table($table)->insert(['id' => 3,'name' => 'PR','legend' => 'Platform Master Report']);
            DB::table($table)->insert(['id' => 4,'name' => 'IR','legend' => 'Item Master Report']);
            DB::table($table)->insert(['id' => 5,'name' => 'TR_B1',
                'legend' => 'Book Requests (Excluding OA_Gold)','parent_id' => 1,
                'inherited_fields' => '1,6:2,8:1,9:1,18,20']);
                // 'inherited_fields' => '1,2,3,4,5,6:2,7,8:1,9:1,10,11,12,13,14,15,16,18,20']);
            DB::table($table)->insert(['id' => 6,'name' => 'TR_B2',
                'legend' => 'Book Access Denied','parent_id' => 1,
                'inherited_fields' => '1,6:2,9:1,23,24']);
                // 'inherited_fields' => '1,2,3,4,5,6:2,7,8,9:1,10,11,12,13,14,15,16,23,24']);
            DB::table($table)->insert(['id' => 7,'name' => 'TR_B3',
                'legend' => 'Book Usage by Access Type','parent_id' => 1,
                'inherited_fields' => '1,6:2,9:1,17,18,19,20,21,22']);
                // 'inherited_fields' => '1,2,3,4,5,6:2,7,8,9:1,10,11,12,13,14,15,16,17,18,19,20,21,22']);
            DB::table($table)->insert(['id' => 8,'name' => 'TR_J1',
                'legend' => 'Journal Requests (Excluding OA_Gold)','parent_id' => 1,
                'inherited_fields' => '1,6:1,8:1,9:1,18,20']);
                // 'inherited_fields' => '1,2,3,4,5,6:1,7,8:1,9:1,10,12,13,14,15,16,18,20']);
            DB::table($table)->insert(['id' => 9,'name' => 'TR_J2',
                'legend' => 'Journal Access Denied','parent_id' => 1,
                'inherited_fields' => '1,6:1,9:1,23,24']);
                // 'inherited_fields' => '1,2,3,4,5,6:1,7,8,9:1,10,12,13,14,15,16,23,24']);
            DB::table($table)->insert(['id' => 10,'name' => 'TR_J3',
                'legend' => 'Journal Usage by Access Type','parent_id' => 1,
                'inherited_fields' => '1,6:1,9:1,17,18,19,20']);
                // 'inherited_fields' => '1,2,3,4,5,6:1,7,8,9:1,10,12,13,14,15,16,17,18,19,20']);
            DB::table($table)->insert(['id' => 11,'name' => 'TR_J4',
                'legend' => 'Journal Requests by YOP (Excluding OA_Gold)','parent_id' => 1,
                'inherited_fields' => '1,6:1,8:1,9:1,18,20']);
                // 'inherited_fields' => '1,2,3,4,5,6:1,7,8:1,9:1,10,12,13,14,15,16,18,20']);
            DB::table($table)->insert(['id' => 12,'name' => 'DR_D1',
                'legend' => 'Database Search and Item Usage','parent_id' => 2,
                'inherited_fields' => '26,32:1,34,35,36,37,38']);
                // 'inherited_fields' => '26,27,28,29,30,31,32:1,33,34,35,36,37,38']);
            DB::table($table)->insert(['id' => 13,'name' => 'DR_D2',
                'legend' => 'Database Access Denied','parent_id' => 2,
                'inherited_fields' => '26,32:1,43,44']);
                // 'inherited_fields' => '26,27,28,29,30,31,32:1,33,43,44']);
            DB::table($table)->insert(['id' => 14,'name' => 'PR_P1',
                'legend' => 'Platform Usage','parent_id' => 3,
                'inherited_fields' => '46,50:1,51,53,55,57']);
                // 'inherited_fields' => '46,47,48,49,50:1,51,52,53,54,55,56,57']);
            DB::table($table)->insert(['id' => 15,'name' => 'IR_A1',
                'legend' => 'Journal Article Requests','parent_id' => 4,
                'inherited_fields' => '59,94:3,97:1,99,101']);
            DB::table($table)->insert(['id' => 16,'name' => 'IR_M1',
                'legend' => 'Multimedia Item Requests','parent_id' => 4,
                'inherited_fields' => '59,94:4,97:1,99']);
        }
    }
}
