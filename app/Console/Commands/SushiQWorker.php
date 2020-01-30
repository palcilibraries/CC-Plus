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

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the CC-Plus Sushi Queue for a Consortium';
    private $all_consortia;

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
       // Allow input consortium to be an ID or Key
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

       // Setup strings for job queries
        $jobs_table = config('database.connections.globaldb.database') . ".jobs";
        $ingestlogs_table = config('database.connections.consodb.database') . ".ingestlogs";
        $runable_status = array('Queued','Pending','Retrying');

       // Get all queue entries for this consortium; exit if none found
        // $job_count = SushiQueueJob::join($ingestlogs_table . ' as ing', 'ing.id', '=', $jobs_table . '.ingest_id')
        $job_count = DB::table($jobs_table . ' as job')
                       ->join($ingestlogs_table . ' as ing', 'ing.id', '=', 'job.ingest_id')
                       ->where('consortium_id', '=', $consortium->id)
                       ->whereIn('ing.status', $runable_status)
                       ->count();
        if ($job_count == 0) {
            exit;
        }

       // Save all consortia records for detecting active jobs, strings for job queries
        $this->all_consortia = Consortium::where('is_active', true)->get();

       // While there are jobs in the queue (count is updated @ bottom of loop)
        while ($job_count > 0) {
            $jobs = SushiQueueJob::join($ingestlogs_table . ' as ing', 'ing.id', '=', $jobs_table . '.ingest_id')
                                 ->where('consortium_id', '=', $consortium->id)
                                 ->whereIn('ing.status', $runable_status)
                                 ->orderBy('priority', 'DESC')
                                 ->orderBy($jobs_table . '.updated_at', 'ASC')
                                 ->orderBy($jobs_table . '.id', 'ASC')
                                 ->get();
            if (empty($jobs)) {
                exit;
            }

           // Find the next available job
            $job = null;
            $ten_ago = strtotime("-10 minutes");
            foreach ($jobs as $_job) {
               // Skip any "Retrying" ingest that's been updated today
                if (
                    $job->ingest->status == 'Retrying' &&
                    (substr($_job->ingest->updated_at, 0, 10) == date("Y-m-d"))
                ) {
                    continue;
                }

               // Skip any "Pending" ingest that's been updated within the last 10 minutes
                if (
                    $job->ingest->status == 'Pending' &&
                    (strtotime($_job->ingest->updated_at) > $ten_ago ||
                     strtotime($_job->updated_at) > $ten_ago)
                ) {
                    continue;
                }

               // Check the job url against all active urls and skip if there's a match
                if ($this->hasActiveIngest($_job->ingest->sushiSetting->provider->server_url_r5)) {
                    continue;
                }

               // Got one... move on
                $job = $_job;
                break;
            }

           // If we found a job, mark it active to keep any parallel processes from hitting this same
           // provider; otherwise, we exit quietly.
            if (!is_null($job)) {
                $job->ingest->status = 'Active';
                $job->ingest->save();
            } else {
                exit;
            }

           // Setup begin and end dates for sushi request
            $yearmon = $job->ingest->yearmon;
            $begin = $yearmon . '-01';
            $end = $yearmon . '-' . date('t', strtotime($begin));

           // Get report
            $report = Report::find($job->ingest->report_id);
            if (is_null($report)) {     // report gone? toss entry
                $this->line('Unknown Report ID: ' . $job->ingest->report_id . ' , queue entry skipped and deleted.');
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

           // Create a new processor object; job record decides if data is getting replaced. If data is
           // being replaced, nothing is deleted until after the new report is received and validated.
            $C5processor = new Counter5Processor(
                $setting->prov_id,
                $setting->inst_id,
                $begin,
                $end,
                $job->replace_data
            );

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

           // Make the request
            $ts = date("Y-m-d H:i:s");
            $request_status = $sushi->request($request_uri);

           // Examine the response
            $valid_report = false;
            if ($request_status == "Success") {
               // Print out any non-fatal message from sushi request
                if ($sushi->message != "") {
                    $this->line($sushi->message . $sushi->detail);
                }

                try {
                    $valid_report = $sushi->validateJson();
                } catch (\Exception $e) {
                    FailedIngest::insert(['ingest_id' => $job->ingest->id, 'process_step' => 'COUNTER',
                                          'error_id' => 100, 'detail' => 'Validation error: ' . $e->getMessage(),
                                          'created_at' => $ts]);
                    $this->line("COUNTER report failed validation : " . $e->getMessage());
                }
           // If request is pending (in a provider queue, not a CC+ queue), update status
           // and touch the job record so it is pushed down the order of a future run
            } elseif ($request_status == "Pending") {
                $job->ingest->status = "Pending";
                $job->touch();

           // If request failed, update the IngestLog and add a FailedIngest record
            } else {    // Fail
                FailedIngest::insert(['ingest_id' => $job->ingest->id, 'process_step' => $sushi->step,
                                      'error_id' => $sushi->error_id, 'detail' => $sushi->detail,
                                      'created_at' => $ts]);
                $this->line($sushi->message . $sushi->detail);
            }

           // If we have a validated report, processs and save it
            if ($valid_report) {
                $_status = $C5processor->{$report->name}($sushi->json);
// -->> Is there ever a time this returns something other than success?
                if ($_status == 'Success') {
                    $this->line($setting->provider->name . " : " . $yearmon . " : " . $report->name . " saved for " .
                                $setting->institution->name);
                }
                $job->ingest->status = $_status;
           // No valid report data saved. If we failed, update ingest record
           // ("Pending" is not considered failure.)
            } else {
                if ($request_status == "Fail") {    // Pending is not failure
                   // Increment ingest attempts
                    $job->ingest->attempts++;

                   // If we're out of retries, the ingest fails and we set an Alert
                    if ($job->ingest->attempts >= config('ccplus.max_ingest_retries')) {
                        $job->ingest->status = 'Fail';
                        Alert::insert(['yearmon' => $yearmon, 'prov_id' => $setting->prov_id,
                                       'ingest_id' => $job->ingest->id, 'status' => 'Active', 'created_at' => $ts]);
                    } else {
                        $job->ingest->status = 'Retrying';
                    }
                }
            }

           // Clean up and update the database;
           // unless the request is "Pending", remove the job from the queue.
            unset($sushi);
            unset($C5processor);
            $job->ingest->update();
            if ($request_status != "Pending") {
                $job->delete();
            }
            $job_count = DB::table($jobs_table . ' as job')
                           ->join($ingestlogs_table . ' as ing', 'ing.id', '=', 'job.ingest_id')
                           ->where('consortium_id', '=', $consortium->id)
                           ->whereIn('ing.status', $runable_status)
                           ->count();
        }   // While there are jobs in the queue
    }

    /**
     * Pull the URLs of "Active" ingests across all active consortia in the system.
     * Return T/F if the job's URL matches any of them.
     *
     * @param  string  $job_url
     * @return boolean result
     */
    private function hasActiveIngest($job_url)
    {
        foreach ($this->all_consortia as $_con) {
            $_db = 'ccplus_' . $_con->ccp_key;
            $_urls = DB::table($_db . '.ingestlogs as ing')
                         ->distinct()
                         ->join($_db . '.sushisettings as sus', 'sus.id', '=', 'ing.sushisettings_id')
                         ->join($_db . '.providers as prv', 'prv.id', '=', 'sus.prov_id')
                         ->where($_db . '.ing.status', 'Active')
                         ->select($_db . '.prv.server_url_r5')
                         ->get();
            foreach ($_urls as $_url) {
                if ($_url->server_url_r5 == $job_url) {
                    return true;
                }
            }
        }
        return false;
    }
}
