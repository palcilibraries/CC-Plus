<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use DB;
use Hash;
use App\Consortium;
use App\Provider;
use App\Institution;
use App\SushiSetting;
use App\Report;
use App\HarvestLog;

class DataArchiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccplus:data-archive
                     {consortium : The Consortium ID or key-string}
                     {filename : Output filename for SQL-formatted data records}
                     {--Y|year= : YYYY to process; required if From/To not specified}
                     {--F|from= : Beginning month (YYYY-MM) of date-range to process; ignored if Year specified}
                     {--T|to= : End month (YYYY-MM) of date-range to process; ignored if Year specified}
                     {--P|provider= : Provider ID# to process [ALL]}
                     {--I|institution= : Institution ID# to process [ALL]}
                     {--R|report= : Master report NAME to archive [ALL]}
                     {--X|exclude : Exclude title, provider, inst, settings and associated log data [FALSE]}
                     {--G|global : Include global Titles, Items, Databases, Platforms, and Publishers [FALSE]}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive CC+ usage data to a file as SQL';
    private $range;
    private $report_ids;
    private $conso_db;
    private $outputFile;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        global $range, $report_ids, $conso_db, $outputFile;
        parent::__construct();
        $range = [];
        $report_ids = [];
        $conso_db = '';
        $outputFile = '';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        global $range, $report_ids, $conso_db, $outputFile;

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

       // Handle input options, beginning w/ From-To and Year options
        $range = array();
        if (is_null($this->option('year'))) {
            $range[0] = $this->option('from');
            $range[1] = $this->option('to');
            if (is_null($range[0]) || is_null($range[1])) {
                $this->line('Error: One of the Year or From/To date range options is required');
                return 0;
            }
        } else {
            $range[0] = $this->option('year') . '-01';
            $range[1] = $this->option('year') . '-12';
        }
        $year_mons = self::createYMarray($range);
        $saveRelated = ($this->option('exclude')) ? false : true;
        $saveGlobal = ($this->option('global')) ? true : false;

       // Provider, Inst and Report options
        $prov_id = $this->option('provider');
        if ($prov_id) {
            $providers = Provider::where('id',$prov_id)->get();
            if ($providers) {
                $providerName = $providers[0]->name;
            } else {
                $this->error("Error: Provider with ID: " . $prov_id . "not found.");
                return 0;
            }
        } else {
            $providerName = "ALL";
            $providers = Provider::get();
        }
        $inst_id = $this->option('institution');
        if ($inst_id) {
            $institutions = Institution::where('id',$inst_id)->get();
            if ($institutions) {
                $institutionName = $institutions[0]->name;
            } else {
                $this->error("Error: Institution with ID: " . $inst_id . "not found.");
                return 0;
            }
        } else {
            $institutionName = "ALL";
            $institutions = Institution::where('id','<>',1)->get(); // Skip 'consortium' inst
        }

       // Get master-report-names and setup array of table names to be queried
        $report_ids = array();
        $data_tables = array();
        $reports = Report::where('parent_id',0)->get();
        $rept = is_null($this->option('report')) ? 'ALL' : strtoupper($this->option('report'));
        if ($rept == 'ALL') {
            foreach($reports as $report) {
                $data_tables[] = strtolower($report->name) . '_report_data';
                $report_ids[] = $report->id;
            }
        } else {
            $report = $reports->where('name',$rept)->first();
            if (!$report) {
                $this->error("Error: cannot export " . $rept . " data. Only master report names supported.");
                return 0;
            }
            $report_ids[] = $report->id;
            $data_tables = array(strtolower($rept) . '_report_data');
        }

       // Get the filename and initialize it
        $outputFile = $this->argument('filename');
        $headerRecs  = "-- CC-Plus Data Archive for Consortium: " . $consortium->name;
        $headerRecs .= ", created: " . date("Y-m-d H:i:s") . "\n";
        $headerRecs .= "-- Usage Data From: " . $range[0] . " To: " . $range[1] . "\n";
        $headerRecs .= "-- Provider: " . $providerName . "\n";
        $headerRecs .= "-- Institution: " . $institutionName . "\n";
        $headerRecs .= "-- Master Report(s): " . $rept . "\n";
        $headerRecs .= "--\n";
        $headerRecs .= "SET FOREIGN_KEY_CHECKS=0;";
        $headerRecs .= "use " . $conso_db . ";";
        $is_created = Storage::put($outputFile,$headerRecs);
        if (!$is_created) {
            $this->error("Error - unable to create file : " . $outputFile);
            return 0;
        }

       // Loop through data tables; generate SQL Insert commands from each master report table
       // and save in $outputFile. The insert statments are "broken up" by provider->inst->year_month
       // to try to avoid running out of memory, inserts that are too long, and make it possible
       // to split/edit the resulting achive, if necessary.
        foreach ($data_tables as $table) {

           // Get fields for this table
            $fields = DB::select("show columns from " . $conso_db . "." . $table);
            $table_columns = array_column($fields,'Field');

           // Process all requested providers
            $this->line('Processing data in ' . $table);
            foreach ($providers as $prov) {
                if ($institutionName == "ALL") {
                    $this->line('Data Provider : ' . $prov->name);
                    $status_bar = $this->output->createProgressBar(count($institutions));
                }

               // Loop for all requested institutions
                foreach ($institutions as $inst) {

                    // Check record count when not doing ALL Insts; if zero - skip it silently
                    if ($institutionName != "ALL") {
                        $num = DB::table($conso_db . "." . $table)
                                 ->where('prov_id',$prov->id)->where('inst_id',$inst->id)
                                 ->whereBetween('yearmon',$range)
                                 ->selectRaw("Count(*) as  count")->value('count');
                        if ($num < 1) {
                            continue;
                        }
                    }

                    $desc = "--\n-- Provider: " . $prov->name . " , Institution: " . $inst->name;
                    $_res = Storage::append($outputFile,$desc);
                    // Build and append the insert statement for each yearmon for this prov->inst
                    foreach ($year_mons as $yearmon) {
                        $records = DB::table($conso_db . "." . $table)->where('yearmon', '=', $yearmon)
                                     ->where('prov_id', $prov->id)->where('inst_id', $inst->id)->get();
                        if ($records->count() == 0) {
                            continue;
                        }
                        $data = "--\nINSERT INTO " . $table . " (" . implode(",", $table_columns) . ") VALUES ";
                        foreach ($records as $record) {
                            $record = (array)$record;
                            $table_values = array_values($record);
                            $data .= "('" . implode("','", $table_values) . "'),";
                        }
                       // Write the statement to the file
                        $data = preg_replace('/,$/',';',$data);
                        $_res = Storage::append($outputFile,$data);
                    }
                    if ($institutionName == "ALL") {
                        $status_bar->advance(); // Advance bar by-institution
                    }
                }
                if ($institutionName == "ALL") {
                    $status_bar->finish();
                    $this->line(' ');
                }
            }
        }

       // Save related data for providers, institutions, sushisettings, failedharvests and harvestlogs
       // Loop across the records for each table. Save using "INSERT IGNORE" so they won't throw errors on
       // import if the records still exist.
        if ($saveRelated) {
           // Set arrays with the provider an institution ids just archived
            $provider_ids = $providers->pluck('id')->toArray();
            $institution_ids = $institutions->pluck('id')->toArray();

           // Save providers to the output file
            $_res = self::relatedData('providers', 'Providers', 'id', $provider_ids);

           // Save institutions to the output file
            $_res = self::relatedData('institutions', 'Institutions', 'id', $institution_ids);

           // Get sushi settings IDs
            $settingsIDs = SushiSetting::whereIn('prov_id',$provider_ids)->whereIn('inst_id',$institution_ids)
                                       ->pluck('id')->toArray();

           // Save sushi_settings to the output file
            $_res = self::relatedData('sushisettings', 'Sushi Settings', 'id', $settingsIDs);

           // Save harvestlogs to the output file
            $harvestIDs = self::relatedData('harvestlogs', 'Harvest Logs', 'sushisettings_id', $settingsIDs);

           // Save failed harvests to the output file
            $_res = self::relatedData('failedharvests', 'Failed Harvests', 'harvest_id', $harvestIDs);
        }

        // Save global database records for Titles, Items, Databases, Platforms, and Publishers.
        // Save using "INSERT IGNORE" so they won't throw errors on import if the records still exist.
         if ($saveGlobal) {
             $globalHeader  = "--\n-- Global Database Records";
             $globalHeader .= "--\nuse ccplus_global;";
             $_res = Storage::append($outputFile,$globalHeader);
             self::globalData('titles', 'Titles');
             self::globalData('items', 'Items');
             self::globalData('databases', 'Databases');
             self::globalData('platforms', 'Platforms');
             self::globalData('publishers', 'Publishers');
        }

       // All done...
        $_res = Storage::append($outputFile,"--\nSET FOREIGN_KEY_CHECKS=1;\n");
        $this->line('Archive successfully created');
        $this->line('Output saved in: ' . Storage::disk('local')->path($outputFile));
        return 0;
    }

    // Save related table records in $outputFile as an insert statement
    private function relatedData($table,$name,$keyColumn,$whereIn)
    {
        global $range, $report_ids, $conso_db, $outputFile;
        $search = array("'", PHP_EOL);
        $replace = array("\'", "");

        $table_name = $conso_db . "." . $table;
        $_res = Storage::append($outputFile,"--\n-- " . $name . "\n--");

       // Get field names for the table
        $fields = DB::select("show columns from " . $table_name);
        $table_columns = array_column($fields,'Field');

       // Get all implicated records
        if ($table == 'harvestlogs') {
            $records = DB::table($table_name)->whereIn($keyColumn,$whereIn)
                         ->whereIn('report_id',$report_ids)->whereBetween('yearmon',$range)->get();
        } else {
            $records = DB::table($table_name)->whereIn($keyColumn,$whereIn)->get();
        }

       // Loop across the returned records. Save with "INSERT IGNORE" so MySQL won't throw errors on
       // import if the row(s) still exist.
        $this->line('Saving ' . $name);
        if ($records->count() > 0) {
            $data = "INSERT IGNORE INTO " . $table;
            $data .= " (" . implode(",", $table_columns) . ") VALUES ";
            foreach ($records as $record) {
                $record = (array)$record;
                $table_values = array_map('trim', array_values($record));
                $table_values = str_replace($search, $replace, $table_values);
                $data .= "('" . implode("','", $table_values) . "'),";
            }

           // Write the statement to the file
            $data = preg_replace('/,$/',';',$data);
            $_res = Storage::append($outputFile,$data);
        } else {
            $_res = Storage::append($outputFile,"-- No matching " . $name . "\n--");
            $this->info("No matching records for " . $name . " were found or included");
        }

        if (in_array('id',$table_columns)) {
            return $records->pluck('id')->toArray();
        } else {
            return 0;
        }
    }

    // Save related table records in $outputFile as an insert statement
    private function globalData($table,$name)
    {
        global $outputFile;
        $search = array("'", PHP_EOL);
        $replace = array("\'", "");

        $table_name = "ccplus_global." . $table;
        $_res = Storage::append($outputFile,"--\n-- " . $name . "\n--");

       // Get field names for the table
        $fields = DB::select("show columns from " . $table_name);
        $table_columns = array_column($fields,'Field');

       // Get all records
        $allRecords = DB::table($table_name)->get();

        // Loop across the returned records. Save with "INSERT IGNORE" so MySQL won't throw errors on
        // import if the row(s) still exist. Build one insert statement every 5K recods
        if ($allRecords->count() > 0) {
            $this->line('Saving ' . $name);
            $chunks = $allRecords->chunk(5000);
            foreach ($chunks as $records) {
                $data = "INSERT IGNORE INTO `" . $table . "`";
                $data .= " (" . implode(",", $table_columns) . ") VALUES ";
                foreach ($records as $record) {
                    $record = (array)$record;
                    $table_values = array_map('trim', array_values($record));
                    $table_values = str_replace($search, $replace, $table_values);
                    $data .= "('" . implode("','", $table_values) . "'),";
                }

               // Write the statement to the file
                $data = preg_replace('/,$/',';',$data);
                $_res = Storage::append($outputFile,$data);
            }
        }
    }

    // Turn a fromYM/toYM range into an array of yearmon strings
    private function createYMarray($range)
    {
        $yearmons = array();
        $start = strtotime($range[0]);
        $end = strtotime($range[1]);
        while ($start <= $end) {
            $yearmons[] = date('Y-m', $start);
            $start = strtotime("+1 month", $start);
        }
        return $yearmons;
    }
}
