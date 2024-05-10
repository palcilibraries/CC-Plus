<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use DB;
use App\Sushi;
use App\Report;
use App\Consortium;
use App\Provider;
use App\Institution;
use App\Counter5Processor;
use App\FailedHarvest;
use App\HarvestLog;
use App\ConnectionField;

/*
 * NOTE:
 *   As of 1/10/2020, it looks like the HOST system needs to set the PHP memory_limit
 *   to AT LEAST 1024 Meg. Encoding/Decoding the JSON for processing can be a real PIG,
 *   and is dependent on the size of the report coming back from the providers
 *   (60-100K characters is not uncommon). This script will fail and die with a
 *   shutdown exception (that cannot be caught) if it cannot allocate enough memory.
 */
class SushiBatch extends Command
{
    /**
     * The name and signature for the Sushi Batch processing console command.
     * @var string
     */
    protected $signature = 'ccplus:sushibatch {consortium : The Consortium ID or key-string}
                             {--A|auto : Limit harvest to provider day_of_month [FALSE]}
                             {--M|month= : YYYY-MM to process [lastmonth]}
                             {--P|provider= : Provider ID to process [ALL]}
                             {--I|institution= : Institution ID to process [ALL]}
                             {--R|report= : Master report NAME to harvest [ALL]}
                             {--K|keep : Preserve, and ADD TO, existing data [FALSE]}';

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
     * ----------------------------
     *   ccplus:sushibatch is intended to be as an artisan command for bulk-loading report data without
     *   involving the CCPlus queuing system. Providers or institutions with is_active=false are ignored.
     *   Report data is stored in the XX_report_data tables, and can replace any existing data.
     *   HarvestLog and, if necessary, FailedHarvest records are created to record processing results.
     *
     * @return mixed
     */
    public function handle()
    {
       // Get the consortium as ID or Key
        $conarg = $this->argument('consortium');
        $consortium = Consortium::find($conarg);
        if (is_null($consortium)) {
            $consortium = Consortium::where('ccp_key', '=', $conarg)->first();
        }
        if (is_null($consortium)) {
            $this->line('Cannot Load Consortium: ' . $conarg);
            return 0;
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
        $replace = ($this->option('keep')) ? false : true;

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

       // Get detail on (master) reports requested
        if (strtoupper($rept) == 'ALL') {
            $requested_reports = Report::where('parent_id', '=', 0)->pluck('name')->toArray();
        } else {
            $requested_reports = Report::where('name', '=', $rept)
                                       ->where('parent_id', '=', 0)
                                       ->pluck('name')->toArray();
        }
        if (count($requested_reports) == 0) {
            $this->error("No matching reports found; only master reports allowed.");
            return 0;
        }

        // Get connection fields
        $all_connection_fields = ConnectionField::get();

       // Get Provider data as a collection regardless of whether we just need one
        if ($prov_id == 0) {
            $providers = Provider::with('SushiSettings', 'SushiSettings.institution', 'SushiSettings.provider',
                                        'SushiSettings.provider.globalProv', 'reports')
                                 ->where('is_active', '=', true)->get();
        } else {
            $providers = Provider::with('SushiSettings','SushiSettings.institution', 'SushiSettings.provider',
                                        'SushiSettings.provider.globalProv', 'reports')
                                 ->where('is_active', '=', true)->where('id', '=', $prov_id)->get();
        }

       // Loop through providers
        $this->line("Harvest begins for " . $consortium->ccp_key . " at " . date("Y-m-d H:i:s"));
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
            if (count($provider->sushiSettings) == 0) {
                $this->line($provider->name . " has no sushi credentials defined; skipping...");
                continue;
            }

           // Loop through all sushiSettings for this provider
            $this->line("Processing: " . $provider->name);
            $settings = $provider->sushiSettings->where('status', 'Enabled');
            foreach ($settings as $setting) {
               // Skip this setting if we're just processing a single inst and the IDs don't match
                if (
                    (!$setting->institution->is_active) ||
                     ($inst_id != 0 &&
                     $setting->inst_id != $inst_id)
                ) {
                    continue;
                }

                // setup array of required connectors for buildUri
                $connectors = $all_connection_fields->whereIn('id',$setting->provider->globalProv->connectors)
                                                    ->pluck('name')->toArray();

               // Create a new processor object
                $C5processor = new Counter5Processor($provider->id, $setting->inst_id, $begin, $end, $replace);

               // Loop through all reports defined as available for this provider
                foreach ($provider->reports as $report) {
                   // if this report hasn't been requested (cmd-line argument above), then skip it
                    if (!in_array($report->name, $requested_reports)) {
                        continue;
                    }

                   // Create a new Sushi object
                    $sushi = new Sushi($begin, $end);
                    $request_uri = $sushi->buildUri($setting, $connectors, 'reports', $report);

                   // Set output filename for raw data. Create the folder path, if necessary
                    if (!is_null(config('ccplus.reports_path'))) {
                        $full_path = $report_path . '/' . $setting->institution->name . '/' . $provider->name . '/';
                        if (!is_dir($full_path)) {
                            mkdir($full_path, 0755, true);
                        }
                        $raw_filename = $report->name . '_' . $begin . '_' . $end . '.json';
                        $sushi->raw_datafile = $full_path . $raw_filename;
                    }

                   // Create new HarvestLog record; if one already exists, use it instead
                    try {
                        $harvest = HarvestLog::create(['status' => 'Active', 'sushisettings_id' => $setting->id,
                                           'report_id' => $report->id, 'yearmon' => $yearmon,
                                           'attempts' => 0]);
                    } catch (QueryException $e) {
                        $errorCode = $e->errorInfo[1];
                        if ($errorCode == '1062') {
                            $harvest = HarvestLog::where([['sushisettings_id', '=', $setting->id],
                                                        ['report_id', '=', $report->id],
                                                        ['yearmon', '=', $yearmon]
                                                       ])->first();
                            $this->line('Harvest ' . '(ID:' . $harvest->id . ') already defined for setting: ' .
                                        $setting->id . ', ' . $report->name . ':' . $yearmon . ').');
                            $harvest->status = 'ReQueued';
                            $harvest->save();
                        } else {
                            $this->line('Failed adding to HarvestLog! Error code:' . $errorCode);
                            return 0;
                        }
                    }

                   // Loop up to retry-limit asking for the report. sleep_time and retry_limit
                   // can go into a config file as in:
                   //    $sleep_time = config('ccplus.sushi_retry_sleep');
                   //    $retry_limit = config('ccplus.sushi_retry_limit');
                   // Just hardcoded here instead...
                    $sleep_time = 30;   // 30 seconds between retries
                    $retry_limit = 20;  // max 20 retries
                    $retries = 0;
                    $req_state = "Pending";
                    $ts = date("Y-m-d H:i:s");

                   // Sleeps and retries if request is queued.
                    while ($retries <= $retry_limit  && $req_state == "Pending") {
                       // Make the request
                        $req_state = $sushi->request($request_uri);

                       // Check status of request
                        if ($req_state == "Pending") {
                            $retries++;
                            $this->line('Report pending .... sleeping (' . $retries . ')');
                            sleep($sleep_time);
                            continue;
                        }

                       // If request failed, insert a FailedHarvest record
                        if ($req_state == "Fail") {
                            FailedHarvest::insert(['harvest_id' => $harvest->id, 'process_step' => $sushi->step,
                                                  'error_id' => $sushi->error_code, 'detail' => $sushi->detail,
                                                  'created_at' => $ts]);
                            $this->line($sushi->message . $sushi->detail);
                            $harvest->status = 'Fail';
                            $harvest->update();
                            continue 2;
                        }

                       // Print out non-fatal message from sushi request
                        if ($sushi->message != "") {
                            $this->line($sushi->message . $sushi->detail);
                        }
                    } // while Pending with retries remaining

                   // Validate report
                    try {
                        $valid_report = $sushi->validateJson();
                    } catch (\Exception $e) {
                       // Update logs
                        FailedHarvest::insert(['harvest_id' => $harvest->id, 'process_step' => 'COUNTER',
                                               'error_id' => 9100, 'detail' => 'Validation error: ' . $e->getMessage(),
                                               'created_at' => $ts]);
                        $this->line("COUNTER report failed validation : " . $e->getMessage());
                        $harvest->status = 'Fail';
                        $harvest->update();
                        continue;
                    }

                   // Process the report and save in the database
                    if ($valid_report) {
                        $_status = $C5processor->{$report->name}($sushi->json);
                        if ($_status = 'Saved') {
                            $this->line($report->name . " report data saved for " . $setting->institution->name);
                            $harvest->status = 'Success';
                            $harvest->rawfile = $raw_filename;
                            $harvest->update();

                            // Keep track last successful for this sushisetting
                            if ($yearmon != $setting->last_harvest) {
                                $setting->last_harvest = $yearmon;
                                $setting->update();
                            }
                        }
                    }
                    unset($sushi);
                    unset($harvest);
                }  // foreach reports

                unset($C5processor);
            }  // foreach sushisettings
        }  // foreach providers

        $this->line("Harvest ends at : " . date("Y-m-d H:i:s"));
        return 1;
    }
}
