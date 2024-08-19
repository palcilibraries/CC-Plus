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
use App\SushiSetting;
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
                             {--P|provider= : Global Provider ID to process [ALL]}
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
        $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
        if (strtoupper($rept) == 'ALL') {
            $requested_reports = $master_reports->pluck('name')->toArray();
        } else {
            $requested_reports = $master_reports->where('name',$rept)->pluck('name')->toArray();
        }
        if (count($requested_reports) == 0) {
            $this->error("No matching reports found; only master reports allowed.");
            return 0;
        }

        // Get connection fields
        $all_connection_fields = ConnectionField::get();

       // Get all active records from the consortium providers table
        if ($prov_id == 0) {
            $conso_providers = Provider::with('reports')->where('is_active', true)->get();
        } else {
            $conso_providers = Provider::with('reports')->where('is_active', true)->where('global_id',$prov_id)->get();
        }

       // Get sushi settings for all the global_ids based on the
        $global_ids = $conso_providers->pluck('global_id')->toArray();
        $settings = SushiSetting::with('institution', 'provider')->where('status', 'Enabled')
                                ->whereIn('prov_id',$global_ids)->get();

       // Loop through global providers
        $this->line("Harvest begins for " . $consortium->ccp_key . " at " . date("Y-m-d H:i:s"));

       // Loop through all sushiSettings for this provider
        foreach ($settings as $setting) {
           // Skip this setting if we're just processing a single inst and the IDs don't match
            if ( (!$setting->institution->is_active) || ($inst_id!=0 && $setting->inst_id!=$inst_id)) {
                continue;
            }

           // setup array of required connectors for buildUri
            $connectors = $all_connection_fields->whereIn('id',$setting->provider->connectors)->pluck('name')->toArray();

           // Create a new processor object
            $C5processor = new Counter5Processor($setting->prov_id, $setting->inst_id, $begin, $end, $replace);

           // Get (conso) provider(s) for the global provider
            $providers = $conso_providers->where('global_id',$setting->prov_id);
            $conso_connection = $providers->where('inst_id',1)->first();
            $conso_reports = ($conso_connection) ? $conso_connection->reports->pluck('id')->toArray() : [];

           // Loop through matching (conso) providers
            $this->line("Processing: " . $setting->provider->name);
            foreach ($providers as $provider) {
               // If running as "Auto" and today is not the day to run, skip silently to next provider
                if ($this->option('auto') && $provider->day_of_month != date('j')) {
                    continue;
                }

               // if the provider is inst-assigned, skip it on mismatch to sushi setting inst_id
                if ($provider->inst_id>1 && $provider->inst_id != $setting->inst_id) {
                    continue;
                }

                // De-dupe provider reports against $conso_reports and skip this provider if no unique reports
                $prov_report_ids = $provider->reports->pluck('id')->toArray();
                if ($provider->inst_id>1 && $conso_connection) {
                    $prov_report_ids = array_intersect( $prov_report_ids, array_diff($prov_report_ids, $conso_reports) );
                }
                if (count($prov_report_ids) == 0) continue;
                $reports = $master_reports->whereIn('id',$prov_report_ids)->whereIn('name',$requested_reports);

               // Loop through all the reports
                foreach ($reports as $report) {

                    // if the provider is inst-assigned, and the conso-copy is already getting it, skip it
                    if ($provider->inst_id>1 && in_array($report->id,$conso_reports)) {
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
                    $source = ($provider->inst_id == 1) ? "C" : "I";

                   // Create new HarvestLog record; if one already exists, use it instead
                    try {
                        $harvest = HarvestLog::create(['status' => 'Active', 'sushisettings_id' => $setting->id,
                                           'report_id' => $report->id, 'yearmon' => $yearmon, 'source' => $source,
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
                            $harvest->error_id = $sushi->error_code;
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
                        $harvest->error_id = 9100;
                        $harvest->status = 'Fail';
                        $harvest->update();
                        continue;
                    }

                   // Process the report and save in the database
                    if ($valid_report) {
                        $_status = $C5processor->{$report->name}($sushi->json);
                        if ($_status = 'Success') {
                            $this->line($report->name . " report data saved for " . $setting->institution->name);
                            $harvest->status = 'Success';
                            $harvest->rawfile = $raw_filename;
                            $harvest->update();

                            // Keep track last successful for this sushisetting
                            if ($yearmon != $setting->last_harvest) {
                                $setting->last_harvest = $yearmon;
                                $setting->update();
                            }

                            // Successfully processed the report - clear out any existing "failed" records
                            $deleted = FailedHarvest::where('harvest_id', $harvest->id)->delete();
                        }
                    }
                    unset($sushi);
                    unset($harvest);
                } // foreach reports
            }     // foreach (conso-reports)

            unset($C5processor);
        }  // foreach sushisettings

        $this->line("Harvest ends at : " . date("Y-m-d H:i:s"));
        return 1;
    }
}
