<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use DB;
use App\Report;
use App\Consortium;
use App\Provider;
use App\Institution;
use App\Counter5Validator;
use App\Counter5Processor;
use \ubfr\c5tools\Report as RawReport;
use \ubfr\c5tools\JsonR5Report;
use \ubfr\c5tools\ParseException;

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
                             {--R|report= : Report Name to request [ALL]}
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

       // Aim the consodb connection at specified consortium's database
        config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
        DB::reconnect();
        $report_path = config('ccplus.reports_path') . $consortium->ccp_key;

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
            $rept = Report::all();
        } else {
            $reports = Report::where('name', '=', $rept)->get();
        }
        if ($reports->isEmpty()) {
            $this->error("No matching reports found");
            exit;
        }

       // Get Provider data as a collection regardless of whether we just need one
        if ($prov_id == 0) {
            $providers = Provider::where('is_active', '=', true)->get();
        } else {
            // $providers = Provider::where('is_active', '=', true)->findOrFail($prov_id);
            $providers = Provider::where('is_active', '=', true)->where('id', '=', $prov_id)->get();
        }

       // Get Institution data
        if ($inst_id == 0) {
            $institutions = Institution::where('is_active', '=', true)->pluck('name', 'id');
        } else {
            // $institutions = Institution::findOrFail($inst_id)->where('is_active', '=', true)
            //                              ->pluck('name', 'id');
            $institutions = Institution::where('is_active', '=', true)->where('id', '=', $inst_id)
                                       ->pluck('name', 'id');
        }

       // Loop through providers
        $logmessage = false;
        $client = new Client();   //GuzzleHttp\Client
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

           // Begin setting up the URI for the request
            if ($logmessage) {
                $this->line("Sushi Requests Begin for Consortium: " . $consortium->ccp_key);
            }
            $base_uri = rtrim($provider->server_url_r5,'/') . "/";
            $uri_args = "/?begin_date=" . $begin . "&end_date=" . $end;

           // Loop through all sushisettings for this provider
            foreach ($provider->sushisettings as $setting) {
               // Skip this setting if we're just processing a single inst and the IDs don't match
                if (($inst_id != 0) && ($setting->inst_id != $inst_id)) {
                    continue;
                }

               // Construct and execute the Request
                $uri_args .= "&customer_id=" . $setting->customer_id;
                $uri_args .= "&requestor_id=" . $setting->requestor_id;

               // Create the processor object
                $C5processor = new Counter5Processor($provider->id, $setting->inst_id, $begin, $end, "");

               // Loop through all reports for this provider
                foreach ($provider->reports as $report) {
                    // if ( $report->name =="TR") continue;
                    $this->line("Requesting " . $report->name . " for " . $provider->name);

                   // Set output filename for raw data
                    if (!is_null(config('ccplus.reports_path'))) {
                        $raw_datafile = $report_path . '/' . $setting->institution->name . '/' . $provider->name .
                                        '/' . $report->name . '_' . $begin . '_' . $end . '.json';
                    }

                   // Setup attributes for the request
                    if ($report->name == "TR") {
                        $uri_atts  = "&attributes_to_show=Data_Type%7CAccess_Method%7CAccess_Type%7C";
                        $uri_atts .= "Section_Type%7CYOP";
                    } elseif ($report->name == "DR") {
                        $uri_atts = "";
                    } elseif ($report->name == "PR") {
                        $uri_atts = "&attributes_to_show=Data_Type%7CAccess_Method";
                    } elseif ($report->name == "IR") {
                        $uri_atts = "";
                    } else {
                        $this->error("Unknown report: " . $report->name . " defined for: " .
                                $provider->name);
                        continue;
                    }

                   // Construct URI for the request
                    $request_uri = $base_uri . $report->name . $uri_args . $uri_atts;

                   // Loop up to retry-limit asking for the report
                    $queue_retries = 0;
                    $req_state = "queued";

                   // Error-Handling needs work... and probably needs a class unto itself
                   // This is currently catches "Queued" response and sleeps to retry.
                   // Any other error/exception is treated as fatal...
                    while ($queue_retries <= config('ccplus.sushi_retry_limit')  && $req_state == "queued") {
                       // Make the request and convert into JSON
                        $result = $client->get($request_uri);
                        $json = json_decode($result->getBody());
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new ParseException("Error decoding JSON - " . json_last_error_msg());
                            exit();
                        }

                        if (isset($json->Code)) {
                            if ($json->Code == 1011) {
                                $queue_retries++;
                                $this->line("Queued ... sleeping: " . $json->Message . "(" . $json->Code . ") ...");
                                sleep(config('ccplus.sushi_retry_sleep'));
                                $this->line("Retrying");
                                $req_state = "queued";
                                continue;
                            } else {
                                $this->line("Error returned: (" . $json->Severity . "),  Code: " . $json->Code);
                                $this->line("Message: " . $json->Message);
                                exit();
                            }
                        }
                        if (isset($json->Report_Header)) {
                            if (isset($json->Report_Header->Exceptions)) {
                                foreach ($json->Report_Header->Exceptions as $_exep) {
                                    if ($_exep->Code == 1011) {
                                        $queue_retries++;
                                        $this->line("Queued ... sleeping: " . $_exep->Message . "(" .
                                                                              $_exep->Code . ") ...");
                                        sleep(config('ccplus.sushi_retry_sleep'));
                                        $this->line("Retrying");
                                        $req_state = "queued";
                                        continue 2;
                                    } else {
                                        $this->line("Exception: (" . $_exep->Severity . "), Code: " . $_exep->Code);
                                        $this->line("Message: " . $_exep->Message);
                                        exit();
                                    }
                                }
                            }
                            if (isset($json->Report_Items)) {
                                $req_state = "done";
                                if (!is_null(config('ccplus.reports_path'))) {
                                    file_put_contents($raw_datafile, $json);
                                }
                            } else {
                                $this->line("SUSHI error - no Report_Items! Requested URI was: " . $request_uri);
                                exit;
                            }
                        } else {
                            $this->line("SUSHI error - no Report_Header! Requested URI was: " . $request_uri);
                            exit;
                        }
                    } // while retries remaining

                   // Validate report
                   // $C5validator = new Counter5Validator($json);
                   $validJson = self::validateJson($json);
                   $result = $C5processor->{$report->name}($validJson);

                }  // foreach reports
            }  // foreach sushisettings
        }  // foreach providers
        $this->line("Ingest completed: " . date("Y-m-d H:i:s"));
    }

    protected static function validateJson($json)
    {

        try {
            $release = RawReport::getReleaseFromJson($json);
        } catch (\Exception $e) {
            throw new ParseException("Could not determine COUNTER Release - " . $e->getMessage());
        }
        if ($release !== '5') {
            throw new ParseException("COUNTER Release '{$release}' invalid/unsupported");
        }

        $report = new JsonR5Report($json);
        unset($json);

        return $report;
    }
}
