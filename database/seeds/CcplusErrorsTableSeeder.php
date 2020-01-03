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
            ['id'=>0, 'message'=>'Undefined error', 'severity'=>'Warning'],
            ['id'=>10, 'message'=>'SUSHI HTTP request failed, verify URL','severity'=>'Fatal'],
            ['id'=>20, 'message'=>'Error decoding JSON', 'severity'=>'Fatal'],
            ['id'=>30, 'message'=>'JSON is not an object', 'severity'=>'Fatal'],
            ['id'=>100, 'message'=>'COUNTER report failed validation', 'severity'=>'Fatal'],
            ]);
           // SUSHI errors : 1XXX - 3XXX
            DB::table($table)->insert([
            ['id'=>1000, 'message'=>'Service Not Available', 'severity'=>'Fatal'],
            ['id'=>1010, 'message'=>'Service Busy', 'severity'=>'Fatal'],
            ['id'=>1011, 'message'=>'Report Queued for Processing', 'severity'=>'Warning'],
            ['id'=>1020, 'message'=>'Client has made too many requests', 'severity'=>'Fatal'],
            ['id'=>1030, 'message'=>'Insufficient Information to Process Request', 'severity'=>'Fatal'],
            ['id'=>2000, 'message'=>'Requestor Not Authorized to Access Service', 'severity'=>'Error'],
            ['id'=>2010, 'message'=>'Requestor Not Authorized to Access Usage for Institution', 'severity'=>'Error'],
            ['id'=>2020, 'message'=>'APIKey Invalid', 'severity'=>'Error'],
            ['id'=>3000, 'message'=>'Report Not Supported', 'severity'=>'Error'],
            ['id'=>3010, 'message'=>'Report Version Not Supported', 'severity'=>'Error'],
            ['id'=>3020, 'message'=>'Invalid Date Arguments', 'severity'=>'Error'],
            ['id'=>3030, 'message'=>'No Usage Available for Requested Dates', 'severity'=>'Error'],
            ['id'=>3031, 'message'=>'Usage Not Ready for Requested Dates', 'severity'=>'Error'],
            ['id'=>3040, 'message'=>'Partial Data Returned', 'severity'=>'Warning'],
            ['id'=>3050, 'message'=>'Parameter Not Recognized in this Context', 'severity'=>'Warning'],
            ['id'=>3060, 'message'=>'Invalid ReportFilter Value', 'severity'=>'Warning'],
            ['id'=>3061, 'message'=>'Incongruous ReportFilter Value', 'severity'=>'Error'],
            ['id'=>3062, 'message'=>'Invalid ReportAttribute Value', 'severity'=>'Error'],
            ['id'=>3070, 'message'=>'Required ReportFilter Missing', 'severity'=>'Warning'],
            ['id'=>3071, 'message'=>'Required ReportAttribute Missing', 'severity'=>'Warning'],
            ['id'=>3080, 'message'=>'Limit Requested Greater than Maximum Server Limit', 'severity'=>'Warning'],
            ]);
        }
    }
}
