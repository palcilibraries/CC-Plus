<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Consortium;
use App\Institution;
use App\GlobalProvider;
use DB;

class RenameRawDataPaths extends Command
{
    /**
     * The name and signature for the Sushi Batch processing console command.
     * @var string
     */
    protected $signature = "ccplus:renamerawdatapaths";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update folder names for stored raw data - recursively, to use IDs instead of names.';

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
        // Get all the consortia
        $consortia = Consortium::get();

        $report_path = null;
        if (!is_null(config('ccplus.reports_path'))) {
            $report_path = config('ccplus.reports_path');
        }
        if (is_null($report_path)) {
            $this->line('Report path not defined in config/ccplus ... quitting.');
        }

        // Pull all global providers
        $providers = GlobalProvider::get(['id','name']);

        // Traverse the given (or current) path
        $fileSystemIterator = new \FilesystemIterator($report_path);
        foreach ($fileSystemIterator as $consoFile) {
            $consoName = $consoFile->getFilename();
            $conso = $consortia->where('name', 'LIKE', $consoName)->first();
            if (!$conso) {
                $this->line('Skipping Consortium Folder: ' . $consoName);
                continue;
            }

            // Found the consortium, set path for institution-level of the tree
            $path_inst = $report_path . "/" . $consoName;

            // Point the consodb connection at consortium's database
            config(['database.connections.consodb.database' => 'ccplus_' . $conso->ccp_key]);
            DB::reconnect();

            // Pull all institutions for this consortium
            $institutions = Institution::get(['id','name']);

            // Iterate through the folders in the consortium, by-institution
            $consoFolderIterator = new \FilesystemIterator($path_inst);
            foreach ($consoFolderIterator as $instFile) {
                $instName = $instFile->getFilename();
                $institution = $institutions->where('name', 'LIKE', $instName)->first();
                if (!$institution) {
                    $this->line('Skipping Institution: ' . $instName);
                    continue;
                }

                // Found the institution, process provider folders inside
                $path_prov = $path_inst . "/" . $instName;
                $instFolderIterator = new \FilesystemIterator($path_prov);
                foreach ($instFolderIterator as $provFile) {
                    $provName = $provFile->getFilename();
                    $provider = $providers->where('name', 'LIKE', $provName)->first();
                    if (!$provider) {
                        $this->line('Skipping Provider: ' . $provName . ' for ' . $instName);
                        continue;
                    }
                    $newProv = $instFile->getPathname() . '/' . $provider->id;
                    rename($provFile->getPathname(), $newProv);

                }
                $newInst = $consoFile->getPathname() . '/' . $institution->id;
                rename($instFile->getPathname(), $newInst);
            }

            // Rename the consortium folder
            $newConso = $report_path. '/' . $conso->id;
            rename($consoFile->getPathname(), $newConso);
        }
    }
}
