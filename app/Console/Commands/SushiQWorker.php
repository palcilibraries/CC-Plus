<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use DB;
use App\Consortium;
use App\Report;
use App\Sushi;
use App\SushiSetting;
use App\SushiQueueJob;
use App\Counter5Processor;
use App\FailedIngest;
use App\IngestLog;
use \ubfr\c5tools\JsonR5Report;
use \ubfr\c5tools\CheckResult;
use \ubfr\c5tools\ParseException;

/*
 * NOTE:
 *   As of 1/10/2020, it looks like the HOST system needs to set the PHP memory_limit
 *   to AT LEAST 1024Mb. Encoding/Decoding the JSON for processing can be a real PIG,
 *   and is dependent on the size of the report coming back from the providers
 *   (60-100K characters is not uncommon for TR). This script will fail and die with a
 *   shutdown exception (that cannot be caught) if it cannot allocate enough memory.
 */
 // CC Plus Queue Worker Script
 //
class SushiQWorker extends Command
{
    /**
     * The name and signature for the single-report Sushi processing console command.
     * @var string
     */
    protected $signature = 'ccplus:sushiqw {consortium : Consortium ID or key-string}';
// Implement Some - Any - or - None of these?
    // {connection? : The name of the queue connection to work}
    // {--queue= : The names of the queues to work}
    // {--daemon : Run the worker in daemon mode (Deprecated)}
    // {--once : Only process the next job on the queue}
    // {--stop-when-empty : Stop when the queue is empty}
    // {--delay=0 : The number of seconds to delay failed jobs}
    // {--force : Force the worker to run even in maintenance mode}
    // {--memory=128 : The memory limit in megabytes}
    // {--sleep=3 : Number of seconds to sleep when no job is available}
    // {--timeout=60 : The number of seconds a child process can run}
    // {--tries=1 : Number of times to attempt a job before logging it failed}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the CC-Plus Sushi Queue for a Consortium';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
       // Try to get the consortium as ID or Key
        $conarg = $this->argument('consortium');
        $consortium = Consortium::find($conarg);
        if (is_null($consortium)) {
            $consortium = Consortium::where('ccp_key', '=', $conarg)->first();
        }
        if (is_null($consortium)) {
            $this->line('Cannot locate Consortium: ' . $conarg);
            exit;
        }

       // Aim the consodb connection at specified consortium's database and initialize the
       // path for keeping raw report responses
        config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
        DB::reconnect();
        if (!is_null(config('ccplus.reports_path'))) {
            $report_path = config('ccplus.reports_path') . $consortium->ccp_key;
        }

       // Build an array of server_urls referenced by active ingests defined in the IngestLogs of
       // ALL active consortia in the system.
        $active_urls = array();
        $all_consortia = Consortium::where('is_active',true)->get();
        foreach ($all_consortia as $_con) {
            $_db = 'ccplus_' . $_con->ccp_key;
            $_urls = DB::table($_db . '.ingestlogs as ing')
                         ->distinct()
                         ->join($_db . '.sushisettings as sus', 'sus.id', '=', 'ing.sushisettings_id')
                         ->join($_db . '.providers as prv', 'prv.id', '=', 'sus.prov_id')
                         ->where($_db . '.ing.status', 'Active')
                         ->select($_db . '.prv.server_url_r5')
                         ->get();
            foreach ($_urls as $_url) {
                if (!in_array($_url,$active_urls)) $active_urls[] = $_url->server_url_r5;
            }
        }

       // Get all queue entries for this consortium; exit if none found
       //
        $jobs = SushiQueueJob::where('consortium_id', '=', $consortium->id)->get();
        if (empty($jobs)) exit;

       // Loop through all queue entrie
        foreach ($jobs as $job) {

           // Check the entry against the active urls and skip if there's a match
            $_url = $job->ingest->sushiSetting->provider->server_url_r5;
// if (in_array($_url, $active_urls)) {
//     $this->line('URL match, Skipping : ' . $job->ingest->id);
//     continue;
// }
            if (in_array($_url, $active_urls)) continue;
            $active_urls[] = $_url;

           // Update entry's current state aand the active urls
// $this->line('Updating Job : ' . $job->id . ' to Active status.');
            $job->ingest->status = 'Active';
            $job->ingest->save();

           // Setup begin and end dates for sushi request
            $yearmon = $job->ingest->yearmon;
            $begin = $yearmon . '-01';
            $end = $yearmon . '-' . date('t', strtotime($begin));

           // Get report
            $report = Report::find($job->ingest->report_id);
            if (is_null($report)) {     // report gone? toss entry
                $this->line('Unknown Report ID: ' . $job->ingest->report_id .
                            ' , queue entry skipped and deleted.');
                $job->delete();
                continue;
            }

           // Get sushi settings
            if (is_null($job->ingest->sushiSetting)) {     // settings gone? toss entry
                $this->line('Unknown Sushi Settings ID: ' . $job->ingest->sushisettings_id .
                            ' , queue entry skipped and deleted.');
                $job->delete();
                continue;
            }
            $setting = $job->ingest->sushiSetting;

           // Create a new processor object
            $C5processor = new Counter5Processor($setting->prov_id, $setting->inst_id, $begin, $end, "");

           // Create a new Sushi object
            $sushi = new Sushi($begin, $end);

           // Set output filename for raw data. Create the folder path, if necessary
            if (!is_null(config('ccplus.reports_path'))) {
                $full_path = $report_path . '/' . $setting->institution->name . '/' . $setting->provider->name . '/';
                if (!is_dir($full_path)) {
                    mkdir($full_path, 0755, true);
                }
                $sushi->raw_datafile = $full_path . $report->name . '_' . $begin . '_' . $end . '.json';
            }

           // Construct URI for the request
            $request_uri = $sushi->buildUri($setting, $report);

           // Loop up to retry-limit asking for the report
           // NOTE:  A queued REQUEST is not the same as a queued INGEST
            $retries = 0;
            $req_state = "Queued";      // Independent of $job->ingest->status ... NOT the same thing !!
            $ts = date("Y-m-d H:i:s");
            $valid_report = false;

           // Sleeps for sushi_retry_sleep seconds, and retries sushi_retry_limit times if request is queued.
            while ($retries <= config('ccplus.sushi_retry_limit')  && $req_state == "Queued") {
               // Make the request
                $req_state = $sushi->request($request_uri);

               // Check status of request
                if ($req_state == "Queued") {
                    $retries++;
                    $sushi->retrySleep();
                    continue;
                }

               // Print out non-fatal message from sushi request
                if ($sushi->message != "") {
                    $this->line($sushi->message . $sushi->detail);
                }

               // Request succeeded, validate the response
                if ($req_state == "Success") {
                    try {
                        $valid_report = $sushi->validateJson();
                    } catch (\Exception $e) {
                        FailedIngest::insert(['ingest_id' => $job->ingest->id, 'process_step' => 'COUNTER',
                                              'error_id' => 100, 'detail' => 'Validation error: ' . $e->getMessage(),
                                              'created_at' => $ts]);
                        $this->line("COUNTER report failed validation : " . $e->getMessage());
                    }
                } else {    // Fail
               // If request failed, update the IngestLog and add a FailedIngest record
                    FailedIngest::insert(['ingest_id' => $job->ingest->id, 'process_step' => $sushi->step,
                                          'error_id' => $sushi->error_id, 'detail' => $sushi->detail,
                                          'created_at' => $ts]);
                    $this->line($sushi->message . $sushi->detail);
                }
            } // while $req_state='Queued' with retries remaining

           // If we have a validated report, processs and save it
            if ($valid_report) {
                $_status = $C5processor->{$report->name}($sushi->json);
// -->> Is there ever a time this returns something other than success?
                if ($_status = 'Success') {
                    $this->line($report->name . " report data successfully saved.");
                }
                $job->ingest->status = $_status;
                $job->ingest->update();
            } else {
               // Increment attempt counter
                $job->ingest->attempts++;
               // If we're out of retries, set ingest status and an Alert
                if ($this->ingest->attempts >= config('ccplus.max_ingest_retries')) {
                    $job->ingest->status = 'Fail';
                    Alert::insert(['yearmon' => $yearmon, 'prov_id' => $setting->prov_id,
                                   'ingest_id' => $job->ingest->id, 'status' => 'Active', 'created_at' => $this->ts]);
                } else {
                    $job->ingest->status = 'Retrying';
                }
               // Update IngestLog record
                $job->ingest->update();
            }

           // Clean up for next iteration
            unset($sushi);
            unset($C5processor);
            $job->delete();
            foreach ($active_urls as $key => $value) {
                if ($value == $_url) {
                    unset($active_urls[$key]);
                    break;
                }
            }
        }   // For each queue $job
    }
}
