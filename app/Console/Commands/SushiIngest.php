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
// use \ubfr\c5tools\Report as RawReport;
use \ubfr\c5tools\JsonR5Report;

class SushiIngestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sushi:ingest {consortium : The Consortium ID}
                             {--A|auto : Limit ingest to provider day_of_month [FALSE]}
                             {--M|month= : YYYY-MM to process  [lastmonth]}
                             {--P|provider= : Provider ID to process [ALL]}
                             {--I|institution= : Institution ID to process[ALL]}
                             {--R|report= : Master report NAME to ingest [ALL]}
                             {--retry= : ID of a failedingest to rerun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run SUSHI Ingest for a single consortium';

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
       // Required arguments
        $con_id = $this->argument('consortium');
        $consortium = Consortium::findOrFail($con_id);

       // Aim the consodb connection at specified consortium's database and setup
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
        $end = $begin;
        $begin .= '-01';
        $end .= '-' . date('t', strtotime($end . '-01'));

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
        $sushi = new Sushi($begin, $end);

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

               // Create the processor object
                $C5processor = new Counter5Processor($provider->id, $setting->inst_id, $begin, $end, "");

               // Loop through all reports defined as available for this provider
                foreach ($provider->reports as $report) {
                    // Only accept the 4 COUNTER-5 master reports
                    if (
                        $report->name != "TR"
                        && $report->name != "PR"
                        && $report->name == "DR"
                          && $report->name == "IR"
                    ) {
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

                   // Set output filename for raw data. Create the folder path, if necessary
                    if (!is_null(config('ccplus.reports_path'))) {
                        $full_path = realpath($report_path . '/' . $setting->institution->name . '/' .
                                              $provider->name . '/');
                        if (!is_dir($full_path)) {
                            mkdir($full_path, 0755, true);
                        }
                        $raw_datafile = $full_path . '/' . $report->name . '_' . $begin . '_' . $end . '.json';
                    }

                   // Construct URI for the request
                    $request_uri = $sushi->buildUri($setting, $report);
                    // $this->line("Requesting via: " . $request_uri);

                   // Loop up to retry-limit asking for the report
                    $retries = 0;
                    $req_state = "Queued";
                    $ts = date("Y-m-d H:i:s");

                   // Sleeps for sushi_retry_sleep seconds, and retries sushi_retry_limit times if request is queued.
                    while ($retries <= config('ccplus.sushi_retry_limit')  && $req_state == "Queued") {
                       // Make the request
                        $json = $sushi->request($request_uri);

                       // Check status of request and returned $json
                        if ($sushi->status == "Queued") {
                            $retries++;
                            $this->line('Report queued for processing, sleeping (' . $retries . ')');
                            self::retrySleep();
                            continue;
                        }

                       // If request failed, insert an IngestLog and a FailedIngest record
                        if ($sushi->status == "Fail") {
                            IngestLog::insert(['sushisettings_id' => $setting->id, 'report_id' => $report->id,
                                               'yearmon' => $yearmon, 'status' => 'Failed', 'created_at' => $ts]);
                            FailedIngest::insert(['sushisettings_id' => $setting->id, 'report_id' => $report->id,
                                                  'yearmon' => $yearmon, 'process_step' => $sushi->step,
                                                  'error_id' => 10, 'detail' => $sushi->detail, 'created_at' => $ts]);
                            $this->line($sushi->message . $sushi->detail);
                            continue 2;
                        }

                       // Print out non-fatal message from sushi request
                        if ($sushi->message != "") {
                            $this->line($sushi->message . $sushi->detail);
                        }
                        $req_state = "done";
                    } // while Queued with retries remaining

                   // Validate report
                    try {
                        $validReport = self::validateJson($json);
                    } catch (\Exception $e) {
                       // Update logs
                        FailedIngest::insert(['sushisettings_id' => $setting->id, 'report_id' => $report->id,
                                              'yearmon' => $yearmon, 'process_step' => 'COUNTER', 'error_id' => 100,
                                              'detail' => 'Validation error: ' . $e->getMessage(),
                                              'created_at' => $ts]);
                        IngestLog::insert(['sushisettings_id' => $setting->id, 'report_id' => $report->id,
                                           'yearmon' => $yearmon, 'status' => 'Failed', 'created_at' => $ts]);
                        $this->line("COUNTER report failed validation : " . $e->getMessage());
                        continue;
                    }

                    // Save raw data
                    if (!is_null(config('ccplus.reports_path'))) {
                        file_put_contents($raw_datafile, json_encode($json, JSON_PRETTY_PRINT));
                    }

                   // Process the report and save in the database
                    $_status = $C5processor->{$report->name}($json);
                    if ( $_status = 'Saved') {
                        $this->line("Data saved successfully for: " . $report->name . " report.");
                    }

                   // Add record to ingest log
                    IngestLog::insert(['sushisettings_id' => $setting->id, 'report_id' => $report->id,
                                       'yearmon' => $yearmon, 'status' => $_status, 'created_at' => $ts]);
                }  // foreach reports
            }  // foreach sushisettings
        }  // foreach providers

        $this->line("Ingest ends at : " . date("Y-m-d H:i:s"));
    }

    protected static function retrySleep()
    {
        $this->line("Queued by provider - sleeping...");
        sleep(config('ccplus.sushi_retry_sleep'));
        $this->line("Retrying");
    }

    protected static function validateJson($json)
    {
        // Confirm Report_Header is present and a valid object, store in $header
        if (! property_exists($json, 'Report_Header')) {
            throw new \Exception('Report_Header is missing');
        }
         $header = $json->Report_Header;
        if (! is_object($header)) {
            throw new \Exception('Report_Header must be an object, found ' .
                                 (is_array($header) ? 'an array' : 'a scalar'));
        }

        // Get release value; we're only handling Release 5
        if (! property_exists($header, 'Release')) {
            throw new \Exception("Could not determine COUNTER Release");
        }
        if (! is_scalar($header->Release)) {
            throw new \Exception('Report_Header.Release must be a scalar, found an ' .
                                 (is_array($header->Release) ? 'array' : 'object'));
        }
         $release = trim($header->Release);
        if ($release !== '5') {
            throw new \Exception("COUNTER Release '{$release}' invalid/unsupported");
        }

        // Make sure there are Report_Items to process
        if (!isset($json->Report_Items)) {
            throw new \Exception("SUSHI error: no Report_Items included in JSON response.");
        }

        // Make sure there are Report_Items to process
         $report = new JsonR5Report($json);
         return $report;
    }
}
