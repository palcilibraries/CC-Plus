<?php

namespace Database\Seeders;

use DB;
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
            ['id'=>0, 'message' => 'Undefined error', 'severity_id' => 11, 'explanation' => '',
                'suggestion' => 'Please contact your CC-PLUS admin for more information.'
            ],
            ['id'=>10, 'message' => 'SUSHI HTTP request failed, verify URL','severity_id' => 99,
                'explanation' => 'The request to the SUSHI server failed to connect.',
                'suggestion' => 'Check that the URL in the provider settings is correct and retry.'
            ],
            ['id'=>20, 'message' => 'Error decoding JSON', 'severity_id' => 99,
                'explanation' => 'The report received from the provider has technical errors in its formatting.',
                'suggestion' => 'Contact the provider to report this issue.'
            ],
            ['id'=>30, 'message' => 'JSON is not an object', 'severity_id' => 99,
                'explanation' => 'The SUSHI service returned non-JSON data.',
                'suggestion' => 'Contact the provider to report this issue.'
            ],
            ['id'=>100, 'message' => 'COUNTER report failed validation', 'severity_id' => 99,
                'explanation' => 'The report received from the provider did not conform to COUNTER specificiations.',
                'suggestion' => 'Contact the provider to report this issue.'
            ],
            ]);
           // SUSHI errors : 1XXX - 3XXX
            DB::table($table)->insert([
            ['id'=>1000, 'message' => 'Service Not Available', 'severity_id' => 99,
                'explanation' => 'The request to the SUSHI server was successful, but the service is currently not' .
                                 ' running.',
                'suggestion' => 'Contact the provider to report this issue.'
            ],
            ['id'=>1010, 'message' => 'Service Busy', 'severity_id' => 99,
                'explanation' => 'The request to the SUSHI server was successful, but the service is currently busy' .
                                 ' and cannot connect.',
                'suggestion' => 'Wait for the next retry. If this error occurs multiple times, contact the provider' .
                                ' to report this issue.'
            ],
            ['id'=>1011, 'message' => 'Report Queued for Processing', 'severity_id' => 11,
                'explanation' => "The SUSHI service accepted the request and put it into a queue for future" .
                                 " processing at the provider's service.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.'
            ],
            ['id'=>1020, 'message' => 'Client has made too many requests', 'severity_id' => 99,
                'explanation' => 'The request to the SUSHI server was successful, but the limit of requests per day' .
                                 ' has been reached.',
                'suggestion' => 'Wait for the next retry. If this error occurs multiple times, contact the provider' .
                                ' to report this issue.'
            ],
            ['id'=>1030, 'message' => 'Insufficient Information to Process Request', 'severity_id' => 99,
                'explanation' => 'One or more credentials is missing.',
                'suggestion' => 'Check your SUSHI credentials and verify that they are complete and correct with' .
                                ' the provider.'
            ],
            ['id'=>2000, 'message' => 'Requestor Not Authorized to Access Service', 'severity_id'=>12,
                'explanation' => 'One or more of your credentials is incorrect or has not been authorized, likely' .
                                 ' the requestor_id.',
                'suggestion' => 'Check your SUSHI credentials and verify that they are complete and correct with' .
                                ' the provider.'
            ],
            ['id'=>2010, 'message' => 'Requestor Not Authorized to Access Usage for Institution', 'severity_id'=>12,
                'explanation' => 'The account reflected by your requestor_id does not have permission to access' .
                                 ' credentials for this institution or provider.',
                'suggestion' => 'Check your SUSHI credentials and verify that they are complete and correct with' .
                                ' the provider.'
            ],
            ['id'=>2020, 'message' => 'APIKey Invalid', 'severity_id'=>12,
                'explanation' => 'The APIKey for the request was not recognized by the report provider.',
                'suggestion' => 'Check your SUSHI credentials and verify that they are complete and correct with' .
                                ' the provider.'
            ],
            ['id'=>3000, 'message' => 'Report Not Supported', 'severity_id'=>12,
                'explanation' => 'The provider is not providing this report via this SUSHI endpoint.',
                'suggestion' => 'Remove this report from your settings to avoid future failures.'
            ],
            ['id'=>3010, 'message' => 'Report Version Not Supported', 'severity_id'=>12,
                'explanation' => 'The provider is not providing this report via this SUSHI endpoint.',
                'suggestion' => 'Remove this report from your settings to avoid future failures.'
            ],
            ['id'=>3020, 'message' => 'Invalid Date Arguments', 'severity_id'=>12,
                'explanation' => 'The dates requested are incorrect or contain an error.',
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.'
            ],
            ['id'=>3030, 'message' => 'No Usage Available for Requested Dates', 'severity_id'=>12,
                'explanation' => 'A report for the dates requested is not available.',
                'suggestion' => 'Stop the harvest and check with the provider if data should be available.'
            ],
            ['id'=>3031, 'message' => 'Usage Not Ready for Requested Dates', 'severity_id'=>12,
                'explanation' => 'A report for the dates requested is not yet available, but will be.',
                'suggestion' => 'Wait for the next retry or stop the harvest for now and restart it later. If this' .
                                ' error persists, consider asking the CC-PLUS admin to change the date on which the' .
                                ' monthly report is run.'
            ],
            ['id'=>3040, 'message' => 'Partial Data Returned', 'severity_id' => 11,
                'explanation' => 'The request did not return a complete report.',
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.'
            ],
            ['id'=>3050, 'message' => 'Parameter Not Recognized in this Context', 'severity_id' => 11,
                'explanation' => "The request asked for something that the server didn't recognize.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.'
            ],
            ['id'=>3060, 'message' => 'Invalid ReportFilter Value', 'severity_id' => 11,
                'explanation' => "The request asked to filter out some data that the server didn't recognize.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.'
            ],
            ['id'=>3061, 'message' => 'Incongruous ReportFilter Value', 'severity_id'=>12,
                'explanation' => 'Specified filter values out of scope for the requested report.',
                'suggestion' => 'Contact the provider to report this issue.'
            ],
            ['id'=>3062, 'message' => 'Invalid ReportAttribute Value', 'severity_id'=>12,
                'explanation' => "The request asked for something that the server didn't recognize.",
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.'
            ],
            ['id'=>3070, 'message' => 'Required ReportFilter Missing', 'severity_id' => 11,
                'explanation' => 'The request required a filter that was not present.',
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.'
            ],
            ['id'=>3071, 'message' => 'Required ReportAttribute Missing', 'severity_id' => 11,
                'explanation' => 'The request required a piece of information that was not present.',
                'suggestion' => 'Work with the CC-PLUS admin to correct this error.'
            ],
            ['id'=>3080, 'message' => 'Limit Requested Greater than Maximum Server Limit', 'severity_id' => 11,
                  'explanation' => 'The request to the SUSHI server was successful, but the limit of requests per' .
                                   ' day has been reached.',
                  'suggestion' => 'Wait for the next retry. If this error occurs multiple times, contact the' .
                                  ' provider to report this issue.'
            ],
            ]);
        }
    }
}
