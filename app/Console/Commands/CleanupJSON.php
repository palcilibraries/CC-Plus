<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Consortium;
use App\HarvestLog;
use App\Report;
use DB;

class CleanupJson extends Command
{
    /**
     * The name and signature for the Sushi Batch processing console command.
     * @var string
     */
    protected $signature = "ccplus:cleanupjson
                     {consortium : The Consortium ID or key-string}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup Unprocessed JSON files for a given Consortium';

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
        $conso_db = 'ccplus_' . $consortium->ccp_key;
        config(['database.connections.consodb.database' => $conso_db]);
        DB::reconnect();

        // Set path to the unprocessed folder for this consortium
        $consortium_root = config('ccplus.reports_path') . $consortium->id . '/';
        $search_path = $consortium_root . '0_unprocessed/*.json';
        $file_glob = glob($search_path);
        if (count($file_glob) == 0) {
            $this->line('No unprocessed JSON files for consortium: ' . $consortium->name);
            return 1;
        }

        // Get the master reports
        $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Get all the harvest records that might have JSON files needing attention
        // (we're skipping 'Queued', 'ReQueued', 'Pending', 'Active', 'Harvested', and 'New')
        $check_statuses = array('Success', 'Fail', 'Stopped');
        $all_harvests = Harvestlog::with('sushiSetting','failedHarvests:id,harvest_id,error_id')
                                  ->whereIn('status',$check_statuses)->get();

        // Walk the files
        $movedCount = 0;
        $deletedCount = 0;
        foreach ($file_glob as $jsonFile) {

            $deleteFile = false;

            // Get harvest and setup output file path/name from the filename
            $parts = preg_split('/_/', substr($jsonFile, strrpos($jsonFile,'/',0)+1));
            $harvest_id = intval($parts[0]);
            $harvest = $all_harvests->where('id',$harvest_id)->first();

            // Save/use the parts
            if ($harvest) {
                $report = $master_reports->where('name', $parts[1])->first();
                if (!$report) $deleteFile = true;
                $begin = $parts[2];
                $end = substr($parts[3],0,10);
                $yearmon = substr($begin,0,7);
                if (is_null($harvest->sushiSetting)) {
                    $deleteFile = true;
                } else {
                    $prov_id = $harvest->sushiSetting->prov_id;
                    $inst_id = $harvest->sushiSetting->inst_id;
                }
            } else {
                $deleteFile = true;
            }

            // If there were missing/unusable parts, skip to the end
            if (!$deleteFile) {

                // If success with no failed records or Stopped, move it and update the harvestlog record
                if ($harvest->status == 'Stopped' ||
                    ($harvest->status == 'Success' && $harvest->failedHarvests->count() == 0)) {
                    $rawfile = $report->name . '_' . $begin . '_' . $end . ".json";
                    $harvest->rawfile = $rawfile;
                    $harvest->save();

                    // Set the new filename
                    $path = $consortium_root . '/' . $inst_id . '/' . $prov_id;
                    $newName = $path . '/' . $rawfile;

                    // if a file w/ same name already in the target folder, just delete this one
                    if (file_exists($newName)) {
                        $deleteFile = true;

                    // Otherwise move it
                    } else {
                        // Make sure the path for the output file exists
                        if (!file_exists($path) || !is_dir($path)) {
                            mkdir($path, 0755, true);
                        }
                        try {
                            rename($jsonFile, $newName);
                            $movedCount++;
                        } catch (\Exception $e) {
                            // Rename failed, clear rawfile in the harvest record and report error (don't delete it)
                            if (!is_readable($newName)) {
                                $this->line ("Rename/Move operation for JSON source file failed: " . $jsonFile);
                                $harvest->rawfile = null;
                                $harvest->save();
                            }
                        }
                    }

                // If success with existing failed() records, check the last (most recent) one for 3030
                } else if ($harvest->status == 'Success') {  // check the last (most recent) failed record(s) for 3030
                    $lastFailed = $harvest->failedHarvests->orderBy('updated_at', 'desc')->first();
                    if ($lastFailed->error_id == 3030) $deleteFile = true;

                // If status is Fail, set flag to delete
                } else if ($harvest->status == 'Fail') {
                    $deleteFile = true;
                }
            }

            // delete the file?
            if ($deleteFile) {
                unlink($jsonFile);
                $deletedCount++;
            }
        }
        $this->line('Deleted : ' . $deletedCount . ' files, and moved ' . $movedCount . ' others.');
    }
}
