<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Consortium;
use App\Report;
use App\Provider;
use App\Institution;
use App\HarvestLog;
use App\FailedHarvest;

class DataPurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccplus:data-purge
                             {consortium : The Consortium ID or key-string}
                             {--Y|year= : YYYY to process; required if From/To not specified}
                             {--F|from= : Beginning month (YYYY-MM) of date-range to purge; ignored if Year specified}
                             {--T|to= : End month (YYYY-MM) of date-range to purge; ignored if Year specified}
                             {--P|provider= : Provider ID# to purge [ALL]}
                             {--I|institution= : Institution ID# to purge [ALL]}
                             {--R|report= : Master report NAME data records to purge [ALL]}
                             {--A|all : Purge related provider, inst, settings and log records (if possible) [FALSE]}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge CC+ usage data from database tables';

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
        $purgeRelated = ($this->option('all')) ? true : false;

       // Provider, Inst and Report options
        $prov_ids = array();
        $prov_id = $this->option('provider');
        if ($prov_id) {
            $provider = Provider::where('id',$prov_id)->first();
            if ($provider) {
                $providerName = $provider->name;
            } else {
                $this->error("Error: Provider with ID: " . $prov_id . "not found.");
                return 0;
            }
            $prov_ids[] = $provider->id;
        } else {
            $providerName = "ALL";
            $prov_ids = Provider::pluck('id')->toArray();
        }
        $inst_ids = array();
        $inst_id = $this->option('institution');
        if ($inst_id) {
            $institution = Institution::where('id',$inst_id)->first();
            if ($institution) {
                $institutionName = $institution->name;
            } else {
                $this->error("Error: Institution with ID: " . $inst_id . "not found.");
                return 0;
            }
            $inst_ids[] = $institution->id;
        } else {
            $institutionName = "ALL";
            $inst_ids = Institution::where('id','<>',1)->pluck('id')->toArray(); // Skip 'consortium' inst
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

        // Summarize what's about to happen and prompt for confirmation
        $this->line('');
        $this->info('There is no "UNDO" command to reverse what is about to happen. Backing up your data');
        $this->info("before proceeding (using ccplus:data-archive or another utility) is highly recommended.");
        $this->line('');
        $_msg  = "About to permanently delete usage data from ";
        $_msg .= ($rept == "ALL") ? "ALL master tables for " : $data_tables[0] . " for ";
        $_msg .= ($providerName == "ALL") ? "ALL providers, and for " : $providerName . ", and for ";
        $this->comment($_msg);
        $_msg  = ($institutionName == "ALL") ? "ALL Institutions " :  $institutionName . " ";
        $_msg .= "where Year_Month is between " . $range[0] . " and " . $range[1] . " (inclusive).";
        $this->comment($_msg);
        if (!$this->confirm('Proceed with this operation?')) {
            return 0;
        }

       // Loop through data tables; delete matching rows from each master report table.
        foreach ($data_tables as $table) {
            DB::table($conso_db . "." . $table)->whereBetween('yearmon', $range)
              ->whereIn('prov_id', $prov_ids)->whereIn('inst_id', $inst_ids)
              ->delete();
        }
        $this->line('Usage data successfully purged.');

       // Delete rows (only if no dependencies) from related tables
        if ($purgeRelated) {
            $this->line('Clearing associated harvest log and failed harvest records.');

           // Get the harvestlog IDs for the data just deleted
            $_ids = HarvestLog::join($conso_db . ".sushisettings", 'harvestlogs.sushisettings_id', 'sushisettings.id')
                        ->whereIn('report_id',$report_ids)->whereBetween('yearmon', $range)
                        ->whereIn('sushisettings.prov_id',$prov_ids)->whereIn('sushisettings.inst_id',$inst_ids)
                        ->pluck('harvestlogs.id')->toArray();

           // Delete the records (failedharvests should cascade)
            DB::table($conso_db . ".harvestlogs")->whereIn('id', $_ids)->delete();

           // Loop through institution ids to find any insts with NO data records in any master data table
            $raw_query = "Count(*) as  count";
            $zero_record_insts = array();
            foreach ($inst_ids as $instID) {
                $total = 0;
                foreach ($data_tables as $table) {
                    $num = DB::table($conso_db . "." . $table)->where('inst_id',$instID)
                             ->selectRaw($raw_query)->value('count');
                    if ($num >0) {
                        $total += $num;
                        break;
                    }
                }
                if ($total == 0) {
                    $zero_record_insts[] = $instID;
                }
            }

           // Loop through provider ids to find any providers with NO data records in any master data table
            $zero_record_provs = array();
            foreach ($prov_ids as $provID) {
                $total = 0;
                foreach ($data_tables as $table) {
                    $num = DB::table($conso_db . "." . $table)->where('prov_id',$provID)
                             ->selectRaw($raw_query)->value('count');
                    if ($num >0) {
                        $total += $num;
                        break;
                    }
                }
                if ($total == 0) {
                    $zero_record_provs[] = $provID;
                }
            }

            // Prompt for confirmastion before removing providers and/or institutions.
            $_msg  = 'There are currently ';
            $_msg .= (sizeof($zero_record_provs)>0) ? sizeof($zero_record_provs) . " Providers " : "";
            $_msg .= (sizeof($zero_record_provs)>0 && sizeof($zero_record_insts)>0) ? "and " : "";
            $_msg .= (sizeof($zero_record_insts)>0) ? sizeof($zero_record_insts) . " Institutions " : "";
            $_msg .= "with NO stored usage data.";
            $this->comment($_msg);
            $this->comment("Purging these will also remove the associated SUSHI settings.");
            if (!$this->confirm('ARE YOU SURE you want to proceed with this operation?')) {
                $this->info('Providers and Institutions not changed.');
                return 0;
            }

            // Toss all the providers and insts with no data (sushisettings should cascasde)
            if (sizeof($zero_record_provs) > 0) {
                DB::table($conso_db . ".providers")->whereIn('id', $zero_record_provs)->delete();
                $this->info(sizeof($zero_record_provs) . ' Providers removed');
            }
            if (sizeof($zero_record_insts) > 0) {
                DB::table($conso_db . ".institutions")->whereIn('id', $zero_record_insts)->delete();
                $this->info(sizeof($zero_record_insts) . ' Institutions removed');
            }
        }   // end-if  $purgeRelated

       // All done...
        $this->line('Data Purge successfully completed');
        return 1;
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
