<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use DB;
use App\Consortium;
use App\Report;
use App\Sushi;
use App\SushiSetting;
use App\SushiQueueJob;
use App\Counter5Processor;
use App\FailedHarvest;
use App\HarvestLog;
use App\CcplusError;
use App\Severity;
use App\Alert;
use App\GlobalProvider;
use App\ConnectionField;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

/*
 * NOTE:
 *   As of 1/10/2020, it looks like the HOST system needs to set the PHP memory_limit
 *   to AT LEAST 1024Mb. Encoding/Decoding the JSON for processing can be a real PIG,
 *   and is dependent on the size of the report being processed (60-100K characters
 *   is not uncommon for TR). This script will fail and die with a shutdown exception
 *   (that is un-catchable(?) ) if it cannot allocate enough memory.
 */
 //
 // CC PlusReport Script
 //
class ReportProcessor extends Command
{
    /**
     * The name and signature for the single-report Sushi processing console command.
     * @var string
     */
    protected $signature = 'ccplus:reportprocessor {consortium : Consortium ID or key-string}
                                             {order=t : t for date-time  or  d for dorder from global.reports [t]}
                                             {ident=null : Optional runtime name for logging output []}
                                             {startup-delay=0 : Optional delay for staggering multiple startups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process Downloaded CC-Plus JSON-format Reports';
    private $global_providers;
    private $connection_fields;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        // $this->global_providers = GlobalProvider::where('is_active', true)->get();
        // $this->connection_fields = ConnectionField::get();
        try {
          $this->global_providers = GlobalProvider::where('is_active', true)->get();
        } catch (\Exception $e) {
          $this->global_providers = array();
        }
        try {
          $this->connection_fields = ConnectionField::get();
        } catch (\Exception $e) {
          $this->connection_fields = array();
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
       // Get optional inputs
        $_ident = $this->argument('ident');
        $ident = ($_ident == "null") ? "" : $_ident . " : ";
        $order = $this->argument('order');
        $delay = $this->argument('startup-delay');
        sleep($delay);

       // Allow input consortium to be an ID or Key
        $ts = date("Y-m-d H:i:s") . " ";
        $conarg = $this->argument('consortium');
        $consortium = Consortium::find($conarg);
        if (is_null($consortium)) {
            $consortium = Consortium::where('ccp_key', '=', $conarg)->first();
        }
        if (is_null($consortium)) {
            $this->line($ts . $ident . 'Cannot locate Consortium: ' . $conarg);
            return 0;
        }
        if (!$consortium->is_active) {
            $this->line($ts . $ident . 'Consortium: ' . $conarg . " is NOT ACTIVE ... quitting.");
            return 0;
        }

       // Aim the consodb connection at specified consortium's database and initialize the
       // path for keeping raw report responses
        config(['database.connections.consodb.database' => 'ccplus_' . $consortium->ccp_key]);
        DB::reconnect();
        if (is_null(config('ccplus.reports_path'))) {
            $this->line($ts . "ReportProcessor: Global Setting for reports_path is not defined - Stopping!");
            return 0;
        }

       // Set error-severity so we only have to query for it once
        $severities_error = Severity::where('name', '=', 'Error')->value('id');

       // Get records for all "Retrieved" Harvestlogs
        $skip_statuses = array('Success', 'Fail', 'New');
        $all_harvests = HarvestLog::with('sushiSetting','sushiSetting.provider','sushiSetting.institution')
                                  ->whereNotIn('status', $skip_statuses)->get();

       // Set paths for where the files are, will be stored
        $consortium_root = config('ccplus.reports_path') . $consortium->id . '/';
        $report_path = $consortium_root . '0_unprocessed';

       // setup the file globs we need to process
        $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
        $globs = array();
        if ($order == "d") {
            $globs = [];
            foreach ($master_reports as $mr) {
                $globs[] = "*" . $mr->name . "*.json";
            }
       // default to one - all by-time
        } else {
            $globs[] = "*.json";
        }

       // Process the globs
        foreach ($globs as $glob) {

            $search_path = $report_path . "/" . $glob;
            $file_glob = glob($search_path);
            if (count($file_glob) == 0) continue;

           // sort a glob by time, oldest -> newest
            usort($file_glob, fn($a, $b) => filemtime($a) - filemtime($b));

           // Process the files
            foreach ($file_glob as $jsonFile) {

               // Get harvest and setup output file path/name from the filename
                $parts = preg_split('/_/', substr($jsonFile, strrpos($jsonFile,'/',0)+1));
                $harvest_id = intval($parts[0]);
                $harvest = $all_harvests->where('id',$harvest_id)->first();
                if (!$harvest) continue;
                $report = $master_reports->where('name', $parts[1])->first();
                if (!$report) continue;
                $begin = $parts[2];
                $end = substr($parts[3],0,10);
                $yearmon = substr($begin,0,7);
                $prov_id = $harvest->sushiSetting->prov_id;
                $inst_id = $harvest->sushiSetting->inst_id;

               // Decrypt and load the JSON into a variable
                $json = json_decode(bzdecompress(Crypt::decrypt(File::get($jsonFile), false)));

               // Create a new processor object (will replace existing data)
                $C5processor = new Counter5Processor($prov_id, $inst_id, $begin, $end, 1);

               // Run the counter processor on the JSON
                try {
                    $res = $C5processor->{$report->name}($json);
               // If processor failed, signal 9020 and delete the JSON file
                } catch (\Exception $e) {
                    FailedHarvest::insert(['harvest_id' => $harvest->id, 'process_step' => 'COUNTER',
                                           'error_id' => 9020, 'detail' => 'Processing error: ' . $e->getMessage(),
                                           'help_url' => null, 'created_at' => $ts]);
                    $this->line($ts . " " . $ident . "Error processing JSON : " . $e->getMessage());
                    $harvest->error_id = 9020;
                    unlink($jsonFile);
                    continue;
                }

              // Successfully processed the report - clear out any existing "failed" records and update the harvest
               $deleted = FailedHarvest::where('harvest_id', $harvest->id)->delete();
               $rawfile = $report->name . '_' . $begin . '_' . $end . ".json";
               $harvest->error_id = null;
               $harvest->status = 'Success';
               $harvest->rawfile = $rawfile;
               $harvest->save();

              // Move the JSON file to its new home
               $newName = $consortium_root . '/' . $inst_id . '/' . $prov_id . '/' . $rawfile;
               rename($jsonFile, $newName);
               unset($C5processor);
               // Print confirmation line
               $this->line($ts . " " . $ident . $harvest->sushiSetting->provider->name . " : " . $yearmon . " : " .
                                 $report->name . " processed for " . $harvest->sushiSetting->institution->name);
            }
        }
        return 1;
    }
}
