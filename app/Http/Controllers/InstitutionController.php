<?php

namespace App\Http\Controllers;

use App\Institution;
use App\InstitutionGroup;
use App\Provider;
use App\Role;
use App\Report;
use App\SushiSetting;
use App\HarvestLog;
use App\GlobalProvider;
use App\ConnectionField;
use App\Consortium;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\Writer\Xls;

class InstitutionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        $json = ($request->input('json')) ? true : false;

        // Assign optional inputs to $filters array
        $filters = array('stat' => 'ALL', 'groups' => []);
        if ($request->input('filters')) {
            $filter_data = json_decode($request->input('filters'));
            foreach ($filter_data as $key => $val) {
                if ($key == 'stat' && (is_null($val) || $val == '')) continue;
                if ($key == 'groups' && sizeof($val) == 0) continue;
                $filters[$key] = $val;
            }
        } else {
            $keys = array_keys($filters);
            foreach ($keys as $key) {
                if ($request->input($key)) {
                    if ($key == 'stat') {
                        if (is_null($request->input('stat')) || $request->input('stat') == '') continue;
                    } else if ($key == 'groups') {
                        if (sizeof($request->input('groups')) == 0) continue;
                    }
                    $filters[$key] = $request->input($key);
                }
            }
        }
        // Get all groups regardless of JSON or not
        $all_groups = InstitutionGroup::with('institutions:id')->orderBy('name', 'ASC')->get(['id','name']);

        // Skip querying for records unless we're returning json
        // The vue-component will run a request for initial data once it is mounted
        if ($json) {

            // Prep variables for use in querying
            $filter_stat = null;
            if ($filters['stat'] != 'ALL'){
                $filter_stat = ($filters['stat'] == 'Active') ? 1 : 0;
            }

            // Handle filter-by-group by limiting to specific inst_ids
            $limit_to_insts = array();
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
            }

            // Get institution records
            $inst_data = Institution::with('institutionGroups:id,name','sushiSettings')
                                       ->when(!is_null($filter_stat), function ($qry) use ($filter_stat) {
                                           return $qry->where('is_active', $filter_stat);
                                       })
                                       ->when(sizeof($limit_to_insts) > 0 , function ($qry) use ($limit_to_insts) {
                                           return $qry->whereIn('id', $limit_to_insts);
                                       })
                                       ->orderBy('name', 'ASC')
                                       ->get(['id','name','local_id','is_active']);

            // Add group memberships
            $institutions = $inst_data->map( function($inst) {
                $inst->group_string = "";
                $inst->groups = $inst->institutionGroups()->pluck('institution_group_id')->all();
                foreach ($inst->institutionGroups as $group) {
                    $inst->group_string .= ($inst->group_string == "") ? "" : ", ";
                    $inst->group_string .= $group->name;
                }
                $harvest_count = $inst->sushiSettings->whereNotNull('last_harvest')->count();
                $inst->can_delete = ($harvest_count > 0 || $inst->id == 1) ? false : true;
                $inst->status = ($inst->is_active) ? "Active" : "Inactive";
                return $inst;
            });
            return response()->json(['institutions' => $institutions], 200);

        // Not returning JSON, pass only what the index/vue-component needs to initialize the page
        } else {
          $cur_instance = Consortium::where('ccp_key', session('ccp_con_key'))->first();
          $conso_name = ($cur_instance) ? $cur_instance->name : "Template";
          return view('institutions.index', compact('conso_name', 'all_groups', 'filters'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return redirect()->route('institutions.index');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Create failed (403) - Forbidden']);
        }
        $this->validate($request, [
          'name' => 'required',
        ]);
        $input = $request->all();
        // Make sure that local ID is unique if not set null
        $localID = (isset($input['local_id'])) ? trim($input['local_id']) : null;
        if ($localID && strlen($localID) > 0) {
            $existing_inst = Institution::where('local_id',$localID)->first();
            if ($existing_inst) {
                return response()->json(['result' => false,
                                         'msg' => 'Local ID already assigned to another institution.']);
            }
            $input['local_id'] = $localID;
        } else {
            $input['local_id'] = null;
        }
        $institution = Institution::create($input);
        $new_id = $institution->id;

        // Attach groups and build a string of the names
        $_groups = "";
        if (isset($input['institution_groups'])) {
            foreach ($request->input('institution_groups') as $g) {
                $institution->institutionGroups()->attach($g);
                $group = InstitutionGroup::where('id', $g)->first();
                $_groups .= ($group) ? $group->name . ", " : "";
            }
        }

        // Setup a return object that matches what index does (above)
        $data = Institution::where('id', $new_id)->get(['id','name','is_active'])->first()->toArray();
        $data['groups'] = rtrim(trim($_groups), ',');

        return response()->json(['result' => true, 'msg' => 'Institution successfully created',
                                 'institution' => $data]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $thisUser = auth()->user();
        if (!$thisUser->hasRole("Admin")) {
            abort_unless($thisUser->inst_id == $id, 403);
        }

        // Get the institution and sushi settings
        $institution = Institution::with('users', 'users.roles','institutionGroups','institutionGroups.institutions:id,name')
                                  ->findOrFail($id);
        $sushi_settings = SushiSetting::with('provider','provider.globalProv')->where('inst_id',$institution->id)->get();

        // Get most recent harvest and set can_delete flag
        $last_harvest = $sushi_settings->max('last_harvest');
        $institution['can_delete'] = ($id > 1 && is_null($last_harvest)) ? true : false;
        $institution['default_fiscalYr'] = config('ccplus.fiscalYr');

        // Related models we'll be passing
        $all_groups = InstitutionGroup::with('institutions:id,name')->orderBy('name', 'ASC')->get();
        $groupIds = $institution->institutionGroups()->pluck('institutiongroups.id')->toArray();
        $institution['groups'] = $all_groups->whereIn('id',$groupIds)->values()->toArray();
        $all_groups = $all_groups->toArray();

        // Add on Sushi Settings
        $institution['sushiSettings'] = $sushi_settings;

        // Limit roles in UI to current user's max role
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())->orderBy('id', 'DESC')
                         ->get(['name', 'id'])->toArray();
        foreach ($all_roles as $idx => $r) {
            if ($r['name'] == 'Manager') $all_roles[$idx]['name'] = "Local Admin";
            if ($r['name'] == 'Admin') $all_roles[$idx]['name'] = "Consortium Admin";
            if ($r['name'] == 'Viewer') $all_roles[$idx]['name'] = "Viewer";
        }

        // Get all providers this inst might be connected to
        $raw_providers = Provider::whereIn('inst_id', [1,$id])->get();
        $inst_prov_ids  = $raw_providers->where('inst_id',$id)->pluck('id')->toArray();
        $conso_prov_ids = $raw_providers->where('inst_id',1)->pluck('id')->toArray();
        $combined_ids = array_unique(array_merge($inst_prov_ids, $conso_prov_ids));
        $inst_providers = Provider::with('institution:id,name','sushiSettings:id,prov_id,last_harvest','reports:id,name',
                                         'globalProv')->whereIn('id',$combined_ids)->orderBy('name', 'ASC')->get();
        // Get master report definitions
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('name','ASC')->get(['id','name']);

        // Build list of providers, based on globals, that includes extra mapped in consorium-specific data
        $global_providers = GlobalProvider::orderBy('name', 'ASC')->get();


        // $global_providers->map( function($rec) use ($master_reports, $inst_providers, $thisUser, $id) {
        $output_providers = [];
        foreach ($global_providers as $rec) {
            $rec->global_prov = $rec->toArray();
            $rec->connectors = $rec->connectionFields();

            // Set master reports to only the globally available ones and add names
            $_reports = [];
            foreach ($master_reports as $rpt) {
                if (in_array($rpt->id, $rec->master_reports)) {
                    $_reports[] = array('id' => $rpt->id, 'name' => $rpt->name);
                }
            }
            $rec->master_reports = $_reports;

            // Set connected to hold both conso and inst if they're enabled
            $has_inst_connection = false;
            $connected = array();
            for ($i=0; $i<2; $i++) {
                $_inst = ($i==0) ? $id : $i;
                $prov_data = $inst_providers->where('global_id',$rec->id)->where('inst_id',$_inst)->first();
                if ($prov_data) {
                    if ($_inst == $id) $has_inst_connection = true;
                    $_name = ($prov_data->inst_id == 1) ? 'Entire Consortium' : $prov_data->institution->name;
                    $connected[] = array('id' => $prov_data->inst_id, 'name' => $_name);
                }
            }
            $rec->connected = $connected;

            // Set a record for both an inst-specific and consortium-wide provider definition
            // (the U/I will have to hide/set-precedence)
            for ($i=0; $i<2; $i++) {
                $_inst = ($i==0) ? $id : $i;
                // Setup default values for the columns in the U/I
                $rec->conso_id = null;
                $rec->inst_name = null;
                $rec->day_of_month = null;
                $rec->can_delete = false;
                $rec->can_connect = true;   // default is true if global is not connected
                $reports_string = ($rec->master_reports) ?
                                   $this->makeReportString($rec->master_reports, $master_reports) : '';
                $report_state = $this->reportState($rec->master_reports, $master_reports);

                // If no connections, just add the global and skip out
                if ($i==0 && count($connected)==0) {
                    $rec->reports_string = ($reports_string == '') ? "None" : $reports_string;
                    $rec->report_state = $report_state;
                    $output_providers[] = $rec->toArray();
                    break;
                }
                $rec->connection_count = ($_inst==1) ? count($connected) : 1;

                // Get the provider data
                $prov_data = $inst_providers->where('global_id',$rec->id)->where('inst_id',$_inst)->first();
                if ($prov_data) {
                    // For a conso-provider, set can_connect true if inst_specific is aloowed and not already inst-connected
                    $rec->can_connect = ($prov_data->inst_id==1 && $prov_data->allow_inst_specific && !$has_inst_connection) ?
                                         true : false;
                    $rec->conso_id = $prov_data->id;
                    $rec->name = $prov_data->name;
                    $rec->inst_id = $prov_data->inst_id;
                    $rec->inst_name = ($prov_data->inst_id == 1) ? 'Entire Consortium' : $prov_data->institution->name;
                    $rec->is_active = $prov_data->is_active;
                    $rec->active = ($prov_data->is_active) ? 'Active' : 'Inactive';
                    $rec->day_of_month = $prov_data->day_of_month;
                    $rec->last_harvest = $prov_data->sushiSettings->max('last_harvest');
                    $rec->restricted = $prov_data->restricted;
                    $rec->allow_inst_specific = $prov_data->allow_inst_specific;
                    $rec->can_edit = $prov_data->canManage();
                    $rec->can_delete = (is_null($rec->last_harvest) && $prov_data->canManage());
                    if ($prov_data->reports) {
                        $report_ids = $prov_data->reports->pluck('id')->toArray();
                        $reports_string = $this->makeReportString($report_ids, $master_reports);
                        $report_state = $this->reportState($report_ids, $master_reports);
                    }
                    $rec->reports_string = ($reports_string == '') ? "None" : $reports_string;
                    $rec->report_state = $report_state;
                    $output_providers[] = $rec->toArray();
                }
            }
        }
        $all_providers = array_values($output_providers);

        // Pull an array for unset global providers
        $existingIds = $inst_providers->pluck('global_id')->toArray();
        $unset_global = GlobalProvider::whereNotIn('id',$existingIds)->orderBy('name', 'ASC')->get();

        // Get 10 most recent harvests
        $harvests = HarvestLog::with('report:id,name', 'sushiSetting', 'sushiSetting.institution:id,name',
                                     'sushiSetting.provider:id,name')
                              ->join('sushisettings', 'harvestlogs.sushisettings_id', '=', 'sushisettings.id')
                              ->where('sushisettings.inst_id', $id)
                              ->orderBy('harvestlogs.updated_at', 'DESC')->limit(10)
                              ->get('harvestlogs.*')
                              ->toArray();
        return view('institutions.show',
                    compact('institution','all_providers','all_groups','all_roles','harvests','unset_global','master_reports')
                   );
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return redirect()->route('institutions.show', [$id]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $thisUser = auth()->user();
        $institution = Institution::with('institutionGroups')->findOrFail($id);
        if (!$institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $was_active = $institution->is_active;

       // Validate form inputs
        $this->validate($request, ['is_active' => 'required']);
        $input = $request->all();

       // Make sure that local ID is unique if not set null
        if (isset($input['local_id'])) {
            $newID = trim($input['local_id']);
            if ($institution->local_id != $newID) {
                if (strlen($newID) > 0) {
                    $existing_inst = Institution::where('local_id',$newID)->first();
                    if ($existing_inst) {
                        return response()->json(['result' => false,
                                                 'msg' => 'Local ID already assigned to another institution.']);
                    }
                    $input['local_id'] = $newID;
                } else {
                    $input['local_id'] = null;
                }
            }
        }

       // if not admin (user is a local-admin for their institution), only update FTE and notes
        if (!$thisUser->hasRole("Admin")) {
            if ( isset($input['fte']) || isset($input['notes']) ) {
                $limitedFields = array();
                if (isset($input['fte'])) {
                    $limitedFields['fte'] = $input['fte'];
                }
                if (isset($input['notes'])) {
                    $limitedFields['notes'] = $input['notes'];
                }
                $institution->update($limitedFields);
            }

       // Admins update everything from $input
        } else {
            // Update the record and assign groups
            $institution->update($input);
            if (isset($input['institution_groups'])) {
                $institution->institutionGroups()->detach();
                foreach ($request->input('institution_groups') as $g) {
                    $institution->institutionGroups()->attach($g);
                }
            }
            $institution->load('institutionGroups');

            // If is_active is changing, check and update related sushi settings
            $settings = SushiSetting::with('provider','provider.globalProv')->where('inst_id',$institution->id)->get();
            if ($was_active != $institution->is_active) {
                foreach ($settings as $setting) {
                    // Went from Active to Inactive
                    if ($was_active) {
                        $setting->update(['status' => 'Disabled']);
                    // Went from Inactive to Active
                    } else {
                        $setting->resetStatus();
                    }
                }
            }

            // Return updated institution data
            $harvest_count = $settings->whereNotNull('last_harvest')->count();
            $institution->can_delete = ($harvest_count > 0 || $institution->id == 1) ? false : true;
            $institution->sushiSettings = $settings->toArray();
        }

        // Tack on a tring for all the group memberships
        $institution->groups = $institution->institutionGroups()->pluck('institution_group_id')->all();
        $institution->group_string = "";
        foreach ($institution->institutionGroups as $group) {
            $institution->group_string .= ($institution->group_string == "") ? "" : ", ";
            $institution->group_string .= $group->name;
        }

        return response()->json(['result' => true, 'msg' => 'Settings successfully updated', 'institution' => $institution]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $institution = Institution::findOrFail($id);

        try {
            $institution->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }

        return response()->json(['result' => true, 'msg' => 'Institution successfully deleted']);
    }

    /**
     * Export institution records from the database.
     */
    public function export(Request $request)
    {
        // Only Admins can export institution data
        abort_unless(auth()->user()->hasRole('Admin'), 403);

        // Handle and validate inputs
        $filters = null;
        if ($request->filters) {
            $filters = json_decode($request->filters, true);
        } else {
            $filters = array('stat' => [], 'groups' => []);
        }

        // Handle group-filter by building a list of InstIds to be included
        $limit_to_insts = array();
        if ($filters['groups'] > 0) {
            $groups = InstitutionGroup::with('institutions:id')->whereIn('id',$filters['groups'])->get();
            foreach ($groups as $group) {
                $_insts = $group->institutions->pluck('id')->toArray();
                $limit_to_insts =  array_merge(
                      array_intersect($limit_to_insts, $_insts),
                      array_diff($limit_to_insts, $_insts),
                      array_diff($_insts, $limit_to_insts)
                );
            }
        }

        // Handle Status filter
        $status_filter = null;
        if ($filters['stat'] == 'Active') $status_filter = 1;
        if ($filters['stat'] == 'Inactive') $status_filter = 0;

        // Get all institutions
        $institutions = Institution::with('institutionGroups')
                                   ->when($limit_to_insts, function ($query, $limit_to_insts) {
                                       return $query->whereIn('id', $limit_to_insts);
                                   })
                                   ->when($status_filter!=null, function ($query, $status_filter) {
                                       return $query->where('is_active', $status_filter);
                                   })
                                   ->orderBy('name', 'ASC')->get();

        // Setup some styles arrays
        $head_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];
        $info_style = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                           ],
        ];
        $centered_style = [
          'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E7');
        $info_sheet->getStyle('A1:E7')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:E7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Institutions tab represents a starting place for updating or importing settings.\n";
        $top_txt .= "The table below describes the datatype and order that the import process requires.\n\n";
        $top_txt .= "Any Import rows without a CC+ System ID in column-A or a Local ID in column-B will be ignored.\n";
        $top_txt .= "Imports row with a new ID in column-A or a new Local ID in column-B are added as new institutions.\n";
        $top_txt .= "Missing or invalid, but not required, values in the other columns will be set to the 'Default'.\n\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'G' will be ignored.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->setCellValue('A8', "NOTES: ");
        $info_sheet->mergeCells('B8:E9');
        $info_sheet->getStyle('A8:B9')->applyFromArray($head_style);
        $info_sheet->getStyle('A8:B9')->getAlignment()->setWrapText(true);
        $precedence_note  = "CC+ System ID values (A) take precedence over Local ID values (B) when processing import";
        $precedence_note .= " records. If a match is found for column-A, all other values in the row are treated as";
        $precedence_note .= " updates. CC+ System ID=1 is reserved for system use.";
        $info_sheet->setCellValue('B8', $precedence_note);
        $info_sheet->mergeCells('B10:E12');
        $info_sheet->getStyle('B10:E12')->applyFromArray($info_style);
        $info_sheet->getStyle('B10:E12')->getAlignment()->setWrapText(true);
        $note_txt  = "Institution imports cannot be used to delete existing institutions; only additions and";
        $note_txt .= " updates are supported. The recommended approach is to add to, or modify, a previously";
        $note_txt .= " generated full export to ensure that desired end result is achieved.";
        $info_sheet->setCellValue('B10', $note_txt);
        $info_sheet->getStyle('A14:E14')->applyFromArray($head_style);
        $info_sheet->setCellValue('A14', 'Column Name');
        $info_sheet->setCellValue('B14', 'Data Type');
        $info_sheet->setCellValue('C14', 'Description');
        $info_sheet->setCellValue('D14', 'Required');
        $info_sheet->setCellValue('E14', 'Default');
        $info_sheet->setCellValue('A15', 'CC+ System ID');
        $info_sheet->setCellValue('B15', 'Integer > 1');
        $info_sheet->setCellValue('C15', 'Institution ID (CC+ System ID)');
        $info_sheet->setCellValue('D15', 'Yes - If LocalID not given');
        $info_sheet->setCellValue('A16', 'LocalID');
        $info_sheet->setCellValue('B16', 'String');
        $info_sheet->setCellValue('C16', 'Local institution identifier');
        $info_sheet->setCellValue('D16', 'Yes - If CC+ System ID not given');
        $info_sheet->setCellValue('A17', 'Name');
        $info_sheet->setCellValue('B17', 'String');
        $info_sheet->setCellValue('C17', 'Institution Name - required');
        $info_sheet->setCellValue('D17', 'Yes');
        $info_sheet->setCellValue('A18', 'Active');
        $info_sheet->setCellValue('B18', 'String (Y or N)');
        $info_sheet->setCellValue('C18', 'Make the institution active?');
        $info_sheet->setCellValue('D18', 'No');
        $info_sheet->setCellValue('E18', 'Y');
        $info_sheet->setCellValue('A19', 'FTE');
        $info_sheet->setCellValue('B19', 'Integer');
        $info_sheet->setCellValue('C19', 'FTE count for the institution');
        $info_sheet->setCellValue('D19', 'No');
        $info_sheet->setCellValue('E19', 'NULL');
        $info_sheet->setCellValue('A20', 'Group Assignment(s)');
        $info_sheet->setCellValue('B20', 'Comma-separated list of integers');
        $info_sheet->setCellValue('C20', 'Assign Institution to one/more groups');
        $info_sheet->setCellValue('D20', 'No');
        $info_sheet->setCellValue('E20', 'NULL');
        $info_sheet->setCellValue('A21', 'Notes');
        $info_sheet->setCellValue('B21', 'Text-blob');
        $info_sheet->setCellValue('C21', 'Notes or other details');
        $info_sheet->setCellValue('D21', 'No');
        $info_sheet->setCellValue('E21', 'NULL');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 20; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E','F');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the institution data into a new sheet
        $inst_sheet = $spreadsheet->createSheet();
        $inst_sheet->setTitle('Institutions');
        $inst_sheet->setCellValue('A1', 'CC+ System ID');
        $inst_sheet->setCellValue('B1', 'Local ID');
        $inst_sheet->setCellValue('C1', 'Name');
        $inst_sheet->setCellValue('D1', 'Active');
        $inst_sheet->setCellValue('E1', 'FTE');
        $inst_sheet->setCellValue('F1', 'Group IDs');
        $inst_sheet->setCellValue('G1', 'Notes');
        $row = 2;

        // Align column-D for the data sheet on center
        $active_column_cells = "D2:D" . strval($institutions->count()+1);
        $inst_sheet->getStyle($active_column_cells)->applyFromArray($centered_style);

        // Process all institutions, 1-per-row
        foreach ($institutions as $inst) {
            $inst_sheet->getRowDimension($row)->setRowHeight(15);
            $inst_sheet->setCellValue('A' . $row, $inst->id);
            $inst_sheet->setCellValue('B' . $row, $inst->local_id);
            $inst_sheet->setCellValue('C' . $row, $inst->name);
            $_stat = ($inst->is_active) ? "Y" : "N";
            $inst_sheet->setCellValue('D' . $row, $_stat);
            $inst_sheet->setCellValue('E' . $row, $inst->fte);
            // Make a CSV list of the group assignments and put into col-F
            $group_list = "";
            foreach ($inst->institutionGroups as $group) {
                $group_list .= ($group_list=="") ? $group->id : ",$group->id";
            }
            $inst_sheet->setCellValue('F' . $row, $group_list);
            $inst_sheet->setCellValue('G' . $row, $inst->notes);
            $row++;
        }

        // Auto-size the columns (skip notes in 'G')
        $columns = array('A','B','C','D','E','F','G');
        foreach ($columns as $col) {
            $inst_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        $fileName = "CCplus_" . session('ccp_con_key', '') . "_Institutions.xlsx";

        // redirect output to client browser
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    /**
     * Import institutions (including sushi-settings) from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Only Admins can import institution data
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

        // Get existing institution and inst-groups data
        $institutions = Institution::get();
        $groups = InstitutionGroup::get();

        // Process the input rows
        $inst_skipped = 0;
        $inst_updated = 0;
        $inst_created = 0;
        $seen_insts = array();          // keep track of institutions seen while looping
        foreach ($rows as $rowNum => $row) {
            // Ignore header row and rows with bad/missing/invalid IDs
            if ($rowNum == 0 || !isset($row[0]) && !isset($row[1])) continue;

            // Look for a matching existing institution based on ID or local-ID
            $current_inst = null;
            $cur_inst_id = (isset($row[0])) ? strval(trim($row[0])) : null;
            $localID = (strlen(trim($row[1])) > 0) ? trim($row[1]) : null;

            // empty/missing/invalid ID and no localID?  skip the row
            if (!$localID && ($row[0] == "" || !is_numeric($row[0]))) {
                $inst_skipped++;
                continue;
            }

            // Set current_inst based on system-ID (column-0) or local-ID in column-1
            // column-0 takes precedence over column-1
            if ($cur_inst_id) {
                $current_inst = $institutions->where("id", $cur_inst_id)->first();
            }
            if (!$current_inst && $localID) {
                $current_inst = $institutions->where("local_id", $localID)->first();
            }

            // Confirm name and check for conflicts against existing records
            $_name = trim($row[2]);
            if ($current_inst) {      // found existing ID
                // If we already processed this inst, skip doing it again
                if (in_array($current_inst->id, $seen_insts)) {
                    $inst_skipped++;
                    continue;
                }
                // If import-name empty, use current value
                if (strlen($_name) < 1) {
                    $_name = trim($current_inst->name);
                }
                // Override name change if new-name already assigned to another inst
                if ($current_inst->name != $_name) {
                    $existing_inst = $institutions->where("name", $_name)->first();
                    if ($existing_inst) {
                        $_name = trim($current_inst->name);
                    }
                }
                // Override localID change if new-localID already assigned to another inst
                if ($current_inst->local_id != $localID && strlen($localID) > 0) {
                    $existing_inst = $institutions->where("local_id", $localID)->first();
                    if ($existing_inst) {
                        $localID = trim($current_inst->local_id);
                    }
                }
            // If we get here and current_inst is still null, it is PROBABLY a NEW record .. but
            // if an exact-match on institution name is found, USE IT instead of inserting
            } else {           // existing ID not found, try to find by name
                $current_inst = $institutions->where("name", $_name)->first();
                if ($current_inst) {
                    $_name = trim($current_inst->name);
                }
            }

            // Dont store/create anything if name is still empty
            if (strlen($_name) < 1) {
                $inst_skipped++;
                continue;
            }

            // Enforce defaults and put institution data columns into an array
            $_active = ($row[3] == 'N') ? 0 : 1;
            $_fte = ($row[4] == '') ? null : $row[4];
            $_notes = ($row[6] == '') ? null : $row[6];
            $_inst = array('name' => $_name, 'is_active' => $_active,  'local_id' => $localID,
                           'fte' => $_fte, 'notes' => $_notes);

            // Update or create the Institution record
            if (!$current_inst) {      // Create
                // input row had explicit ID? If so, assign it, otherwise, omit from create input array
                if ($row[0] != "" && is_numeric($row[0])) {
                    $_inst['id'] = $row[0];
                }
                $current_inst = Institution::create($_inst);
                $institutions->push($current_inst);
                $inst_created++;
            } else {                            // Update
                $_inst['id'] = $current_inst->id;
                $current_inst->update($_inst);
                $inst_updated++;
            }

            // Clear and reset group membership(s)
            $current_inst->institutionGroups()->detach();
            $_group_list = preg_split('/,/', $row[5]);
            if (sizeof($_group_list) > 0) {
                foreach ($_group_list as $csv_value) {
                    $_id = intval(trim($csv_value));
                    if (is_numeric($_id)) {
                        $group = $groups->where('id',$_id)->first();
                        if ($group) {
                            $current_inst->institutionGroups()->attach($_id);
                        }
                    }
                }
            }

            $seen_insts[] = $current_inst->id;
        }

        // Recreate the institutions list (like index does) to be returned to the caller
        $inst_data = array();
        $institutions = Institution::with('institutionGroups')->orderBy('name', 'ASC')
                                   ->get(['id','name','local_id','is_active']);
        foreach ($institutions as $inst) {
            $_groups = "";
            foreach ($inst->institutionGroups as $group) {
                $_groups .= $group->name . ", ";
            }
            $i_data = $inst->toArray();
            $i_data['groups'] = rtrim(trim($_groups), ',');
            $inst_data[] = $i_data;
        }

        // return the current full list of groups with a success message
        $i_msg = "";
        $i_msg .= ($inst_updated > 0) ? $inst_updated . " updated" : "";
        if ($inst_created > 0) {
            $i_msg .= ($i_msg != "") ? ", " . $inst_created . " added" : $inst_created . " added";
        }
        if ($inst_skipped > 0) {
            $i_msg .= ($i_msg != "") ? ", " . $inst_skipped . " skipped" : $inst_skipped . " skipped";
        }
        $msg  = 'Institution Import successful : ' . $i_msg;

        return response()->json(['result' => true, 'msg' => $msg, 'inst_data' => $inst_data]);
    }

    /**
     * Build string representation of master_reports array
     *
     * @param  Array  $reports
     * @param  Collection  $master_reports
     * @return String
     */
    private function makeReportString($reports, $master_reports) {
        $report_string = '';
        foreach ($master_reports as $mr) {
            if (in_array($mr->id,$reports)) {
                $report_string .= ($report_string == '') ? '' : ', ';
                $report_string .= $mr->name;
            }
        }
        return $report_string;
    }

    /**
     * Return an array of booleans for report-state from provider reports columns
     *
     * @param  Array  $reports
     * @param  Collection  $master_reports
     * @return Array  $report-state
     */
    private function reportState($reports, $master_reports) {
        $rpt_state = array();
        foreach ($master_reports as $rpt) {
            $rpt_state[$rpt->name] = (in_array($rpt->id, $reports)) ? true : false;
        }
        return $rpt_state;
    }

}
