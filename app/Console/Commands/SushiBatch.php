<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use DB;
use App\Sushi;
use App\Report;
use App\Consortium;
use App\Provider;
use App\Institution;
use App\Counter5Processor;
use App\FailedIngest;
use App\IngestLog;

/*
 * NOTE:
 *   As of 1/10/2020, it looks like the HOST system needs to set the PHP memory_limit
 *   to AT LEAST 1024 Meg. Encoding/Decoding the JSON for processing can be a real PIG,
 *   and is dependent on the size of the report coming back from the providers
 *   (60-100K characters is not uncommon). This script will fail and die with a
 *   shutdown exception (that cannot be caught) if it cannot allocate enough memory.
 */
class SushiBatchCommand extends Command
{
    /**
     * The name and signature for the Sushi Batch processing console command.
     * @var string
     */
    protected $signature = 'ccplus:sushibatch {consortium : The Consortium ID or key-string}
                             {--A|auto : Limit ingest to provider day_of_month [FALSE]}
                             {--M|month= : YYYY-MM to process  [lastmonth]}
                             {--P|provider= : Provider ID to process [ALL]}
                             {--I|institution= : Institution ID to process[ALL]}
                             {--R|report= : Master report NAME to ingest [ALL]}
                             {--retry= : ID of a failedingest to rerun}';
                // Note: may want a "clear/replace existing data" option

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run SUSHI harvest(s) for a consortium sequentially';

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
            $this->line('Cannot Load Consortium: ' . $conarg);
            exit;
        }

       // Aim the consodb connection at specified consortium's database and initialize the
       // path for keeping raw report responses
        config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
        DB::reconnect();
        if (!is_null(config('ccplus.reports_path'))) {
            $report_path = config('ccplus.reports_path') . $consortium->ccp_key;
        }

       // Handle input options
        $month  = is_null($this->option('month')) ? 'lastmonth' : $this->option('month');
        $prov_id = is_null($this->option('provider')) ? 0 : $this->option('provider');
        $inst_id = is_null($this->option('institution')) ? 0 : $this->option('institution');
        $rept = is_null($this->option('report')) ? 'ALL' : $this->option('report');
        $retry_id = is_null($this->option('retry')) ? 0 : $this->option('retry');

       // Setup month string for pulling the report and begin/end for parsing
       //
        if (strtolower($month) == 'lastmonth') {
            $begin = date("Y-m", mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
        } else {
            $begin = date("Y-m", strtotime($month));
        }
        $yearmon = $begin;
        $begin .= '-01';
        $end = $yearmon . '-' . date('t', strtotime($begin));

       // Get detail on reports requested
        if (strtoupper($rept) == 'ALL') {
            $requested_reports = Report::all()->pluck('name')->toArray();
        } else {
            $requested_reports = Report::where('name', '=', $rept)->pluck('name')->toArray();
        }
        if (count($requested_reports) == 0) {
            $this->error("No matching reports found");
            exit;
        }

       // Get Provider data as a collection regardless of whether we just need one
        if ($prov_id == 0) {
            $providers = Provider::where('is_active', '=', true)->get();
        } else {
            $providers = Provider::where('is_active', '=', true)->where('id', '=', $prov_id)->get();
        }

       // Get Institution data
        if ($inst_id == 0) {
            $institutions = Institution::where('is_active', '=', true)->pluck('name', 'id');
        } else {
            $institutions = Institution::where('is_active', '=', true)->where('id', '=', $inst_id)
                                       ->pluck('name', 'id');
        }

       // Loop through providers
        $this->line("Ingest begins for " . $consortium->ccp_key . " at " . date("Y-m-d H:i:s"));
        foreach ($providers as $provider) {
           // If running as "Auto" and today is not the day to run, skip silently to next provider
            if ($this->option('auto') && $provider->day_of_month != date('j')) {
                continue;
            }

           // Skip this provider if there are no reports defined for it
            if (count($provider->reports) == 0) {
                $this->line($provider->name . " has no reports defined; skipping...");
                continue;
            }

           // Skip this provider if there are no sushi settings for it
            if (count($provider->sushisettings) == 0) {
                $this->line($provider->name . " has no sushi settings defined; skipping...");
                continue;
            }

           // Loop through all sushisettings for this provider
            foreach ($provider->sushisettings as $setting) {
               // Skip this setting if we're just processing a single inst and the IDs don't match
                if (($inst_id != 0) && ($setting->inst_id != $inst_id)) {
                    continue;
                }

               // Create a new processor object
                $C5processor = new Counter5Processor($provider->id, $setting->inst_id, $begin, $end, "");

               // Loop through all reports defined as available for this provider
                foreach ($provider->reports as $report) {
                    // Only accept the 4 COUNTER-5 master reports
                    if ($report->name!="TR" && $report->name!="PR" && $report->name!="DR" && $report->name!="IR") {
                         $this->error("Unsupported report? : " . self::$report->name . " defined for: " .
                                      self::$setting->provider->name);
                         continue;
                    }

                   // if this report hasn't been requested (cmd-line argument above), then skip it
                    if (!in_array($report->name, $requested_reports)) {
                        continue;
                    }
                    $this->line("Requesting " . $report->name . " from " . $provider->name .
                                " for " . $setting->institution->name);

                   // Create a new Sushi object
                    $sushi = new Sushi($begin, $end);

                   // Set output filename for raw data. Create the folder path, if necessary
                    if (!is_null(config('ccplus.reports_path'))) {
                        $full_path = $report_path . '/' . $setting->institution->name . '/' . $provider->name . '/';
                        if (!is_dir($full_path)) {
                            mkdir($full_path, 0755, true);
                        }
                        $sushi->raw_datafile = $full_path . $report->name . '_' . $begin . '_' . $end . '.json';
                    }

                   // Create new IngestLog record; if one already exists, use it instead
                    try {
                        $ingest = IngestLog::create(['status' => 'Active', 'sushisettings_id' => $setting->id,
                                           'report_id' => $report->id, 'yearmon' => $yearmon,
                                           'attempts' => 0]);
                    } catch (QueryException $e){
                        $errorCode = $e->errorInfo[1];
                        if ($errorCode == '1062'){
                            $ingest = IngestLog::where([['sushisettings_id', '=', $setting->id],
                                                        ['report_id', '=', $report->id],
                                                        ['yearmon', '=', $yearmon]
                                                       ])->first();
                            $this->line('Ingest ' . '(ID:' . $ingest->id . ') already defined for setting: ' .
                                        $setting->id . ', ' . $report->name . ':' . $yearmon . ').');
                        } else {
                            $this->line('Failed adding to IngestLog! Error code:' . $errorCode);
                            exit;
                        }
                    }

                   // Construct URI for the request
                    $request_uri = $sushi->buildUri($setting, $report);

                   // Loop up to retry-limit asking for the report
                    $retries = 0;
                    $req_state = "Queued";
                    $ts = date("Y-m-d H:i:s");

                   // Sleeps for sushi_retry_sleep seconds, and retries sushi_retry_limit times if request is queued.
                    while ($retries <= config('ccplus.sushi_retry_limit')  && $req_state == "Queued") {
                       // Make the request
                        $req_state = $sushi->request($request_uri);

                       // Check status of request
                        if ($req_state == "Queued") {
                            $retries++;
                            $this->line('Report queued for processing, sleeping (' . $retries . ')');
                            $sushi->retrySleep();
                            continue;
                        }

                       // If request failed, insert an IngestLog and a FailedIngest record
                        if ($req_state == "Fail") {
                            FailedIngest::insert(['ingest_id' => $ingest->id, 'process_step' => $sushi->step,
                                                  'error_id' => $sushi->error_id, 'detail' => $sushi->detail,
                                                  'created_at' => $ts]);
                            $this->line($sushi->message . $sushi->detail);
                            $ingest->status = 'Fail';
                            $ingest->update();
                            continue 2;
                        }

                       // Print out non-fatal message from sushi request
                        if ($sushi->message != "") {
                            $this->line($sushi->message . $sushi->detail);
                        }
                    } // while Queued with retries remaining

                   // Validate report
                    try {
                        $valid_report = $sushi->validateJson();
                    } catch (\Exception $e) {
                       // Update logs
                        FailedIngest::insert(['ingest_id' => $ingest->id,'process_step' => 'COUNTER','error_id' => 100,
                                              'detail' => 'Validation error: ' . $e->getMessage(),
                                              'created_at' => $ts]);
                        $this->line("COUNTER report failed validation : " . $e->getMessage());
                        $ingest->status = 'Fail';
                        $ingest->update();
                        continue;
                    }

                   // Process the report and save in the database
                    if ($valid_report) {
                        $_status = $C5processor->{$report->name}($sushi->json);
                        if ( $_status = 'Saved') {
                            $this->line($report->name . " report data successfully saved.");
                            $ingest->status = 'Success';
                            $ingest->update();
                        }
                    }
                    unset($sushi);
                    unset($ingest);
                }  // foreach reports

                unset($C5processor);
            }  // foreach sushisettings

        }  // foreach providers

        $this->line("Ingest ends at : " . date("Y-m-d H:i:s"));
    }
}
