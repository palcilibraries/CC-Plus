<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use App\HarvestLog;
use App\Consortium;
use App\FailedHarvest;
use App\Report;
use App\Provider;
use App\GlobalProvider;
use App\Institution;
use App\InstitutionGroup;
use App\Sushi;
use App\SushiSetting;
use App\SushiQueueJob;
use App\ConnectionField;
use Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class HarvestLogController extends Controller
{
   private $connection_fields;

   public function __construct()
   {
       $this->middleware('auth');
       // Load all connection fields
       try {
         $this->connection_fields = ConnectionField::get();
       } catch (\Exception $e) {
         $this->connection_fields = array();
       }
   }

   /**
    * Display a listing of the resource.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response or JSON
    */
   public function index(Request $request)
   {
       $thisUser = auth()->user();
       $json = ($request->input('json')) ? true : false;

       // Assign optional inputs to $filters array
       $filters = array('institutions' => [], 'providers' => [], 'reports' => [], 'harv_stat' => [], 'updated' => null,
                        'groups' => [], 'fromYM' => null, 'toYM' => null, 'source' => null, 'codes' => []);
       if ($request->input('filters')) {
           $filter_data = json_decode($request->input('filters'));
           foreach ($filter_data as $key => $val) {
               if (($key == 'updated' && $val != '') || $val != 0) {
                   $filters[$key] = $val;
               }
           }
       } else {
           $keys = array_keys($filters);
           foreach ($keys as $key) {
               if ($request->input($key)) {
                   if ($key=='fromYM' || $key=='toYM' || $key=='updated' || $key=='source') {
                       $filters[$key] = $request->input($key);
                   } elseif (is_numeric($request->input($key))) {
                       $filters[$key] = array(intval($request->input($key)));
                   }
               }
           }
       }

       // Allow for inbound provider and institution arguments
       $presets = array();
       $presets['prov_id'] = ($request->input('prov_ps')) ? $request->input('prov_ps') : null;
       $presets['inst_id'] = ($request->input('inst_ps')) ? $request->input('inst_ps') : null;

       // Managers and users only see their own insts
       $show_all = $thisUser->hasAnyRole(["Admin","Viewer"]);
       if (!$show_all) {
           $user_inst = $thisUser->inst_id;
           $filters['institutions'] = array($user_inst);
       }

       // Make sure dates are sensible
       if (!is_null($filters['fromYM']) || !is_null($filters['toYM'])) {
           if (is_null($filters['fromYM'])) {
               $filters['fromYM'] = $filters['toYM'];
           }
           if (is_null($filters['toYM'])) {
               $filters['toYM'] = $filters['fromYM'];
           }
       }

       // Get all groups regardless of JSON or not
       $groups = array();
       if ($show_all) {
           $all_groups = InstitutionGroup::with('institutions:id,name')->orderBy('name', 'ASC')->get();
           // Keep only groups that have members
           foreach ($all_groups as $group) {
               if ( $group->institutions->count() > 0 ) {
                   $groups[] = array('id' => $group->id, 'name' => $group->name, 'institutions' => $group->institutions);
               }
           }
       }
       $harvests = array();

       // Setup for initial view - get queue count and build arrays for the filter-options.
       if (!$json) {

           // Get the job-count for the current consortium
           $conso = null;
           $job_count = 0;
           $con = Consortium::where("ccp_key", session("ccp_con_key"))->first();
           if ($con) {
               $job_count = SushiQueueJob::where('consortium_id', $con->id)->count();
               $conso = $con->name;
           }

           // Get IDs of all possible providers from the sushisettings table
           if ($show_all) {
               $possible_providers = SushiSetting::distinct('prov_id')->pluck('prov_id')->toArray();
           } else {
               $possible_providers = SushiSetting::where('inst_id',$user_inst)->distinct('prov_id')->pluck('prov_id')->toArray();
           }

           // Setup arrays for institutions and providers
           if ($show_all) {
               $institutions = Institution::with('sushiSettings:id,inst_id,prov_id')->where('is_active', true)
                                          ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
           } else {
               $institutions = Institution::with('sushiSettings:id,inst_id,prov_id')
                                          ->where('id',$user_inst)->get(['id','name'])->toArray();
           }
           $provider_data = GlobalProvider::with('sushiSettings','consoProviders','consoProviders.reports')
                                          ->whereIn('id', $possible_providers)->where('is_active', true)
                                          ->orderBy('name', 'ASC')->get(['id','name']);

           // Add in a flag for whether or not the provider has enabled sushi settings
           $providers = array();
           foreach ($provider_data as $gp) {
              $rec = array('id' => $gp->id, 'name' => $gp->name);
              $consoCnx = $gp->consoProviders->where('inst_id',1)->first();
              $rec['inst_id'] = ($consoCnx) ? 1 : null;
              $enabled_setting = $gp->sushiSettings->where('status','Enabled')->first();
              $rec['sushi_enabled'] = ($enabled_setting) ? true : false;
              $_reports = $gp->enabledReports();
              $rec['reports'] = $_reports;
              $providers[] = $rec;
           }

           // Get reports for all that exist in the relationship table
           $table = config('database.connections.consodb.database') . '.' . 'provider_report';
           $report_ids = DB::table($table)->distinct('report_id')->pluck('report_id')->toArray();
           $reports = Report::whereIn('id', $report_ids)->orderBy('dorder', 'ASC')->get()->toArray();

           // Query for min and max yearmon values
           $bounds = $this->harvestBounds();

           // make a list of error codes - for now just return everthing that exists in failedHarvests
           $codes = HarvestLog::whereNotNull('error_id')->distinct('error_id')->orderBy('error_id')->pluck('error_id')->toArray();

           // Setup the initial page view
           return view('harvests.index', compact('harvests','institutions','groups','providers','reports','bounds','filters',
                                                 'codes','job_count','presets','conso'));
       }

       // Skip querying for records unless we're returning json
       // The vue-component will run a request for JSON data once it is mounted
       if ($json) {
           // Setup limit_to_insts with the instID's we'll pull settings for
           $limit_to_insts = array();
           if ($show_all) {
               // if checkbox for all-consortium is on, clear inst and group Filters
               if (in_array(0,$filters["institutions"])) {
                   $filters['institutions'] = array();
                   $filters['groups'] = array();
               }
               if (sizeof($filters['groups']) > 0) {
                   foreach ($filters['groups'] as $group_id) {
                       $group = $all_groups->where('id',$group_id)->first();
                       if ($group) {
                           $_insts = $group->institutions->pluck('id')->toArray();
                           $limit_to_insts =  array_merge(
                               array_intersect($limit_to_insts, $_insts),
                               array_diff($limit_to_insts, $_insts),
                               array_diff($_insts, $limit_to_insts)
                           );
                       }
                   }
               } else if (sizeof($filters['institutions']) > 0) {
                   $limit_to_insts = $filters['institutions'];
               }
           } else {
               $limit_to_insts[] = $thisUser->inst_id;
           }

           // Setup limit_to_provs with the provID's we'll pull settings for
           $limit_to_provs = array();   // default to no limit
           if ($show_all && in_array(0,$filters["providers"])) {   //  Get all consortium providers?
               $limit_to_provs = GlobalProvider::with('sushiSettings', 'sushiSettings.institution:id,is_active')
                                               ->pluck('id')->toArray();
           } else if (!in_array(-1,$filters["providers"]) && count($filters['providers']) > 0) {
               $limit_to_provs = $filters['providers'];
           }

           // Set array of statuses to pull (if filter is set)
           $statuses = array();
           if (sizeof($filters['harv_stat']) > 0) {
               if ( in_array("Queued", $filters['harv_stat']) ) {
                   $statuses = array('Queued', 'New', 'Pending', 'Requeued');
               }
               foreach ($filters['harv_stat'] as $stat) {
                   if ($stat != "Queued") $statuses[] = $stat;
               }
           }

           // Get the harvest rows based on sushisettings
           $settings = SushiSetting::when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                       return $qry->whereIn('inst_id', $limit_to_insts);
                                   })
                                   ->when(count($limit_to_provs) > 0, function ($qry) use ($limit_to_provs) {
                                       return $qry->whereIn('prov_id', $limit_to_provs);
                                   })->get(['id','inst_id','prov_id']);
           $settings_ids = $settings->pluck('id')->toArray();
           $harvest_data = HarvestLog::
               with('report:id,name','sushiSetting','sushiSetting.institution:id,name','sushiSetting.provider',
                    'lastError','failedHarvests','failedHarvests.ccplusError')
               ->whereIn('sushisettings_id', $settings_ids)
               ->orderBy('updated_at', 'DESC')
               ->when(sizeof($filters['reports']) > 0, function ($qry) use ($filters) {
                   return $qry->whereIn('report_id', $filters['reports']);
               })
               ->when(sizeof($filters['codes']) > 0, function ($qry) use ($filters) {
                   return $qry->whereIn('error_id', $filters['codes']);
               })
               ->when(sizeof($statuses) > 0, function ($qry) use ($statuses) {
                   return $qry->whereIn('status', $statuses);
               })
               ->when($filters['fromYM'], function ($qry) use ($filters) {
                   return $qry->where('yearmon', '>=', $filters['fromYM']);
               })
               ->when($filters['source'] , function ($qry) use ($filters) {
                  return $qry->where('source',substr($filters['source'],0,1));
               })
               ->when($filters['toYM'], function ($qry) use ($filters) {
                   return $qry->where('yearmon', '<=', $filters['toYM']);
               })
               ->when($filters['updated'], function ($qry) use ($filters) {
                   if ($filters['updated'] == "Last 24 hours") {
                       return $qry->whereRaw("updated_at >= (now() - INTERVAL 24 HOUR)");
                   } else {
                       return $qry->where('updated_at', 'like', '%' . $filters['updated'] . '%');
                   }
               })
               ->get();

           // Make arrays for updating the filter options in the U/I
           $codes = $harvest_data->whereNotNull('error_id')->unique('error_id')->sortBy('error_id')->pluck('error_id')->toArray();
           $statuses = $harvest_data->unique('status')->sortBy('status')->pluck('status')->toArray();
           $rept_ids = $harvest_data->unique('report_id')->sortBy('report_id')->pluck('report_id')->toArray();
           $prov_ids = $harvest_data->unique('sushiSetting.provider')->sortBy('sushiSetting.provider.name')
                                    ->pluck('sushiSetting.prov_id')->toArray();
           $inst_ids = $harvest_data->unique('sushiSetting.institution')->sortBy('sushiSetting.institution.name')
                                    ->pluck('sushiSetting.inst_id')->toArray();

           // Format records for display , limit to 500 output records
           $updated_ym = array();
           $count = 0;
           $truncated = false;
           $max_records = 500;
           foreach ($harvest_data as $key => $harvest) {
               $formatted_harvest = $this->formatRecord($harvest);
               // bump counter and add the record to the output array
               $count += 1;
               if ($count > $max_records) {
                   $truncated = true;
                   break;
               }
               $harvests[] = $formatted_harvest;
               if (!in_array(substr($harvest->updated_at,0,7), $updated_ym)) {
                   $updated_ym[] = substr($harvest->updated_at,0,7);
               }
           }

           // sort updated_ym options descending
           usort($updated_ym, function ($time1, $time2) {
               if (strtotime($time1) < strtotime($time2)) {
                   return 1;
               } else if (strtotime($time1) > strtotime($time2)) {
                   return -1;
               } else {
                   return 0;
               }
           });
           array_unshift($updated_ym , 'Last 24 hours');
           return response()->json(['harvests' => $harvests, 'updated' => $updated_ym, 'truncated' => $truncated, 200,
                                    'code_opts' => $codes, 'stat_opts' => $statuses, 'rept_opts' => $rept_ids,
                                    'prov_opts' => $prov_ids, 'inst_opts' => $inst_ids]);
       }
   }

   /**
    * Setup wizard for manual harvesting
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
   public function create(Request $request)
   {
       $thisUser = auth()->user();
       abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
       if ($thisUser->hasRole('Admin')) {
           $is_admin = true;
       } else {
           $user_inst =$thisUser->inst_id;
           $is_admin = false;
       }

       // Allow for inbound provider and institution arguments
       $input = $request->all();
       $presets = array('inst_id' => null);
       $presets['prov_id'] = (isset($input['prov'])) ? $input['prov'] : null;
       if (isset($input['inst'])) {
           $presets['inst_id'] = ($is_admin) ? $input['inst'] : $user_inst;
       }

       // Get IDs of all possible prov_ids from the sushisettings table
       $inst_groups = array();
       if ($is_admin) {     // Admin view
           $possible_providers = SushiSetting::distinct('prov_id')->pluck('prov_id')->toArray();
           $institutions = Institution::with('sushiSettings:id,inst_id,prov_id')->where('is_active', true)
                                      ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
           $group_data = InstitutionGroup::with('institutions')->orderBy('name', 'ASC')->get(['id','name']);

           // Copy available_providers into the groups (to simplify the vue component)
           foreach ($group_data as $key => $group) {
               // Remove groups with no members
               if ( $group->institutions->count() == 0 ) continue;
               $insts = $group->institutions->pluck('id')->toArray();
               $available_providers = SushiSetting::whereIn('inst_id',$insts)->pluck('prov_id')->toArray();
               $group->providers = $provider_data->whereIn('id',$available_providers)->toArray();
               $inst_groups[] = $group;
           }

       } else {    // manager view
           $possible_providers = SushiSetting::where('inst_id',$user_inst)->distinct('prov_id')->pluck('prov_id')->toArray();
           $institutions = Institution::with('sushiSettings:id,inst_id,prov_id')->where('id', $user_inst)
                                      ->get(['id','name'])->toArray();
       }

       // Setup providers aray for U/I
       $providers = GlobalProvider::whereIn('id', $possible_providers)->where('is_active', true)
                                  ->orderBy('name', 'ASC')->get(['id','name'])->toArray();

       // // Get reports for all that exist in the relationship table
       // $table = config('database.connections.consodb.database') . '.' . 'provider_report';
       // $report_ids = DB::table($table)->distinct('report_id')->pluck('report_id')->toArray();
       // $all_reports = Report::whereIn('id', $report_ids)->orderBy('dorder', 'ASC')->get()->toArray();
       //
       // Get all the master reports
       $all_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name'])->toArray();
       return view('harvests.create', compact('institutions','inst_groups','providers','all_reports','presets'));
   }

   /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
   public function store(Request $request)
   {
       $thisUser = auth()->user();
       abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
       $this->validate($request,
           ['prov' => 'required', 'reports' => 'required', 'fromYM' => 'required', 'toYM' => 'required', 'when' => 'required']
       );
       $input = $request->all();
       if (!isset($input["inst"]) || !isset($input["inst_group_id"])) {
           return response()->json(['result' => false, 'msg' => 'Error: Missing input arguments!']);
       }
       if (sizeof($input["inst"]) == 0 && $input["inst_group_id"] <= 0) {
           return response()->json(['result' => false, 'msg' => 'Error: Institution/Group invalid in request']);
       }
       $user_inst =$thisUser->inst_id;
       $is_admin =$thisUser->hasRole('Admin');

       // Set flag for "skip previously harvested data"
       $skip_harvested = false;
       if (isset($input["skip_harvested"])) {
           $skip_harvested = $input["skip_harvested"];
       }

       // Admins can harvest multiple insts or a group
       $inst_ids = array();
       if ($is_admin) {
           // Set inst_ids (force to user's inst if not an admin)
           if ($input["inst_group_id"] > 0) {
               $group = InstitutionGroup::with('institutions')->findOrFail($input["inst_group_id"]);
               $inst_ids = $group->institutions->pluck('id')->toArray();
           } else {
               // A value of 0 in inst_ids means we're doing consortium
               if (in_array(0,$input["inst"])) {
                   $inst_ids = Institution::where('is_active',true)->pluck('id')->toArray();
               } else {
                   $inst_ids = $input["inst"];
               }
           }
       // Managers are confined to only their inst
       } else {
           $inst_ids = array($user_inst);
       }
       if (sizeof($inst_ids) == 0) {
           return response()->json(['result' => false, 'msg' => 'Error: no matching institutions to harvest']);
       }

       // Get detail on (master) reports requested
       $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

       // Get provider info
       if (in_array(0,$input["prov"])) {    //  Get all consortium-enabled global providers?
           $global_ids = Provider::where('is_active',true)->where('inst_id',1)->pluck('global_id')->toArray();
       } else {
           $prov_ids = $input["prov"];
           $global_ids = array();
           // prov_ids with  -1  means ALL, set global_ids based whose asking (admin or not)
           if ($is_admin) {
               if (in_array(-1, $prov_ids) || count($prov_ids) == 0) {
                   $global_ids = Provider::where('is_active',true)->pluck('global_id')->toArray();
               } else {
                   $global_ids = Provider::where('is_active',true)->whereIn('global_id',$prov_ids)->pluck('global_id')->toArray();
               }
           } else {
               if (in_array(-1, $prov_ids) || count($prov_ids) == 0) {
                   $global_ids = Provider::where('is_active',true)->whereIn('inst_id',[1,$user_inst])
                                         ->pluck('global_id')->toArray();
               } else {
                   $global_ids = Provider::where('is_active',true)->whereIn('inst_id',[1,$user_inst])
                                         ->whereIn('global_id',$prov_ids)->pluck('global_id')->toArray();
               }
           }
       }
       $global_providers = GlobalProvider::with('sushiSettings','sushiSettings.institution:id,is_active','consoProviders',
                                                'consoProviders.reports')
                                         ->where('is_active',true)->whereIn('id',$global_ids)->get();

       // Set the status for the harvests we're creating based on "when"
       $state = "New";
       if ($input["when"] == 'now') {
           $state = "Queued";
           $con = Consortium::where('ccp_key', '=', session('ccp_con_key'))->first();
           if (!$con) {
               return response()->json(['result' => false, 'msg' => 'Cannot create jobs with current consortium setting.']);
           }
       }

       // Check From/To - truncate to current month if in future and ensure from <= to ,
       // then turn them into an array of yearmon strings
       $this_month = date("Y-m", mktime(0, 0, 0, date("m"), date("d"), date("Y")));
       $to = ($input["toYM"] > $this_month) ? $this_month : $input["toYM"];
       $from = ($input["fromYM"] > $to) ? $to : $input["fromYM"];
       $year_mons = self::createYMarray($from, $to);

       // Loop for all months requested
       $num_queued = 0;
       $created_ids = [];
       $updated_ids = [];
       foreach ($year_mons as $yearmon) {
           // Loop for all global providers
           foreach ($global_providers as $global_provider) {

               // Set an array with the report_ids enabled consortium-wide
               $consoProv = $global_provider->consoProviders->where('inst_id',1)->first();
               $conso_reports = ($consoProv) ? $consoProv->reports->pluck('id')->toArray() : [];

               // Loop through all sushisettings
               foreach ($global_provider->sushiSettings as $setting) {
                  // If institution is inactive or this inst_id is not in the $inst_ids array, skip it
                   if ($setting->status != "Enabled" ||
                       !$setting->institution->is_active || !in_array($setting->inst_id,$inst_ids)) {
                       continue;
                   }

                  // Set reports to process based on consortium-wide and, if defined, institution-specific settings_ids
                   $report_ids = array();
                   foreach ($global_provider->consoProviders as $conso_provider) {
                        // if inst-specific provider for an inst different from the current setting, skip it
                        if ($conso_provider->inst_id != 1 && $conso_provider->inst_id != $setting->inst_id) {
                            continue;
                        }
                       $_ids = $conso_provider->reports->pluck('id')->toArray();
                       $report_ids = array_unique(array_merge($report_ids,$_ids));
                   }

                   // Add a "source" value to $reports
                   $report_data = $master_reports->whereIn('id',$report_ids);
                   $reports = $report_data->map(function ($rec) use ($conso_reports) {
                       $rec->source = (in_array($rec->id,$conso_reports)) ? "C" : "I";
                       return $rec;
                   });

                   // Loop through all reports
                   foreach ($reports as $report) {
                      // if this report isn't in $inputs['reports'], skip it
                       if (!in_array($report->name, $input['reports'])) {
                           continue;
                       }

                       // Get the harvest record, if it exists
                       $harvest = HarvestLog::where([['sushisettings_id', '=', $setting->id],
                                                     ['report_id', '=', $report->id],
                                                     ['yearmon', '=', $yearmon]
                                                    ])->first();
                       // Harvest exists
                       if ($harvest) {
                           if ($skip_harvested) {
                             continue;
                           }
                           // We're not skipping... reset the harvest
                           $harvest->attempts = 0;
                           $harvest->status = $state;
                           $harvest->save();
                           $updated_ids[] = $harvest->id;
                       // Insert new HarvestLog record
                       } else {
                           $harvest = HarvestLog::create(['status' => $state, 'sushisettings_id' => $setting->id,
                                                          'report_id' => $report->id, 'yearmon' => $yearmon,
                                                          'source' => $report->source, 'attempts' => 0]);
                           $created_ids[] = $harvest->id;
                       }

                       // If user wants it added now create the queue entry - set replace_data to overwrite
                       if ($input["when"] == 'now') {
                           try {
                               $newjob = SushiQueueJob::create(['consortium_id' => $con->id,
                                                                'harvest_id' => $harvest->id,
                                                                'replace_data' => 1
                                                              ]);
                               $num_queued++;
                           } catch (QueryException $e) {
                               $code = $e->errorInfo[1];
                               if ($code == '1062') {     // If already in queue, continue silently
                                   continue;
                               } else {
                                   $msg = 'Failure adding Harvest ID: ' . $harvest->id . ' to Queue! Error ' . $code;
                                   return response()->json(['result' => false, 'msg' => $msg]);
                               }
                           }
                       }
                   }
               }
           }
       }

       // Setup full details for new harvest entries
       $new = array();
       if (count($created_ids) > 0) {
           $new_data = HarvestLog::whereIn('id', $created_ids)->with('report:id,name','sushiSetting',
                               'sushiSetting.institution:id,name','sushiSetting.provider:id,name',
                               'lastError','failedHarvests','failedHarvests.ccplusError')->get();
           foreach ($new_data as $rec) {
               $new[] = $this->formatRecord($rec);
           }
       }
       // New harvests means "bounds" changed
       $bounds = (count($created_ids) > 0) ? $this->harvestBounds() : array();

       // Setup full details for updated harvest entries
       $updated = array();
       if (count($updated_ids) > 0) {
           $upd_data = HarvestLog::whereIn('id', $updated_ids)->with('report:id,name','sushiSetting',
                               'sushiSetting.institution:id,name','sushiSetting.provider:id,name',
                               'lastError','failedHarvests','failedHarvests.ccplusError')->get();
           foreach ($upd_data as $rec) {
               $updated[] = $this->formatRecord($rec);
           }
       }

       // Send back confirmation with counts of what happened
       $msg  = "Success : " . count($created_ids) . " new harvests added, " . count($updated_ids) . " harvests updated";
       $msg .= ($num_queued > 0) ? ", and " . $num_queued . " queue jobs created." : ".";
       return response()->json(['result'=>true, 'msg'=>$msg, 'new_harvests'=>$new, 'upd_harvests'=>$updated,
                                'bounds'=>$bounds]);
   }

   /**
    * Return available providers for an array of inst_ids or an inst_group
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
   */
   public function availableProviders(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
       $group_id = json_decode($request->group_id, true);
       $insts = json_decode($request->inst_ids, true);

       // Setup an array of inst_ids for querying against the sushisettings
       if ($group_id > 0) {
           $group = InstitutionGroup::with('institutions')->findOrFail($group_id);
           $inst_ids = $group->institutions->pluck('id')->toArray();
       } else if (sizeof($insts) > 0) {
           $inst_ids = $insts;
       } else {
           return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
       }

       // Query the sushisettings for providers connected to the requested inst IDs
       if (in_array(0,$inst_ids)) {
           $availables = SushiSetting::where('status','Enabled')->pluck('prov_id')->toArray();
       } else {
           $availables = SushiSetting::where('status','Enabled')->whereIn('inst_id',$inst_ids)->pluck('prov_id')->toArray();
       }

       // Use availables (IDs) to get the provider data and return it via JSON
       $providers = GlobalProvider::where('is_active', true)->whereIn('id',$availables)
                            ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
       if (sizeof($providers) == 0) {
           return response()->json(['result' => false, 'msg' => 'No matching, active providers found']);
       } else {
           return response()->json(['providers' => $providers], 200);
       }
   }

   /**
    * Update status for a given harvest
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
   public function updateStatus(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

       // Get and verify input or bail with error in json response
       try {
           $input = json_decode($request->getContent(), true);
       } catch (\Exception $e) {
           return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
       }
       if (!isset($input['id']) || !isset($input['status'])) {
           return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
       }

       // The new status will be based on one of 2 possible values:
       //   Queued: resets attempts to zero and requeues the harvest for immediate retrying
       //   Stopped: Sets harvest to "Stopped", regardless of what it was before.
       $new_status_allowed = array('Queued', 'Stopped');
       if (!in_array($input['status'], $new_status_allowed)) {
           return response()->json(['result' => false,
                                    'msg' => 'Invalid request: status cannot be set to requested value.']);
       }

       // Harvests w/ status= 'Success', 'Active', or 'Pending' are NOT changed
       $fixed_status = array('Success', 'Active', 'Pending');
       $harvest = HarvestLog::findOrFail($input['id']);
       if (in_array($harvest->status, $fixed_status)) {
           return response()->json(['result' => false,
                                    'msg' => 'Invalid request: harvest status cannot be changed.']);
       }
       $original_status = $harvest->status;

       // Stopping a harvest also means deleting any corresponding job thats in the queue
       if ($input['status'] == 'Stopped') {
           $harvest->status = 'Stopped';
           $existing_job = SushiQueueJob::where('harvest_id', '=', $harvest->id)->first();
           if ($existing_job) {
               $existing_job->delete();
           }

        // Resetting means attempts get set to zero
       } else {
           $harvest->attempts = 0;
           $harvest->status = 'Queued';
       }

       // Update the harvest record
       try {
           $harvest->save();
       } catch (\Exception $e) {
           return response()->json(['result' => false, 'msg' => 'Error updating harvest!']);
       }
       $harvest['updated'] = substr($harvest->updated_at,0,10);

       // If we're resetting to Queued
       if ($harvest->status == 'Queued') {
           // Get consortium record
           $con = Consortium::where('ccp_key', '=', session('ccp_con_key'))->first();
           if (!$con) {
               return response()->json(['result' => false, 'msg' => 'Error: Corrupt session or consortium settings']);
           }

           // If the harvest was originally set with status = 'Harvested'
           if ($original_status == 'Harvested' &&  !is_null(config('ccplus.reports_path'))) {
               // get rid of the downloaded data file(s)
               $searchPat = config('ccplus.reports_path') . $con->id . '/0_unprocessed/' . $harvest->id . "_*";
               $matches = glob($searchPat);
               foreach ($matches as $_file) {
                 unlink($_file);
               }
           }

           // Create a Job entry if it doesn't exist
           try {
               $newjob = SushiQueueJob::create(['consortium_id' => $con->id,
                                                'harvest_id' => $harvest->id,
                                                'replace_data' => 1
                                              ]);
           } catch (QueryException $e) {
               $code = $e->errorInfo[1];
               if ($code != '1062') {     // If already in queue, continue silently
                   $msg = 'Failure adding Harvest ID: ' . $harvest->id . ' to Queue! Error ' . $code;
                   return response()->json(['result' => false, 'msg' => $msg]);
               }
           }
       }
       return response()->json(['result' => true, 'status' => $harvest->status]);
   }

  /**
   * Display a form for editting the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
   public function edit($id)
   {
       $harvest = HarvestLog::with('report:id,name','sushiSetting', 'sushiSetting.institution:id,name',
                                   'sushiSetting.provider:id,name')
                            ->findOrFail($id);

       // Get any failed attempts, pass as an array
       $data = FailedHarvest::with('ccplusError', 'ccplusError.severity')->where('harvest_id', '=', $id)
                            ->orderBy('created_at','DESC')->get();
       $attempts = $data->map(function ($rec) {
           $rec->severity = $rec->ccplusError->severity->name;
           $rec->message = $rec->ccplusError->message;
           $rec->attempted = date("Y-m-d H:i:s", strtotime($rec->created_at));
           return $rec;
       })->toArray();

       // If harvest successful, pass it as an array
       if ($harvest->status == 'Success') {
           $rec = array('process_step' => 'SUCCESS', 'error_id' => '', 'severity' => '', 'detail' => '');
           $rec['message'] = "Harvest successfully completed";
           $rec['attempted'] = date("Y-m-d H:i:s", strtotime($harvest->created_at));
           array_unshift($attempts,$rec);
       } else {
           // Harvests could have prior failures, but attampes has been reset to zero to requeue ot
           if (sizeof($attempts) == 0) {
               if ($harvest->attempts == 0) {
                   $attempts[] = array('severity' => "Unknown", 'message' => "Harvest has not yet been attempted",
                                       'attempted' => "Unknown");
               } else {
                   $attempts[] = array('severity' => "Unknown", 'message' => "Failure records are missing!",
                                       'attempted' => "Unknown");
               }
           }
       }

       return view('harvests.edit', compact('harvest', 'attempts'));
   }

   /**
    * Display the resource w/ built-in form (manager/admin) for editting the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
   public function show($id)
   {
       //
   }

   /**
    * Update the specified resource in storage.
    *
    * @param  \Illuminate\Http\Request $request
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
   public function update(Request $request, $id)
   {
       abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

       $harvest = HarvestLog::findOrFail($id);
       $this->validate($request, ['status' => 'required']);

       // A harvest being updated to ReQueued means setting attempts to zero
       if ($request->input('status') == 'ReQueued' && $harvest->status != "ReQueued") {
           $harvest->attempts = 0;
       }
       $harvest->status = $request->input('status');
       $harvest->save();
       $harvest->load('report:id,name','sushiSetting','sushiSetting.institution:id,name','sushiSetting.provider:id,name');

       return response()->json(['result' => true, 'harvest' => $harvest]);
   }

   /**
    * Download raw data for a harvest
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
   public function downloadRaw($id)
   {
       $thisUser = auth()->user();
       $harvest = HarvestLog::findOrFail($id);
       if (!$thisUser->hasRole(['Admin'])) {
           if (!$thisUser->hasRole(['Manager']) || $harvest->sushiSetting->inst_id != $thisUser->inst_id) {
               return response()->json(['result' => false, 'msg' => 'Error - Not authorized']);
           }
       }

       // Get consortium_id
       $con = Consortium::where('ccp_key', session('ccp_con_key'))->first();
       if (!$con) {
          return response()->json(['result'=>false, 'msg'=>'Error: Current consortium is undefined.']);
       }

       if (!is_null(config('ccplus.reports_path'))) {
           // Set the path and filename based on config and harvest sushsettings
           $return_name = "";
           $filename  = config('ccplus.reports_path') . $con->id . '/';
           if ($harvest->status == 'Harvested') {
               $searchPat = $filename . "0_unprocessed/" . $harvest->id . "_*";
               $matches = glob($searchPat);
               $filename = (count($matches) > 0) ? $matches[0] : "/_xyzzy_/not-found";
               $return_name = substr($filename, strrpos($filename,'/',0)+1);
           } else {
               $filename .= $harvest->sushiSetting->inst_id . '/' . $harvest->sushiSetting->prov_id . '/';
               $filename .= (is_null($harvest->rawfile)) ? 'not-found' : $harvest->rawfile;
               $return_name = $harvest->rawfile;
           }

           // Confirm the file exists and is readable before trying to decrypt and return it
           if (is_readable($filename)) {
               return response()->streamDownload(function () use ($filename) {
                   echo bzdecompress(Crypt::decrypt(File::get($filename), false));
               }, $return_name);
           } else {
               $msg = 'Raw datafile is not accessible.';
           }
       } else {
           $msg = 'System not configured to save raw data, check config value of CCP_REPORTS.';
       }
       return response()->json(['result' => false, 'msg' => $msg]);
   }

   /**
    * Remove the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
   public function destroy($id)
   {
       $record = HarvestLog::findOrFail($id);
       abort_unless($record->canManage(), 403);
       if (!$record->canManage()) {
           return response()->json(['result' => false, 'msg' => 'Not authorized!']);
       }

       // Delete any related jobs from the global queue
       $jobs = SushiQueueJob::where('harvest_id', $id)->get();
       foreach ($jobs as $job) {
           $job->delete();
       }

       // Delete the harvestlog record itself
       $record->delete();
       return response()->json(['result' => true, 'msg' => 'Log record deleted successfully']);
   }

   /**
    * Delete multiple harvests
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
   public function bulkDestroy(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

       // Get and verify input or bail with error in json response
       try {
           $input = json_decode($request->getContent(), true);
       } catch (\Exception $e) {
           return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
       }
       if (!isset($input['harvests'])) {
           return response()->json(['result' => false, 'msg' => 'Missing expected inputs!']);
       }

       // Get the harvests requested
       $harvests = HarvestLog::whereIn('id', $input['harvests'])->get();
       $skipped = 0;
       $msg = "Result: ";

       // Build a list of IDs that current user allowed to delete
       $deleted = 0;
       $deleteable_ids = [];
       foreach ($harvests as $harvest) {
           if (!$harvest->canManage()) {
               $skipped++;
               continue;
           }
           $deleteable_ids[] = $harvest->id;
       }
       if (count($deleteable_ids) == 0) {
           return response()->json(['result' => false, 'msg' => 'Error: Authorization failed for requested inputs']);
       }
       // Get consortium_id
       $con = Consortium::where('ccp_key', session('ccp_con_key'))->first();
       if (!$con) {
           return response()->json(['result'=>false, 'msg'=>'Error: Cannot delete harvests with current consortium settings.']);
       }

       // Delete any related jobs from the global queue before deleting the harvests
       $result = SushiQueueJob::where('consortium_id',$con->id)->whereIn('harvest_id', $deleteable_ids)->delete();

       // Delete the harvests
       $deleted = HarvestLog::whereIn('id', $deleteable_ids)->delete();

       // return result
       $msg .= $deleted . " records deleted";
       $msg .= ($skipped>0) ? ", and " . $skipped . "records skipped." : " successfully.";
       return response()->json(['result' => true, 'msg' => $msg, 'removed' => $deleteable_ids]);
   }

   /**
    * return #-jobs in the global harvesting queue
    *
    * @param  \Illuminate\Http\Request  $request
    * @return JSON
    */
   public function queueCount(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(["Admin","Manager"]), 403);

       // Get job count for current consortium
       $count = 0;
       $con = Consortium::where("ccp_key", session("ccp_con_key"))->first();
       if ($con) {
          $count = SushiQueueJob::where('consortium_id', $con->id)->count();
       }

       // Get the job-count
       return response()->json(['count' => $count]);
   }

   /**
    * Entry point for getting harvests with jobs in the global harvesting queue
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
   public function harvestJobs(Request $request)
   {
       abort_unless(auth()->user()->hasAnyRole(["Admin","Manager"]), 403);

       // Get and verify input or bail with error in json response
       try {
           $input = json_decode($request->getContent(), true);
       } catch (\Exception $e) {
           return response()->json(["result" => false, "msg" => "Error decoding input!"]);
       }

       // Assign optional inputs to $filters array
       $filters = array("inst" => [], "prov" => [], "rept" => []);
       if ($request->input("filters")) {
           $filter_data = json_decode($request->input("filters"));
           foreach ($filter_data as $key => $val) {
               if (count($val) > 0) {
                   $filters[$key] = $val;
               }
           }
       }

       // Get consortium_id
       $con = Consortium::where("ccp_key", session("ccp_con_key"))->first();
       if (!$con) {
           return response()->json(["result" => true, "jobs" => [], "prov_ids" => [], "inst_ids" => [], "rept_ids" => [] ]);
       }

       // Get jobs from the global queue
       $consodb = config("database.connections.consodb.database");
       $globaldb = config("database.connections.globaldb.database");
       $jobs = DB::table($globaldb . ".jobs as Jobs")->where("consortium_id",$con->id)
                 ->join($consodb . ".harvestlogs as Harv", "Jobs.harvest_id", "Harv.id")
                 ->join($consodb . ".sushisettings as Sset", "Harv.sushisettings_id", "Sset.id")
                 ->join($consodb . ".institutions as Inst", "Sset.inst_id", "Inst.id")
                 ->join($globaldb . ".global_providers as Prov", "Sset.prov_id", "Prov.id")
                 ->join($globaldb . ".reports as Rept", "Harv.report_id", "Rept.id")
                 ->when(count($filters["inst"]) > 0, function ($qry) use ($filters) {
                     return $qry->whereIn("Sset.inst_id", $filters["inst"]);
                 })
                 ->when(count($filters["prov"]) > 0, function ($qry) use ($filters) {
                     return $qry->whereIn("Sset.prov_id", $filters["prov"]);
                 })
                 ->when(count($filters["rept"]) > 0, function ($qry) use ($filters) {
                     return $qry->whereIn("Harv.report_id", $filters["rept"]);
                 })
                 ->orderBy("Jobs.created_at", "ASC")
                 ->get(["Rept.id as report_id","Rept.name as report_name","Harv.status as harvest_status","Harv.yearmon as yearmon",
                        "Harv.error_id as last_error","Inst.id as inst_id","Inst.name as inst_name","Prov.id as prov_id",
                        "Prov.name as prov_name","Jobs.created_at as created_at"]);

       // Send back the jobs data, plus unique IDs for U/I to use for filtering the jobs
       $provs = $jobs->unique('prov_id')->sortBy('prov_id')->pluck('prov_id')->toArray();
       $insts = $jobs->unique('inst_id')->sortBy('inst_id')->pluck('inst_id')->toArray();
       $repts = $jobs->unique('report_id')->sortBy('report_id')->pluck('report_id')->toArray();
       return response()->json(["result" => true, "jobs" => $jobs->toArray(), "prov_ids" => $provs, "inst_ids" => $insts,
                                "rept_ids" => $repts]);
   }

   // Turn a fromYM/toYM range into an array of yearmon strings
   private function createYMarray($from, $to)
   {
       $range = array();
       $start = strtotime($from);
       $end = strtotime($to);
       if ($start > $end) {
           return $range;
       }
       while ($start <= $end) {
           $range[] = date('Y-m', $start);
           $start = strtotime("+1 month", $start);
       }
       return $range;
   }

   // user-defined comparison function to sort based on timestamp
   static function sortTimeStamp($time1, $time2)
   {
       if (strtotime($time1) < strtotime($time2)) {
           return 1;
       } else if (strtotime($time1) > strtotime($time2)) {
           return -1;
       } else {
           return 0;
       }
   }

   // Return an array of bounding yearmon strings based on exsiting Harvestlogs
   //   bounds[0] will hold absolute min and max yearmon for all harvests across all reports
   private function harvestBounds() {

       $conso_db = config('database.connections.consodb.database');

       // Query for min and max yearmon values
       $raw_query = "min(yearmon) as YM_min, max(yearmon) as YM_max";
       $result = HarvestLog::selectRaw($raw_query)->get()->toArray();
       $bounds[0] = $result[0];

       return $bounds;
   }

   // Build a consistent output record using an input harvest record
   // input should include Report, SushiSetting with institution+provider and failedHarvests with ccplusError
   private function formatRecord($harvest) {
       $rec = array('id' => $harvest->id, 'yearmon' => $harvest->yearmon, 'attempts' => $harvest->attempts,
                    'inst_name' => $harvest->sushiSetting->institution->name,
                    'prov_name' => $harvest->sushiSetting->provider->name,
                    'prov_inst_id' => $harvest->sushiSetting->provider->inst_id,
                    'report_name' => $harvest->report->name,
                    'status' => $harvest->status, 'rawfile' => $harvest->rawfile,
                    'updated' => $harvest->updated_at,
                    'error_code' => null, 'error' => []
                   );
       if ($harvest->lastError) {
           $rec['error_code'] = $harvest->error_id;
           $rec['error'] = $harvest->lastError->toArray();
       }
       $rec['failed'] = [];
       if ($harvest->failedHarvests) {
           // Build a URL to test+confirm the error(s); let Sushi class do the work
           $beg = $harvest->yearmon . '-01';
           $end = $harvest->yearmon . '-' . date('t', strtotime($beg));
           $sushi = new Sushi($beg, $end);
           // setup required connectors for buildUri
           $prov_connectors = $harvest->sushiSetting->provider->connectors;
           $connectors = $this->connection_fields->whereIn('id',$prov_connectors)->pluck('name')->toArray();
           $rec['retryUrl'] = $sushi->buildUri($harvest->sushiSetting, $connectors, 'reports', $harvest->report);
           // Format and save the failed records
           foreach ($harvest->failedHarvests->sortByDesc('created_at') as $fh) {
               $info = array("id" => $fh->id, "ts" => date("Y-m-d H:i:s", strtotime($fh->created_at)),
                             "code" => $fh->ccplusError->id, "message" => $fh->ccplusError->message);
               // U/I will point to COUNTER docs for details on COUNTER error codes
               $info['detail'] = ($fh->ccplusError->id < 1000 || $fh->ccplusError->id >= 9000) ? $fh->detail : "";
               $info['help_url'] = (is_null($fh->help_url)) ? "" : $fh->help_url;
               $rec['failed'][] = $info;
           }
       }
       return $rec;
   }

}
