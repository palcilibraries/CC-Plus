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

class SushiTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sushi:test {consortium : The Consortium ID}
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
    protected $description = 'Testing ingest...';

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

       // Handle input options
        $month  = is_null($this->option('month')) ? 'lastmonth' : $this->option('month');
        $prov_id = is_null($this->option('provider')) ? 0 : $this->option('provider');
        $inst_id = is_null($this->option('institution')) ? 0 : $this->option('institution');
        $report = is_null($this->option('report')) ? 'ALL' : $this->option('report');
        $retry_id = is_null($this->option('retry')) ? 0 : $this->option('retry');

       // Setup month string for pulling the report and Begin/End for parsing
       //
        if (strtolower($month) == 'lastmonth') {
            $Begin = date("Y-m", mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
        } else {
            $Begin = date("Y-m", strtotime($month));
        }
        $yearmon = $Begin;
        $End = $Begin;
        $Begin .= '-01';
        $End .= '-' . date('t', strtotime($End . '-01'));

       // Get detail on reports requested
        if (strtoupper($report) == 'ALL') {
            $reports = Report::pluck('name', 'id');
        } else {
            $reports = Report::where('name', '=', $report)->pluck('name', 'id');
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
            $providers = Provider::where('is_active', '=', true)->pluck('name', 'id');
        } else {
            $providers = Provider::findOrFail($prov_id)->where('is_active', '=', true)
                                   ->pluck('name', 'id');
        }

       // Loop through all vendors
        $logmessage = false;
        $client = new Client();   //GuzzleHttp\Client
        foreach ($providers as $provider) {
          // Skip to next provider if today is not the day to run
            if ($provider->sushisettings->day_of_month != date('j')) {
                continue;
            }
            if ($logmessage) {
                $this->line("Sushi Requests Begin for Consortium: " . $consortium->ccp_key);
            }
            $clean_base = preg_replace('/\/reports(\/?)$/', '/', $provider->server_url_r5);
            $base_uri = $provider->server_url_r5 . 'reports/';
            $uri_args = "?begin_date=" . $Begin . "&end_date=" . $End;

          // Loop through all sushisettings for this provider
            foreach ($provider->sushisettings as $setting) {
              // Construct and execute the Request
              // (Tolerate and handle server urls that end with "/reports/")
                $uri_args .= "&customer_id=" . $setting->CustRefID;
                $uri_args .= "&requestor_id=" . $setting->RequestorID;

              // Loop through all sushisettings for this provider
                foreach ($reports as $report) {
                  // setup attributes for this report
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
                    $cur_state = "queued";
                    while ($queue_retries <= SUSHI_RETRY_LIMIT && $cur_state == "queued") {
                        $result = $client->get($request_uri);
                    }
                }
            }
        }
        $this->line("... and that's all I have to say about that!");
    }
}
