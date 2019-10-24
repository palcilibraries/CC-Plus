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
                'inherited_fields' => '2,6,9,10,11,12,13,14,15,16,17,18,19:Book,20,21:Controlled,22:Regular,23']);
            DB::table($table)->insert(['id' => 6,'name' => 'TR_B2',
                'legend' => 'Book Access Denied','parent_id' => 1,
                'inherited_fields' => '7,8,9,10,11,12,13,14,15,16,17,18,19:Book,20,21,22:Regular,23']);
            DB::table($table)->insert(['id' => 7,'name' => 'TR_B3',
                'legend' => 'Book Usage by Access Type','parent_id' => 1,
                'inherited_fields' => '1,2,3,4,5,6,9,10,11,12,13,14,15,16,17,18,19:Book,20,21,22:Regular,23']);
            DB::table($table)->insert(['id' => 8,'name' => 'TR_J1',
                'legend' => 'Journal Requests (Excluding OA_Gold)','parent_id' => 1,
                'inherited_fields' => '2,6,9,10,11,12,13,14,15,17,18,19:Journal,20,21:Controlled,22:Regular,23']);
            DB::table($table)->insert(['id' => 9,'name' => 'TR_J2',
                'legend' => 'Journal Access Denied','parent_id' => 1,
                'inherited_fields' => '7,8,9,10,11,12,13,14,15,17,18,19:Journal,20,21,22:Regular,23']);
            DB::table($table)->insert(['id' => 10,'name' => 'TR_J3',
                'legend' => 'Journal Usage by Access Type','parent_id' => 1,
                'inherited_fields' => '1,2,3,4,9,10,11,12,13,14,15,17,18,19:Journal,20,21,22:Regular,23']);
            DB::table($table)->insert(['id' => 11,'name' => 'TR_J4',
                'legend' => 'Journal Requests by YOP (Excluding OA_Gold)','parent_id' => 1,
                'inherited_fields' => '2,6,9,10,11,12,13,14,15,17,18,19:Journal,20,21:Controlled,22:Regular,23']);
            DB::table($table)->insert(['id' => 12,'name' => 'DR_D1',
                'legend' => 'Database Search and Item Usage','parent_id' => 2,
                'inherited_fields' => '24,25,26,27,28,35,36,37,38,39,40:Regular,41']);
            DB::table($table)->insert(['id' => 13,'name' => 'DR_D2',
                'legend' => 'Database Access Denied','parent_id' => 2,
                'inherited_fields' => '33,34,35,36,37,38,39,40:Regular,41']);
            DB::table($table)->insert(['id' => 14,'name' => 'PR_P1',
                'legend' => 'Platform Usage','parent_id' => 3,
                'inherited_fields' => '42,44,46,48,49,50,51,52:Regular,53']);
            DB::table($table)->insert(['id' => 15,'name' => 'IR_A1',
                'legend' => 'Journal Article Requests','parent_id' => 4,
                'inherited_fields' => '54,55,56,57,58,59,60,61,62,63,65,66,67,68,69,70,71,72,73,74,76,77,78,'
                                    . '79,80,81,82,83,84,86,87,88,89:Article,90,91,92:Regular,93']);
            DB::table($table)->insert(['id' => 16,'name' => 'IR_M1',
                'legend' => 'Multimedia Item Requests','parent_id' => 4,
                'inherited_fields' => '54,56,57,58,62,63,67,68,78,79,88,89:Multimedia,90,91,92:Regular,93']);
        }
    }
}
