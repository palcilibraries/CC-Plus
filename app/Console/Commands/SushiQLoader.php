<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use DB;
use App\Consortium;
use App\Provider;
use App\HarvestLog;
use App\Report;
use App\SushiQueueJob;

class SushiQLoader extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccplus:sushiloader {consortium : Consortium ID or key-string}
                                               {--M|month= : YYYY-MM to override day_of_month [lastmonth]}
                                               {--P|provider= : Provider ID to process [ALL]}
                                               {--I|institution= : Institution ID to process [ALL]}
                                               {--R|report= : Master report NAME to harvest [ALL]}
                                               {--K|keep : Preserve, and ADD TO, existing data [FALSE]}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nightly CC-Plus Sushi Queue Loader';

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
     *   ccplus:sushiqloader is intended to be run primarily as a nightly job.
     *      The optional arguments exist to allow the script to be run from the artisan command-line
     *      to add harvests and jobs in a more customized way.
     *   Processing phase-1:
     *      The day-of-month harvest setting for all (active) providers of the given consortium are checked,
     *      and if today is the day, all harvests defined by the sushisettings are added to the HarvestLogs table.
     *      Settings for providers or institutions with is_active=false are ignored.
     *   Processing phase-2:
     *      Any harvests just added in phase-1 are added to the globaldb:jobs queue along with any harvests
     *      that are in a "Retry" state.
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
            return 0;
        }

       // Aim the consodb connection at specified consortium's database and initialize the
       // path for keeping raw report responses
        config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
        DB::reconnect();

       // Handle input options
        $month  = is_null($this->option('month')) ? 'lastmonth' : $this->option('month');
        $prov_id = is_null($this->option('provider')) ? 0 : $this->option('provider');
        $inst_id = is_null($this->option('institution')) ? 0 : $this->option('institution');
        $rept = is_null($this->option('report')) ? 'ALL' : $this->option('report');
        $replace = ($this->option('keep')) ? false : true;

       // Timestamp is now, set yearmon to last month (default) or input value
        $ts = date("Y-m-d H:i:s");
        if (strtolower($month) == 'lastmonth') {
            $override_dom = false;
            $yearmon = date("Y-m", mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
        } else {
            $override_dom = true;   // day_of_month for providers is ignored if --month given
            $yearmon = date("Y-m", strtotime($month));
        }

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

       // Get active provider data
        if ($prov_id == 0) {
            $providers = Provider::with('sushiSettings', 'sushiSettings.institution:id,is_active', 'reports')
                                 ->where('is_active', '=', true)->get();
        } else {
            $providers = Provider::with('sushiSettings', 'sushiSettings.institution:id,is_active', 'reports')
                                 ->where('is_active', '=', true)->where('id', '=', $prov_id)->get();
        }

       // Part I : Load any new harvests (based on today's date) into the HarvestLog table
       // ------------------------------------------------------------------------------
       // Loop through the providers
        foreach ($providers as $provider) {
            // If not overriding day-of-month, and today is not the day, skip to next provider
            if (!$override_dom && $provider->day_of_month != date('j')) {
                continue;
            }

           // Loop through all sushisettings for this provider
            foreach ($provider->sushiSettings as $setting) {
               // If institution is inactive, -or- only processing a single instituution and this isn't it,
               // skip to next setting.
                if (
                    (!$setting->institution->is_active) ||
                     ($inst_id != 0 &&
                     $setting->inst_id != $inst_id)
                ) {
                    continue;
                }

               // Loop through all reports defined as available for this provider
                foreach ($provider->reports as $report) {
                   // if this report isn't in the requested_reports array (defined above), skip it
                    if (!in_array($report->name, $requested_reports)) {
                        continue;
                    }

                   // Insert new HarvestLog record; catch and prevent duplicates
                    try {
                        HarvestLog::insert(['status' => 'New', 'sushisettings_id' => $setting->id,
                                           'report_id' => $report->id, 'yearmon' => $yearmon,
                                           'attempts' => 0, 'created_at' => $ts]);
                    } catch (QueryException $e) {
                        $errorCode = $e->errorInfo[1];
                        if ($errorCode == '1062') {
                            $harvest = HarvestLog::where([['sushisettings_id', '=', $setting->id],
                                                        ['report_id', '=', $report->id],
                                                        ['yearmon', '=', $yearmon]
                                                       ])->first();
                            if ($harvest->status == 'New') { // if existing harvest is "New", don't modify status
                                continue;                    // since Part II will requeue it anyway
                            }
                            $this->line('Harvest ' . '(ID:' . $harvest->id . ') already defined. Updating to retry (' .
                                        'setting: ' . $setting->id . ', ' . $report->name . ':' . $yearmon . ').');
                            $harvest->status = 'ReQueued';
                            $harvest->save();
                        } else {
                            $this->line('Failed adding to HarvestLog! Error code:' . $errorCode);
                            return 0;
                        }
                    }
                } // for each report
            } // for each sushisetting
        } // for each provider

       // Part II : Create queue jobs based on HarvestLogs
       // -----------------------------------------------
        $harvests = HarvestLog::where('status', '=', 'New')->orWhere('status', '=', 'ReQueued')->get();
        foreach ($harvests as $harvest) {
            try {
                $newjob = SushiQueueJob::create(['consortium_id' => $consortium->id,
                                                 'harvest_id' => $harvest->id,
                                                 'replace_data' => $replace
                                               ]);
            } catch (QueryException $e) {
                $errorCode = $e->errorInfo[1];
                if ($errorCode == '1062') {
                    $this->line('Harvest ID: ' . $harvest->id . ' for consortium ID: ' . $consortium->id .
                                ' already exists in the queue; not adding.');
                    continue;
                } else {
                    $this->line('Failed adding harvestID: ' . $harvest->id . ' to Queue! Error code:' . $errorCode);
                    continue;
                }
            }
            $harvest->status = 'Queued';
            $harvest->save();
        }
        return 1;
    }
}
