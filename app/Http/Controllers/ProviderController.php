<?php

namespace App\Http\Controllers;

use DB;
use App\Provider;
use App\Institution;
use App\Report;
use App\HarvestLog;
use App\SushiSetting;
use App\ConnectionField;
use App\GlobalProvider;
use App\Consortium;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\Writer\Xls;

class ProviderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Return provider data for JSON request (matches what dashboards expect)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);
        $json = ($request->input('json')) ? true : false;

        // Nothing needs to hit this method anymore, except JSON requests from provider datatable
        if (!$json) {
            return response()->json(['result' => false, 'msg' => 'Module disabled - check source in github find a developer.']);
        }

        // Get master report definitions
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Get all (consortium) providers, extract array of global IDs
        $conso_providers = Provider::with('reports:id,name','globalProv','globalProv.sushiSettings:id,prov_id,last_harvest',
                                          'institution:id,name,is_active')->orderBy('name','ASC')->get();

        // Build list of providers, based on globals, that includes extra institution-specific providers
        $global_providers = GlobalProvider::where('is_active', true)-> orderBy('name')
                                          ->get()->sortBy('name', SORT_NATURAL|SORT_FLAG_CASE);
        $output_providers = [];
        foreach ($global_providers as $rec) {
            $report_state = [];
            $conso_reports = [];
            $rec->global_prov = $rec->toArray();
            $rec->connectors = $rec->connectionFields();
            $rec->can_edit = false;   // default value for unconnected global provider
            $rec->can_connect = true; //    "      "    "       "         "       "
            $rec->inst_id = null;
            $rec->inst_name = null;
            $rec->active = ($rec->is_active) ? 'Active' : 'Inactive';
            $rec->can_delete = false;
            $rec->connected = array();

            // Setup connected institution data for all outpute records
            $connected_insts = array();
            $connected_providers = $conso_providers->where('global_id',$rec->id);
            foreach ($connected_providers as $prov_data) {
                $_name = ($prov_data->inst_id == 1) ? 'Consortium' : $prov_data->institution->name;
                $connected_insts[] = array('id' => $prov_data->inst_id, 'name' => $_name);
            }
            // Include globals not connected to the consortium in the array
            $conso_connection = $connected_providers->where('inst_id',1)->first();
            if ($conso_connection) {
                $conso_reports = $conso_connection->reports->pluck('id')->toArray();
            } else {
                $rec->connected = array();
                $rec->connection_count = 0;
                $output_providers[] = $rec->toArray();
            }
            $rec->conso_id = ($conso_connection) ? $conso_connection->id : null;

            // Reset master reports to the globally available reports
            $master_ids = $rec->master_reports;
            $rec->master_reports = $master_reports->whereIn('id', $master_ids)->values()->toArray();
            $rec->report_state = $this->reportState($master_reports, $conso_reports, []);

            // Include all providers connected to the global in the array
            foreach ($connected_providers as $prov_data) {
                $rec->inst_id = $prov_data->inst_id;
                $rec->inst_name = $prov_data->institution->name;
                $rec->inst_stat = ($prov_data->institution->is_active) ? "isActive" : "isInactive";
                // inst-specific providers show only one connection; consortium providers include all
                if ($rec->inst_id==1) {
                  $rec->connected = $connected_insts;
                } else {
                  $rec->name = $prov_data->name;
                  $rec->connected = array( array('id' => $rec->inst_id, 'name' => $rec->inst_name) );
                }
                $rec->connection_count = count($rec->connected);
                $rec->can_edit = true;
                $rec->is_active = $prov_data->is_active;
                $rec->active = ($prov_data->is_active) ? 'Active' : 'Inactive';
                $rec->last_harvest = $prov_data->globalProv->sushiSettings->max('last_harvest');
                $rec->allow_inst_specific = $prov_data->allow_inst_specific;
                if ($conso_connection) {
                    $rec->can_connect = ($conso_connection->allow_inst_specific && $rec->inst_id == 1) ? true : false;
                } else {
                    $rec->can_connect = ($rec->inst_id == 1) ? true : false;
                }
                $rec->can_delete = (is_null($rec->last_harvest)) ? true : false;
                if ($prov_data->reports) {
                    $report_ids = $prov_data->reports->pluck('id')->toArray();
                    $combined_ids = array_unique(array_merge($conso_reports, $report_ids));
                    $rec->report_state = $this->reportState($master_reports, $conso_reports, $combined_ids);
                }
                $output_providers[] = $rec->toArray();
            }
        }
        $providers = array_values($output_providers);
        return response()->json(['providers' => $providers], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $thisUser = auth()->user();
        $con_prov = Provider::with('reports:reports.id,reports.name','globalProv')->findOrFail($id);
        // $con_prov holds the database record, $provider is what we'll build and pass to the UI
        $provider = $con_prov->data();

       // Build data to be passed based on whether the user is admin or Manager
        $limit_to_insts = null;
        if ($thisUser->hasRole("Admin")) {
            $sushi_settings = SushiSetting::with('institution')->where('prov_id',$con_prov->global_id)->get();

            // Get last_harvest for the provider (ALL insts) as determinant for whether it can be deleted
            $last_harvest = $sushi_settings->max('last_harvest');
            $provider['can_delete'] = (is_null($last_harvest)) ? true : false;

            // Make an institutions list
            $institutions = Institution::where('id','>',1)->orderBy('name', 'ASC')->get(['id','name'])->toArray();
            array_unshift($institutions,array('id' => 1,'name' => 'Consortium'));

            // Setup an array of insts without settings for this provider
            $set_inst_ids = $con_prov->globalProv->sushiSettings->pluck('inst_id');
            $set_inst_ids[] = 1;
            $unset_institutions = Institution::whereNotIn('id', $set_inst_ids)
                                             ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        } else {  // Managers/Users are limited to their own inst
            $user_inst = $thisUser->inst_id;
            $limit_to_insts = array($user_inst);
            $sushi_settings = SushiSetting::with('institution')
                                          ->where('prov_id',$con_prov->global_id)->where('inst_id', $user_inst)->get();
            $last_harvest = $sushi_settings->max('last_harvest');
            $provider['can_delete'] = ($thisUser->inst_id == $con_prov->inst_id && is_null($last_harvest)) ? true : false;
            $institutions = Institution::where('id', '=', $user_inst)->get(['id','name'])->toArray();
            $unset_institutions = array();
            if ($sushi_settings->count() == 0) {
                $unset_institutions[] = Institution::where('id', $user_inst)->first()->toArray();
            }
        }

        // Add on Sushi Settings
        $provider['sushiSettings'] = $sushi_settings->toArray();

        // Master reports limited to whet is defined for the related global provider
        $master_reports = Report::whereIn('id',$con_prov->globalProv->master_reports)->orderBy('dorder','ASC')->get(['id','name']);

        // setup reprts_state structure to mkae checkboxres with
        $rpt_state = [];
        foreach ($master_reports as $rpt) {
            $rpt_state[$rpt->name] = ($con_prov->reports->where('name',$rpt->name)->first()) ? true : false;
        }
        $provider['report_state'] = $rpt_state;

        // Connection fields is the set defined for the related global provider
        $connectors = $con_prov->globalProv->connectionFields();
        // Get 10 most recent harvests
        $harvests = HarvestLog::with('report:id,name',
                                     'sushiSetting',
                                     'sushiSetting.institution:id,name',
                                     'sushiSetting.provider:id,name'
                                    )
                              ->join('sushisettings', 'harvestlogs.sushisettings_id', '=', 'sushisettings.id')
                              ->when($limit_to_insts, function ($query, $limit_to_insts) {
                                    return $query->whereIn('sushisettings.inst_id', $limit_to_insts);
                              })
                              ->where('sushisettings.prov_id', $id)
                              ->orderBy('harvestlogs.updated_at', 'DESC')->limit(10)
                              ->get('harvestlogs.*')->toArray();

        return view('providers.show', compact(
            'provider',
            'institutions',
            'unset_institutions',
            'master_reports',
            'connectors',
            'harvests'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return redirect()->route('providers.show', [$id]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Provider  $provider
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $thisUser = auth()->user();
        $is_admin = $thisUser->hasRole('Admin');
        $is_manager = $thisUser->hasRole('Manager');
        abort_unless(($is_admin || $is_manager), 403);

        // Validate form inputs
        $global = GlobalProvider::with('sushiSettings')->findOrFail($id);
        $this->validate($request, ['is_active' => 'required', 'inst_id' => 'required']);
        $input = $request->all();

        // Local admin can only interact with providers for their own inst_id
        if (!$is_admin && $input['inst_id']!=$thisUser->inst_id) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

        // Get all consoDB Provider connections to the global and check for conso-wide setting for the global
        // NOTE:: changing the Active/Inactive for a CONSORTIUM-wide provider will update the is_active
        //        value of all added INST-specific provider copies
        $connected = Provider::with('globalProv','globalProv.sushiSettings','reports','institution:id,name')
                             ->where('global_id',$id)->get();

        // Find the provider record to update
        $provider = $connected->where('inst_id',$input['inst_id'])->first();
        if (!$provider) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Not Found or Not Authorized']);
        }
        $connected_ids = $connected->pluck('id')->toArray();
        $conso_connection = $connected->where('inst_id',1)->first();
        $was_active = $provider->is_active;
        $new_is_active = ($input['is_active']==1) ? 1 : 0;

        // Setup arrays for report assignments/changes and get master reports
        $added = array();
        $removed = array();
        $input_ids = array();
        $report_ids = array();
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Get related sushi settings
        if ($input['inst_id'] == 1) {
            $settings = $global->sushiSettings->where('prov_id',$id);
        } else {
            $settings = $global->sushiSettings->where('prov_id',$id)->where('inst_id',$input['inst_id']);
        }

        // If we're just updating status, do it now and skip the rest (U/I toggle-switch and bulk-status updates)
        if (count($input) == 2) { // only is_active and inst_id given as input
            // Did is_active change?
            if ($was_active != $new_is_active) {
                if ($provider->inst_id==1) {  // conso-setting cascades to the copies
                    $res = Provider::whereIn('id',$connected_ids)->update(['is_active' => $new_is_active]);
                } else {                      // inst-specific ; just set/save is_active
                    $provider->is_active = $new_is_active;
                    $provider->save();
                }

                // Update related sushi setting(s)
                foreach ($settings as $setting) {
                    // Went from Active to Inactive
                    if ($was_active) {
                        if ($setting->status != 'Disabled') {
                            $setting->update(['status' => 'Suspended']);
                        }
                    // Went from Inactive to Active
                    } else {
                        $setting->resetStatus();
                    }
                }
            }
            return response()->json(['result' => true]);
        }

        // Setup arrays for report assignments/changes
        $added = array();
        $removed = array();
        $input_ids = array();
        $report_ids = array();
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
        if (isset($input['report_state'])) {
            $current_ids = $provider->reports->pluck('id')->toArray();
            $global_master_list = $global->master_reports;
            foreach ($global_master_list as $gid) {
                $master = $master_reports->where('id',$gid)->first();
                if (!$master) continue;
                // If we're updating reports for a non-conso provider, ignore prov_enabled
                // since the consortium has it enabled already
                if ($provider->inst_id!=1 && $input['report_state'][$master->name]['conso_enabled']) {
                    continue;
                }
                if ($input['report_state'][$master->name]['prov_enabled']) {
                    $input_ids[] = $gid;
                }
            }
            // make arrays of IDs to remove or add
            $removed = array_diff($current_ids, $input_ids);
            $added = array_diff($input_ids, $current_ids);
        }

        // Setup fields for update
        // Updating an inst-specific copy of a conso-provider only updates is_active and report assignments
        if ($conso_connection && $provider->inst_id!=1) {
            $prov_input = array('is_active' => $input['is_active']);
        } else {
            $prov_input = array_except($input,array('master_reports','allow_sushi','report_state'));
            if (isset($input['allow_inst_specific'])) {
                $prov_input['allow_inst_specific'] = ($input['allow_inst_specific']) ? 1 : 0;
            }
        }

        // Update the provider record
        $provider->update($prov_input);

        // Update is_active value for inst-specific copies if conso-provider status changed
        if ($provider->inst_id==1 && $was_active!=$new_is_active) {
            $res = Provider::whereIn('id',$connected_ids)->update(['is_active' => $new_is_active]);
        }

        // Update related sushi setting(s)
        foreach ($settings as $setting) {
            // Went from Active to Inactive
            if ($was_active) {
                if ($setting->status != 'Disabled') {
                    $setting->update(['status' => 'Suspended']);
                }
            // Went from Inactive to Active
            } else {
                $setting->resetStatus();
            }
        }

        // Update report assignments - attach/detach based on $added and $removed
        foreach ($removed as $dr) {
            $provider->reports()->detach($dr);
        }
        foreach ($added as $ar) {
            $provider->reports()->attach($ar);
        }

        // If reports were added or removed
        if (count($added) > 0 || count($removed) > 0) {
            // Reload reports
            $provider->load('reports');

            // Call updateReports() in case we need to delete provider(s) that have no reports
            // in additon to what the consortium is pulling
            if ($provider->inst_id==1) {  // use newly-updated conso-report settings
                $conso_ids = $provider->reports->pluck('id')->toArray();
            } else {
                $conso_ids = ($conso_connection) ? $conso_connection->reports->pluck('id')->toArray() : [];
            }
            $res = $this->updateReports($id, $conso_ids, "detach");

            // Reset $connected if we deleted one or more providers
            if ($res > 0) {
                $connected = Provider::with('globalProv','globalProv.sushiSettings','reports','institution:id,name')
                                     ->where('global_id',$id)->get();
            }

            // If we just updated a consortium provider (inst_id=1), reset $conso_connection
            if ($provider->inst_id==1) {
              $conso_connection = $connected->where('inst_id',1)->first();
            }
        }
        $conso_reports = ($conso_connection) ? $conso_connection->reports->pluck('id')->toArray() : [];

        // Build return provider data Object that matches what index() sends
        $return_provider = $global;
        $inst_connection = $connected->where('inst_id',$provider->inst_id)->first();
        $return_provider->conso_id = ($conso_connection) ? $conso_connection->id : null;
        $return_provider->global_prov = $global->toArray();
        $return_provider->content_provider = $global->content_provider;
        if ($conso_connection) {
            $return_provider->is_conso = true;
            $return_provider->inst_id = $conso_connection->inst_id;
            $return_provider->inst_name = $conso_connection->institution->name;
        } else {
            $return_provider->is_conso = false;
            $return_provider->inst_id = $provider->inst_id;
            $return_provider->inst_name = $provider->institution->name;
        }
        $return_provider->is_active = ($conso_connection && $provider->inst_id==1) ? $conso_connection->is_active
                                                                                   : $provider->is_active;
        $return_provider->active = ($return_provider->is_active) ? "Active" : "Inactive";

        // Admins see conso-reports for providers that have a conso-connection
        if ($is_admin && $conso_connection) {
            $input_ids = $conso_connection->reports->pluck('id')->toArray();
        }
        $return_provider->report_state = $this->reportState($master_reports, $conso_reports, $input_ids);
        $return_provider->can_connect = (!$conso_connection && $is_admin) ? true : false;
        $return_provider->connectors = $global->connectionFields();
        $return_provider->can_edit = true;
        $return_provider->can_delete = true;
        $return_provider->day_of_month = $global->day_of_month;
        $return_provider->last_harvest = $global->sushiSettings->max('last_harvest');
        $return_provider->can_delete = (is_null($provider->last_harvest)) ? true : false;
        $return_provider->allow_inst_specific = $provider->allow_inst_specific;

        // Set master reports to the globally available reports
        $master_ids = $global->master_reports;
        $return_provider->master_reports = $master_reports->whereIn('id', $master_ids)->values()->toArray();

        // Setup flags to control per-report icons in the U/I
        $inst_reports = ($inst_connection) ? $inst_connection->reports->pluck('id')->toArray() : [];
        $report_flags = $this->setReportFlags($master_reports, $master_ids, $conso_reports, $inst_reports);
        foreach ($report_flags as $rpt) {
            $return_provider->{$rpt['name'] . "_status"} = $rpt['status'];
        }

        // Build an array of connected details
        $connected_data = array();
        $all_deleteable = true;
        foreach ($connected as $prov_data) {
            $_rec = $prov_data->toArray();
            $_rec['active'] = ($prov_data->is_active) ? "Active" : "Inactive";
            $_rec['inst_name'] = ($prov_data->inst_id == 1) ? 'Consortium' : $prov_data->institution->name;
            $_rec['inst_stat'] = ($prov_data->institution->is_active) ? "isActive" : "isInactive";
            $_inst_reports = $prov_data->reports->pluck('id')->toArray();
            $combined_ids = array_unique(array_merge($conso_reports, $_inst_reports));
            $_rec['report_state'] = $this->reportState($master_reports, $conso_reports, $combined_ids);
            $_rec['master_reports'] = $return_provider->master_reports;
            $_rec['last_harvest'] = $global->sushiSettings->max('last_harvest');
            $_rec['can_edit'] = true;
            $_rec['can_delete'] = (is_null($_rec['last_harvest'])) ? true : false;
            $_rec['allow_inst_specific'] = ($prov_data->inst_id == 1) ? $prov_data->allow_inst_specific : 0;
            if (!$_rec['can_delete']) $all_deleteable = false;
            $connected_data[] = $_rec;
        }
        // Set return value based on connected settings
        $return_provider->connected = $connected_data;
        $return_provider->connection_count = $connected->count();
        $return_provider->can_delete = ($all_deleteable);
        return response()->json(['result' => true, 'msg' => 'Provider settings successfully updated',
                                 'provider' => $return_provider]);
    }

    /**
     * Connect a global provider.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function connect(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        $localAdmin = (!$thisUser->hasRole('Admin'));

        // Handle inputs
        if (is_null($request->input('inst_id')) || is_null($request->input('global_id'))) {
            return response()->json(['result' => false, 'msg' => 'Store Failed: missing input arguments!']);
        }
        $input = $request->all();

        // Local Admins only create providers for their own institutions
        $localInst = null;
        if ($localAdmin && $input['inst_id'] != $thisUser->inst_id) {
            $input['inst_id'] = $thisUser->inst_id;
            $localInst = $thisUser->inst_id;
        }
        // Get global provider record
        $global_provider = GlobalProvider::with('sushiSettings')->findOrFail($input['global_id']);

        // Check for a consortium-wide setting and set conso_reports so we can ignore conso-reports when
        // attaching the new provider reports)
        $conso_connection = Provider::where('inst_id',1)->where('global_id',$input['global_id'])->first();
        $conso_reports = ($conso_connection) ? $conso_connection->reports->pluck('id')->toArray() : [];

        // Create the provider
        $provider = new Provider;
        $provider->inst_id = $input['inst_id'];
        $provider->global_id = $input['global_id'];
        $provider->name = (isset($input['name'])) ? $input['name'] : $global_provider->name;
        $provider->is_active = (isset($input['is_active'])) ? $input['is_active'] : $global_provider->is_active;
        $provider->allow_inst_specific = (isset($input['allow_inst_specific'])) ? $input['allow_inst_specific'] : 0;
        $provider->save();

        //Attach report definitions to new provider
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
        $master_ids = $global_provider->master_reports;
        foreach ($master_reports as $rpt) {
            if (in_array($rpt->id, $master_ids) && !in_array($rpt->id,$conso_reports)) {
                if (isset($input['report_state'])) {
                    if ($input['report_state'][$rpt->name]['prov_enabled']) {
                        $provider->reports()->attach($rpt->id);
                    }
                }
            }
        }

        // Update $conso_connection and $conso_reports if we just created a conso connection
        if ($provider->inst_id == 1) {
            $conso_connection = $provider;
            $conso_reports = $conso_connection->reports->pluck('id')->toArray();
        }

        // If requested, create a sushi-setting to give a "starting point" for connecting it later
        $existing_setting = $global_provider->sushiSettings->where('inst_id',$input['inst_id'])->first();
        $stub = (isset($input['sushi_stub']) && !$existing_setting) ? $input['sushi_stub'] : 0;
        if ($stub) {
            $sushi_setting = new SushiSetting;
            $sushi_setting->inst_id = $input['inst_id'];
            $sushi_setting->prov_id = $input['global_id'];
            // Add required conenction fields to sushi args
            foreach ($global_provider->connectionFields() as $cnx) {
                $sushi_setting->{$cnx['name']} = "-required-";
            }
            $sushi_setting->status = "Incomplete";
            $sushi_setting->save();
        }

        // If we just connected a provider conso-wide, detach reports assignments for all other
        // connected providers that we just enabled in the consortium-wide definition. updateReports
        // will also delete any inst-specific definitions with zero reports, so this needs doing now,
        // before we try to build an array of inst-specific connected providers.
        $conso_report_ids = ($conso_connection) ? $conso_connection->reports->pluck('id')->toArray() : [];
        $inst_report_ids = [];
        if ($provider->inst_id==1) {
            $res = $this->updateReports($global_provider->id, $conso_report_ids, "detach");
        } else {
            $inst_report_ids = $provider->reports->pluck('id')->toArray();
        }

        // Setup return object - essentially the global provider updated to reflect the new connection
        $returnProv = $global_provider;
        $returnProv->is_active = ($conso_connection) ? $conso_connection->is_active : $provider->is_active;
        $returnProv->global_prov = $global_provider->toArray();
        $returnProv->connectors = $global_provider->connectionFields();
        // Ignore connections to other institutions for a localAdmin (manager)
        if ($localAdmin) {
            $connected_providers = Provider::with('institution:id,name,is_active','reports:id,name','globalProv')
                                           ->where('global_id', $global_provider->id)
                                           ->whereIn('inst_id',[1,$thisUser->inst_id])
                                           ->get();
        } else {
            $connected_providers = Provider::with('institution:id,name,is_active','reports:id,name','globalProv')
                                           ->where('global_id', $global_provider->id)
                                           ->get();
        }

        // Reset master reports (from an array of IDs) to the globally available reports (array of objects)
        $returnProv->master_reports = $master_reports->whereIn('id', $master_ids)->values()->toArray();
        $returnProv->is_conso = ($conso_connection) ? true : false;
        $returnProv->inst_id = ($conso_connection) ? 1 : $provider->inst_id;
        $returnProv->conso_id = ($conso_connection) ? $conso_connection->id : null;
        $returnProv->allow_inst_specific = ($conso_connection) ? $conso_connection->allow_inst_specific : 0;
        $returnProv->last_harvest = $global_provider->sushiSettings->max('last_harvest');

        // Setup flags to control per-report icons in the U/I
        $report_flags = $this->setReportFlags($master_reports, $master_ids, $conso_report_ids, $inst_report_ids);
        foreach ($report_flags as $rpt) {
            $returnProv->{$rpt['name'] . "_status"} = $rpt['status'];
        }

        // If global provider is connected
        $connected_data = array();
        if ($connected_providers) {

            // Build an array of details for connected insts
            foreach ($connected_providers as $prov_data) {
                $_rec = $prov_data->toArray();
                $_rec['inst_name'] = ($prov_data->inst_id == 1) ? 'Consortium' : $prov_data->institution->name;
                $_rec['inst_stat'] = ($prov_data->institution->is_active) ? "isActive" : "isInactive";
                $_inst_reports = $prov_data->reports->pluck('id')->toArray();
                $combined_ids = array_unique(array_merge($conso_reports, $_inst_reports));
                $_rec['report_state'] = $this->reportState($master_reports, $conso_reports, $combined_ids);
                $_rec['master_reports'] = $returnProv->master_reports;
                $_rec['last_harvest'] = $returnProv->last_harvest;
                $_rec['can_edit'] = true;
                $_rec['can_delete'] = (is_null($_rec['last_harvest'])) ? true : false;
                $_rec['allow_inst_specific'] = ($prov_data->inst_id == 1) ? $prov_data->allow_inst_specific : 0;
                $connected_data[] = $_rec;
            }
        }

        $returnProv->connected = $connected_data;
        $returnProv->connection_count = $connected_providers->count();
        $returnProv->can_edit = $provider->canManage();
        $returnProv->can_delete = $provider->canManage();
        $returnProv->can_connect = (!$conso_connection && !$localAdmin) ? true : false;
        $returnProv->active = ($returnProv->is_active) ? 'Active' : 'Inactive';

        return response()->json(['result' => true, 'msg' => 'Provider successfully connected', 'provider' => $returnProv]);
    }

    /**
     * Remove the specified resource(s) from storage.
     *
     * @param  \App\Provider  $id   // Global provider ID
     */
    // public function destroy($id)
    public function destroy(Request $request)
    {}

    /**
     * Remove the specified resource(s) from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\GlobalProvider  $globalProvID   // Global provider ID
     * @param  \App\Provider        $instProvID     // Institution-Specific provider ID
     */
    // public function destroy($id)
    public function customDestroy(Request $request, $globalProvID, $instProvID)
    {
        $thisUser = auth()->user();
        $deleted = false;
        if (is_null($instProvID) && !$thisUser->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Consortium-wide deletion requires Admin Role']);
        }

        // If provider has saved data, return error
        if ($this->hasSavedReportData($globalProvID, $instProvID)) {
            return response()->json(['result' => false, 'msg' => 'Provider has saved report data, cannot delete']);
        }

        // Get all provider definitions that match the global $id
        if ($instProvID == 0) {
            $providers = Provider::where('global_id',$globalProvID)->with('globalProv')->get();
        } else {
            $providers = Provider::where('id',$instProvID)->with('globalProv')->get();
        }

        // Delete all authorized providers, and fail silently on individual attempts
        foreach ($providers as $prov) {
            if ($prov->canManage()) {
              try {
                  $prov->delete();
                  $deleted = true;
              } catch (\Exception $ex) {}
            }
        }
        // Return result
        $msg = ($deleted) ? 'Provider definition(s) successfully deleted' : 'No providers deleted - authorization failed';
        return response()->json(['result' => $deleted, 'msg' => $msg]);
    }

    /**
     * Export provider records from the database.
     * NOTE:: Provider exports are based on GLOBAL providers, and related to institutions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
     public function export(Request $request)
    {
        $thisUser = auth()->user();

        // Only admins and managers can export
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);

       // Admins get all providers
        if ($thisUser->hasRole("Admin")) {
            $providers = Provider::with('globalProv','reports','institution')->orderBy('name', 'ASC')->get();

       // Managers get all consortia-wide providers and those that match user's inst_id
       // (excludes providers assigned to institutions.)
        } else {
            $providers = Provider::with('globalProv','reports','institution')->whereIn('inst_id', [1,$thisUser->inst_id])
                                 ->orderBy('name', 'ASC')->get();
        }

        // Setup some styles arrays
        $leftbold_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];
        $topleft_style = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                           ],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E7');
        $info_sheet->getStyle('A1:E8')->applyFromArray($topleft_style);
        $info_sheet->getStyle('A1:E7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Providers tab represents a starting place for updating or importing settings. The table\n";
        $top_txt .= "below describes the datatype and order that the import expects. Any Import rows without a global provider\n";
        $top_txt .= "ID value in column A and a valid Institution ID column B will be ignored. If values are missing or invalid\n";
        $top_txt .= "for columns (B-H), but not required, they will be set to the 'Default'.\n\n";
        $top_txt .= "Any header row or columns beyond 'H' will be ignored. Once the data sheet contains everything\n";
        $top_txt .= "to be updated or inserted, save the sheet as a CSV and import it into CC-Plus.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A8')->applyFromArray($leftbold_style);
        $info_sheet->setCellValue('A8', "NOTE:");
        $info_sheet->mergeCells('B8:E10');
        $info_sheet->getStyle('B8:E10')->applyFromArray($topleft_style);
        $info_sheet->getStyle('B8:E10')->getAlignment()->setWrapText(true);
        $info_sheet->getStyle('A12:E20')->applyFromArray($topleft_style);
        $note_txt  = "Provider imports cannot be used to delete existing providers; only additions and updates are\n";
        $note_txt .= "supported. The recommended approach is to add to, or modify, a previously run full export\n";
        $note_txt .= "to ensure that desired end result is achieved.";
        $info_sheet->setCellValue('B8', $note_txt);
        $info_sheet->getStyle('A12:E12')->applyFromArray($leftbold_style);
        $info_sheet->setCellValue('A12', 'Column Name');
        $info_sheet->setCellValue('B12', 'Data Type');
        $info_sheet->setCellValue('C12', 'Description');
        $info_sheet->setCellValue('D12', 'Required');
        $info_sheet->setCellValue('E12', 'Default');
        $info_sheet->setCellValue('A13', 'Global Provider ID');
        $info_sheet->setCellValue('B13', 'Integer');
        $info_sheet->setCellValue('C13', 'Unique CC-Plus Provider ID');
        $info_sheet->setCellValue('D13', 'Yes');
        $info_sheet->setCellValue('A14', 'Institution ID');
        $info_sheet->setCellValue('B14', 'Integer');
        $info_sheet->setCellValue('C14', 'Unique CC-Plus Institution ID (Consortium is ID=1)');
        $info_sheet->setCellValue('D14', 'Yes');
        $info_sheet->setCellValue('A15', 'Name');
        $info_sheet->setCellValue('B15', 'String');
        $info_sheet->setCellValue('C15', 'Provider name');
        $info_sheet->setCellValue('D15', 'No');
        $info_sheet->setCellValue('E15', 'Global Provider Name');
        $info_sheet->setCellValue('A16', 'Active');
        $info_sheet->setCellValue('B16', 'String (Y or N)');
        $info_sheet->setCellValue('C16', 'Make the provider active?');
        $info_sheet->setCellValue('D16', 'No');
        $info_sheet->setCellValue('E16', 'Yes');
        $info_sheet->setCellValue('A17', 'Inst-Specific');
        $info_sheet->setCellValue('B17', 'String (Y or N)');
        $info_sheet->setCellValue('C17', 'Allow Institutional-Specific Definition (when InstID=1)');
        $info_sheet->setCellValue('D17', 'No');
        $info_sheet->setCellValue('E17', 'No');
        $info_sheet->setCellValue('A18', 'Master Reports');
        $info_sheet->setCellValue('B18', 'Integer');
        $info_sheet->setCellValue('C18', 'CSV list of Master Report IDs to Harvest (e.g. : 1,3)');
        $info_sheet->setCellValue('D18', 'No');
        $info_sheet->setCellValue('E18', 'None');
        // Set row height and auto-width columns for the sheet

        for ($r = 1; $r < 19; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the provider data into a new sheet
        $providers_sheet = $spreadsheet->createSheet();
        $providers_sheet->setTitle('Providers');
        $providers_sheet->setCellValue('A1', 'Global Id');
        $providers_sheet->setCellValue('B1', 'Institution ID');
        $providers_sheet->setCellValue('C1', 'Name');
        $providers_sheet->setCellValue('D1', 'Active');
        $providers_sheet->setCellValue('E1', 'Inst-Specific');
        $providers_sheet->setCellValue('F1', 'Master Reports');
        $providers_sheet->setCellValue('H1', 'Institution Name');
        $providers_sheet->setCellValue('I1', 'Report Names');
        $row = 2;
        foreach ($providers as $provider) {
            $providers_sheet->getRowDimension($row)->setRowHeight(15);
            $providers_sheet->setCellValue('A' . $row, $provider->global_id);
            $providers_sheet->setCellValue('B' . $row, $provider->inst_id);
            $providers_sheet->setCellValue('C' . $row, $provider->globalProv->name);
            $_stat = ($provider->is_active) ? "Y" : "N";
            $providers_sheet->setCellValue('D' . $row, $_stat);
            $_ais = ($provider->allow_inst_specific) ? "Y" : "N";
            $providers_sheet->setCellValue('E' . $row, $_ais);
            $_name = ($provider->inst_id == 1) ? "Consortium" : $provider->institution->name;
            $_report_ids = "";
            $_report_names = "";
            $providers_sheet->setCellValue('I' . $row, 'NULL');
            foreach ($provider->reports as $rpt) {
                $_report_ids .= $rpt->id . ", ";
                $_report_names .= $rpt->name . ", ";
            }
            $_report_ids = rtrim(trim($_report_ids), ',');
            $_report_names = rtrim(trim($_report_names), ',');
            $providers_sheet->setCellValue('F' . $row, $_report_ids);
            $providers_sheet->setCellValue('I' . $row, $_report_names);
            $providers_sheet->setCellValue('H' . $row, $_name);
            $row++;
        }

        // Auto-size and style the output sheet
        $columns = array('A','B','C','D','E','F','G','H','I');
        foreach ($columns as $col) {
            $providers_sheet->getColumnDimension($col)->setAutoSize(true);
            $providers_sheet->getStyle($col)->applyFromArray($topleft_style);
        }
        $providers_sheet->getStyle('A1:J1')->applyFromArray($leftbold_style);

        // Give the file a meaningful filename
        if ($thisUser->hasRole('Admin')) {
            $fileName = "CCplus_" . session('ccp_con_key', '') . "_Providers.xlsx";
        } else {
            $fileName = "CCplus_" . preg_replace('/ /', '', $thisUser->institution->name) . "_Providers.xlsx";
        }

        // redirect output to client browser
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    /**
     * Import providers from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Only Admins can import provider data
        abort_unless(auth()->user()->hasRole('Admin'), 403);

        // Handle and validate inputs
        $this->validate($request, ['csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }

        // Get the CSV data
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Get existing global_provider, consortium provider, institution, and reports data
        $global_providers = GlobalProvider::where('is_active', true)->get();
        $providers = Provider::with('reports','institution')->get();
        $institutions = Institution::get();
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Process the input rows
        $cur_prov_id = 0;
        $prov_skipped = 0;
        $prov_updated = 0;
        $prov_created = 0;
        $seen_provs = array();          // keep track of provider already processed while looping
        foreach ($rows as $row) {

            // Ignore bad/missing/invalid Global IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0]) || !is_numeric($row[1]) || sizeof($row) < 6) {
                continue;
            }

            // valid global provider is required
            $global_id = intval($row[0]);
            $global_prov = $global_providers->where("id", $global_id)->first();
            if (!$global_prov) {
                continue;
            }

            // valid institution is required
            $inst_id = intval($row[1]);
            $institution = $institutions->where("id", $inst_id)->first();
            if (!$institution) {
                continue;
            }

            // see if there is a match on existing providers
            $current_prov = $providers->where("global_id", $global_id)->where('inst_id',$inst_id)->first();
            $cur_prov_id = ($current_prov) ? $current_prov->id : null;

            // If we already processed this provider, skip this record
            if ($current_prov && in_array($cur_prov_id, $seen_provs)) {
                $prov_skipped++;
                continue;
            }

            // Update/Add the provider data/settings
            // Check ID and name columns for silliness or errors
            $_name = trim($row[2]);
            if (strlen($_name) < 1) {       // If import-name empty, use global name
                $_name = trim($global_prov->name);
            }

            // Ok - we're gonna save something - Enforce defaults
            $_active = ($row[3] == 'N') ? 0 : 1;
            $_allow_inst = ($row[4] == 'Y' && $inst_id==1) ? 1 : 0;

            // Put provider data columns into an array
            $_prov = array('id' => $cur_prov_id, 'global_id' => $global_id, 'inst_id' => $inst_id, 'name' => $_name,
                           'is_active' => $_active, 'allow_inst_specific' => $_allow_inst);

            // Update or create the Provider record
            if ($current_prov) {      // Update
                $current_prov->update($_prov);
                $prov_updated++;
            } else {                 // Create
                $current_prov = Provider::create($_prov);
                $cur_prov_id = $current_prov->id;
                $prov_created++;
            }

            // Set reports
            $current_prov->reports()->detach();
            $_report_ids = preg_split('/,/', $row[5]);
            if (sizeof($_report_ids) > 0) {
                foreach ($_report_ids as $r) {
                    $r_id = intval(trim($r));
                    if (is_numeric($r_id)) {
                        $report = $master_reports->where('id', '=', $r_id)->first();
                        if ($report) {
                            $current_prov->reports()->attach($r_id);
                        }
                    }
                }
            }
            $seen_provs[] = $cur_prov_id;
        }

        // return the current full list of groups with a success message
        $detail = "";
        $detail .= ($prov_updated > 0) ? $prov_updated . " updated" : "";
        if ($prov_created > 0) {
            $detail .= ($detail != "") ? ", " . $prov_created . " added" : $prov_created . " added";
        }
        if ($prov_skipped > 0) {
            $detail .= ($detail != "") ? ", " . $prov_skipped . " skipped" : $prov_skipped . " skipped";
        }
        $msg  = 'Import successful, Providers : ' . $detail;

        // return response()->json(['result' => true, 'msg' => $msg, 'providers' => $providers]);
        return response()->json(['result' => true, 'msg' => $msg]);
    }

    /**
     * Build array of flags by-report for the UI
     *
     * @param  Collection master_reports
     * @param  Array  $master_ids  (ID's available from the global platform)
     * @param  Array  $conso_enabled  (ID's enabled for the consortium)
     * @param  Array  $inst_enabled  (ID's enabled for the institution)
     * @return Array  $flags
     */
    private function setReportFlags($master_reports, $master_ids, $conso_enabled, $inst_enabled) {
        $flags = array();
        foreach ($master_reports as $mr) {
            $rpt = array('name' => $mr->name, 'status' => 'NA');
            if (in_array($mr->id, $conso_enabled)) {
                $rpt['status'] = 'C';
            } else if (in_array($mr->id, $inst_enabled)) {
                $rpt['status'] = 'I';
            } else if (in_array($mr->id, $master_ids)) {
                $rpt['status'] = 'A';
            }
            $flags[] = $rpt;
        }
        return $flags;
    }

    /**
     * Return an array of booleans for report-state from provider reports columns
     *
     * @param  Collection master_reports
     * @param  Array  $conso_enabled  (ID's)
     * @param  Array  $prov_enabled  (ID's)
     * @return Array  $report-state
     */
    private function reportState($master_reports, $conso_enabled, $prov_enabled) {
        $rpt_state = array();
        foreach ($master_reports as $rpt) {
            $rpt_state[$rpt->name] = array();
            $rpt_state[$rpt->name]['prov_enabled'] = (in_array($rpt->id, $prov_enabled)) ? true : false;
            $rpt_state[$rpt->name]['conso_enabled'] = (in_array($rpt->id, $conso_enabled)) ? true : false;
        }
        return $rpt_state;
    }


    /**
     * Updates report-assignments if a provider is changed, or new connections require it
     *
     * @param  Integer global_id
     * @param  Array  $conso_ids  (consortium report ID's to match on)
     * @param  String  $type : operation to perform
     * @return Integer  deleted : count of providers deleted
     */
    private function updateReports($global_id, $conso_ids, $type) {
        $deleted = 0;
        // Loop through all (non-consortium) providers connected to the global
        $provider_list = Provider::with('reports')->where('global_id',$global_id)->where('inst_id','<>',1)->get();
        foreach ($provider_list as $prov) {

            // Get IDs to add/remove
            $current_ids = $prov->reports->pluck('id')->toArray();
            $changed_ids = ($type=="attach") ? array_diff($conso_ids, $current_ids)
                                             : array_intersect($current_ids, $conso_ids);
            // Add/Remove the report connection(s)
            foreach ($changed_ids as $r) {
                if ($type == "attach") {
                    $prov->reports()->attach($r);
                } else {
                    $prov->reports()->detach($r);
                }
            }
            // No reason to keep the provider definition if there are no reports attached now
            if ($type == "detach" && $prov->reports()->count() == 0) {
                $prov->delete();
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Return record counts of saved report data for a given provider and/or a record-type
     * @param  Provider $global_id
     * @param  Integer $instProvID
     * @param  String  $model   : a single report-name to check, defaults to all
     * @return Boolean
     */
    private function hasSavedReportData($global_id, $instProvID, $model = '')
    {
        $all_models = ['TR' => '\\App\\TitleReport',    'DR' => '\\App\\DatabaseReport',
                       'PR' => '\\App\\PlatformReport', 'IR' => '\\App\\ItemReport'];

        // Get counts and min/max yearmon for each master report
        $models = ($model == '') ? $all_models : array($all_models[$model]);
        if ($instProvID == 0) {
            $where = array(['prov_id', $global_id]);
        } else {
            $where = array(['prov_id', $global_id], ['inst_id', $instProvID]);
        }
        foreach ($models as $model) {
            $result = $model::where($where)->first();
            if ($result) return true;
        }
        return false;
    }
}
