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
use App\IngestLog;
use App\SushiQueueJob;

class SushiQNightly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccplus:sushiqn {consortium : Consortium ID or key-string}';

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
            exit;
        }

        // Aim the consodb connection at specified consortium's database and initialize the
        // path for keeping raw report responses
         config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
         DB::reconnect();

        // Part I : Load any new ingests (based on today's date) into the IngestLog table
        // ------------------------------------------------------------------------------
        // Get and loop through all active providers
         $providers = Provider::where('is_active', '=', true)->get();

        // Timestamp is now, yearmon for report being queued is last month
         $ts = date("Y-m-d H:i:s");
         $yearmon = date("Y-m", mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
        foreach ($providers as $provider) {
            // If today is not the day, skip to next provider
            if ($provider->day_of_month != date('j')) {
                continue;
            }

            // foreach ($provider->sushiSettings() as $setting) {
            // Loop through all sushisettings for this provider
            foreach ($provider->sushiSettings as $setting) {
                // Loop through all reports defined as available for this provider
                foreach ($provider->reports as $report) {
                    // Process only master reports
                    if (
                        $report->name != "TR"
                        && $report->name != "PR"
                        && $report->name != "DR"
                         && $report->name != "IR"
                    ) {
                        continue;
                    }
                    // Insert new IngestLog record; catch and prevent duplicates
                    try {
                        IngestLog::insert(['status' => 'New', 'sushisettings_id' => $setting->id,
                                           'report_id' => $report->id, 'yearmon' => $yearmon,
                                           'attempts' => 0, 'created_at' => $ts]);
                    } catch (QueryException $e) {
                        $errorCode = $e->errorInfo[1];
                        if ($errorCode == '1062') {
                            $ingest = IngestLog::where([['sushisettings_id', '=', $setting->id],
                                                        ['report_id', '=', $report->id],
                                                        ['yearmon', '=', $yearmon]
                                                       ])->first();
                            $this->line('Ingest ' . '(ID:' . $ingest->id . ') already defined. Updating to retry (' .
                                        'setting: ' . $setting->id . ', ' . $report->name . ':' . $yearmon . ').');
                            $ingest->status = 'Retrying';
                            $ingest->save();
                        } else {
                            $this->line('Failed adding to IngestLog! Error code:' . $errorCode);
                            exit;
                        }
                    }
                } // for each report
            } // for each sushisetting
        } // for each provider

        // Part II : Create queue jobs based on IngestLogs
        // -----------------------------------------------
         $ingests = IngestLog::where('status', '=', 'New')->orWhere('status', '=', 'Retrying')->get();
        foreach ($ingests as $ingest) {
            try {
                $newjob = SushiQueueJob::create(['consortium_id' => $consortium->id, 'ingest_id' => $ingest->id]);
            } catch (QueryException $e) {
                $errorCode = $e->errorInfo[1];
                if ($errorCode == '1062') {
                    $this->line('Ingest ID: ' . $ingest->id . ' for consortium ID: ' . $consortium->id .
                                ' already exists in the queue; not adding.');
                    continue;
                } else {
                    $this->line('Failed adding ingestID: ' . $ingest->id . ' to Queue! Error code:' . $errorCode);
                    continue;
                }
            }
            $ingest->status = 'Queued';
            $ingest->save();
        }
    }
}
