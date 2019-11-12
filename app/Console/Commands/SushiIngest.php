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
        $report_path = env('CCP_REPORTS') . $consortium->ccp_key;

       // Handle input options
        $auto  = is_null($this->option('auto')) ? false : true;
        $month  = is_null($this->option('month')) ? 'lastmonth' : $this->option('month');
        $prov_id = is_null($this->option('provider')) ? 0 : $this->option('provider');
        $inst_id = is_null($this->option('institution')) ? 0 : $this->option('institution');
        $report = is_null($this->option('report')) ? 'ALL' : $this->option('report');
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
        if (strtoupper($report) == 'ALL') {
            $reports = Report::all();
        } else {
            $reports = Report::where('name', '=', $report)->get();
        }
        if ($reports->isEmpty()) {
            $this->error("No matching reports found");
            exit;
        }

        // Get Institution data
        if ($inst_id == 0) {
            $institutions = Institution::where('is_active', '=', true)->pluck('name', 'id');
        } else {
            $institutions = Institution::findOrFail($inst_id)->where('is_active', '=', true)
                                         ->pluck('name', 'id');
        }

       // Get Provider data
        if ($prov_id == 0) {
            $providers = Provider::where('is_active', '=', true)->get();
        } else {
            $providers = Provider::findOrFail($prov_id)->where('is_active', '=', true)->get();
        }

       // Loop through all vendors
        $logmessage = false;
        $client = new Client();   //GuzzleHttp\Client
        foreach ($providers as $provider) {
          // If running as "Auto", skip silently to next provider if today is not the day to run
            if ($auto && $provider->day_of_month != date('j')) {
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
            $base_uri = preg_replace('/\/?$/', '/', $provider->server_url_r5); // ensure slash-ending
            $uri_args = "/?begin_date=" . $begin . "&end_date=" . $end;

          // Loop through all sushisettings for this provider
            foreach ($provider->sushisettings as $setting) {
              // Construct and execute the Request
                $uri_args .= "&customer_id=" . $setting->customer_id;
                $uri_args .= "&requestor_id=" . $setting->requestor_id;

              // Create the processor object
                $C5processor = new Counter5Processor($provider->id, $setting->inst_id, $begin, $end, "");

              // Loop through all sushisettings for this provider
                foreach ($reports as $report) {
                  // Set output filename
                    if (!is_null(env('CCP_REPORTS'))) {
                        $C5processor->setOutCsv($report_path . '/' . $setting->institution->name . '/' .
                                 $provider->name . '/' . $report->name . $begin . '_' . $end . '.csv');
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

                    while ($queue_retries <= env('SUSHI_RETRY_LIMIT', 20)  && $req_state == "queued") {
                      // Make the request and convert into JSON
                        $result = $client->get($request_uri);
                        $json_result = json_decode($result->getBody());

                        if (isset($json_result->Report_Header) && isset($json_result->Report_Items)) {
                            $req_state = "done";
                        } else {
                            foreach ($json_result as $_resp) {
                                // print_r($resp);
                                if ($_resp->Code == 1011) {
                                    $queue_retries++;
                                    print "Queued ... sleeping: " . $_resp->Message .
                                    "(" . $_resp->Code . ") ...";
                                    sleep(env('SUSHI_RETRY_SLEEP', 30));
                                    print "Retrying\n";
                                    $req_state = "queued";
                                    break;
                                } else {
                                    print "Unknown return status: " . $_resp->Code . "\n";
                                    print "Message: " . $_resp->Message . "\n";
                                    exit();
                                }
                            }
                        }
                    } // while retries remaining

                  // Validate report
                    $C5validator = new Counter5Validator($json_result);

                  // Parse and store the report if it's valid
                    if ($C5validator->{$report->name}()) {
                        $result = $C5processor->{$report->name}($C5validator->report);
                    } else {
                        // Signal / Log / Report error
                        // $C5validator->error holds detail
                    }
                }  // foreach reports
            }  // foreach sushisettings
        }  // foreach providers
        $this->line("Ingest completed: " . date("Y-m-d H:i:s"));
    }
}
