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
use App\CcplusError;
use App\Counter5Processor;
use \ubfr\c5tools\Report as RawReport;
use \ubfr\c5tools\JsonR5Report;
use \ubfr\c5tools\CheckResult;
use \ubfr\c5tools\ParseException;

class C5TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccplus:C5test {consortium : Consortium ID}
                             {provider : Provider ID}
                             {institution : Institution ID}
                             {report : Report Name to request}
                             {month : YYYY-MM of the dataset}
                             {infile : The input file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Counter processing for a given input file';

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
        $prov_id = $this->argument('provider');
        $inst_id = $this->argument('institution');
        $rept = $this->argument('report');
        $month  = $this->argument('month');
        $infile = $this->argument('infile');

       // Aim the consodb connection at specified consortium's database and setup
       // path for keeping raw report responses
        config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
        DB::reconnect();

       // Get Provider data as a collection regardless of whether we just need one
        $providers = Provider::where('is_active', '=', true)->where('id', '=', $prov_id)->get();

       // Get Institution data
        $institutions = Institution::where('is_active', '=', true)->where('id', '=', $inst_id)
                                       ->pluck('name', 'id');

       // Loop on providers
        $logmessage = false;
        foreach ($providers as $provider) {
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
                $C5processor = new Counter5Processor($provider->id, $setting->inst_id, $month, $month, "");

               // Loop through all reports for this provider
                foreach ($provider->reports as $report) {
                    if ($report->name != $rept) {
                        continue;
                    }
                    $this->line("Processing " . $report->name . " for " . $provider->name);

                    $json_text = file_get_contents($infile);
                    if ($json_text === false) {
                        $this->line("System Error - reading file {$infile} failed");
                        exit;
                    }

                   // Issue a warning if it looks like we'll run out of memory
                    $mem_avail = intval(ini_get('memory_limit'));
                    $body_len = strlen($json_text());
                    $mem_needed = ($body_len * 8) + memory_get_usage(true);
                    if ($mem_needed > ($mem_avail * 1024 * 1024)) {
                        $mb_need = intval($mem_needed / (1024 * 1024));
                        echo "Warning! Projected memory required: " . $mb_need . "Mb but only " .
                                                                    $mem_avail . "Mb available\n";
                        echo "-------> Decoding this report may exhaust system memory (JSON len = $body_len)\n";
                    }

                   // Decode JSON response
                    $json = json_decode($json_text);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->line("Error decoding JSON - " . json_last_error_msg());
                        exit;
                    }

                   // Make sure $json is a proper object
                    if (! is_object($json)) {
                        $this->line('JSON must be an object, found ' . (is_array($json) ? 'an array' : 'a scalar'));
                        exit;
                    }

                   // Validate report
                    try {
                        $valid_report = self::validateJson($json);
                    } catch (\Exception $e) {
                        $this->line("COUNTER Validation Failed: " . $e->getMessage());
                    }

                   // Store the report if it's valid
                    if ($valid_report) {
                        $this->line("Data valid, but skipping processing...");
                        // $result = $C5processor->{$report->name}($json);
                    }
                }  // foreach reports
            }  // foreach sushisettings
        }  // foreach providers
        $this->line("Memory Usage : " . memory_get_usage() . " / " . memory_get_usage(true));
        $this->line("Peak Usage: " . memory_get_peak_usage() . " / " . memory_get_peak_usage(true));
        $this->line("Test completed: " . date("Y-m-d H:i:s"));
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
        try {
            $report = new JsonR5Report($json);
            $checkResult = $report->getCheckResult();
        } catch (\Exception $e) {
            $checkResult = new CheckResult();
            try {
                $checkResult->fatalError($e->getMessage());
            } catch (ParseException $e) {
                // ignore
            }
            $message = $checkResult->asText();
            throw new \Exception($message());
        }
        return true;
    }
}
