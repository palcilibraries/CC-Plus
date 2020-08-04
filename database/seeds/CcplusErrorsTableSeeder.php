<?php

use Illuminate\Database\Seeder;

class CcplusErrorsTableSeeder extends Seeder
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
        $table = $_db . ".ccplus_errors";

        // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
           // CCPLUS errors : 00XX - 09XX
            DB::table($table)->insert([
            ['id'=>0, 'message' => 'Undefined error', 'severity_id' => 11],
            ['id'=>10, 'message' => 'SUSHI HTTP request failed, verify URL','severity_id' => 99],
            ['id'=>20, 'message' => 'Error decoding JSON', 'severity_id' => 99],
            ['id'=>30, 'message' => 'JSON is not an object', 'severity_id' => 99],
            ['id'=>100, 'message' => 'COUNTER report failed validation', 'severity_id' => 99],
            ]);
           // SUSHI errors : 1XXX - 3XXX
            DB::table($table)->insert([
            ['id'=>1000, 'message' => 'Service Not Available', 'severity_id' => 99],
            ['id'=>1010, 'message' => 'Service Busy', 'severity_id' => 99],
            ['id'=>1011, 'message' => 'Report Queued for Processing', 'severity_id' => 11],
            ['id'=>1020, 'message' => 'Client has made too many requests', 'severity_id' => 99],
            ['id'=>1030, 'message' => 'Insufficient Information to Process Request', 'severity_id' => 99],
            ['id'=>2000, 'message' => 'Requestor Not Authorized to Access Service', 'severity_id'=>12],
            ['id'=>2010, 'message' => 'Requestor Not Authorized to Access Usage for Institution', 'severity_id'=>12],
            ['id'=>2020, 'message' => 'APIKey Invalid', 'severity_id'=>12],
            ['id'=>3000, 'message' => 'Report Not Supported', 'severity_id'=>12],
            ['id'=>3010, 'message' => 'Report Version Not Supported', 'severity_id'=>12],
            ['id'=>3020, 'message' => 'Invalid Date Arguments', 'severity_id'=>12],
            ['id'=>3030, 'message' => 'No Usage Available for Requested Dates', 'severity_id'=>12],
            ['id'=>3031, 'message' => 'Usage Not Ready for Requested Dates', 'severity_id'=>12],
            ['id'=>3040, 'message' => 'Partial Data Returned', 'severity_id' => 11],
            ['id'=>3050, 'message' => 'Parameter Not Recognized in this Context', 'severity_id' => 11],
            ['id'=>3060, 'message' => 'Invalid ReportFilter Value', 'severity_id' => 11],
            ['id'=>3061, 'message' => 'Incongruous ReportFilter Value', 'severity_id'=>12],
            ['id'=>3062, 'message' => 'Invalid ReportAttribute Value', 'severity_id'=>12],
            ['id'=>3070, 'message' => 'Required ReportFilter Missing', 'severity_id' => 11],
            ['id'=>3071, 'message' => 'Required ReportAttribute Missing', 'severity_id' => 11],
            ['id'=>3080, 'message' => 'Limit Requested Greater than Maximum Server Limit', 'severity_id' => 11],
            ]);
        }
    }
}
