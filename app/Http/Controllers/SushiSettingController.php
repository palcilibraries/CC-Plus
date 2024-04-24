<?php

namespace App\Http\Controllers;

use App\SushiSetting;
use App\Institution;
use App\Provider;
use App\GlobalProvider;
use App\HarvestLog;
use App\InstitutionGroup;
use App\ConnectionField;
use App\Consortium;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\Writer\Xls;

class SushiSettingController extends Controller
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
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        $json = ($request->input('json')) ? true : false;

        // Assign optional inputs to $filters array
        $filters = array('inst' => [], 'group' => 0, 'global_prov' => [], 'inst_prov' => [], 'harv_stat' => []);
        if ($request->input('filters')) {
            $filter_data = json_decode($request->input('filters'));
            foreach ($filter_data as $key => $val) {
                $filters[$key] = $val;
            }
        } else {
            $keys = array_keys($filters);
            foreach ($keys as $key) {
                if ($request->input($key)) {
                    $filters[$key] = $request->input($key);
                }
            }
        }

        // If filtering by group, get the institution IDs for the group
        $group_insts = array();
        if ($filters['group'] != 0) {
            $group = InstitutionGroup::with('institutions:id,name')->find($filters['group']);
            if ($group) {
                $group_insts = $group->institutions->pluck('id')->toArray();
            }
        }

        // Skip querying for records unless we're returning json
        // The vue-component will run a request for initial data once it is mounted
        if ($json) {
            // Pulling Sushi Connections for institution->show() means we need to de-dupe any settings for
            // inst-specific providers that also have a consortium definition
            $limit_prov_ids = [];
            $context = 1;
            if ($request->input('context')) {
                $context = ($request->input('context') > 0) ? $request->input('context') : 1;
            }
            if ($context > 1) {
                $filters['inst'] = array($context);
                $inst_provs = Provider::where('inst_id',$context)->get();
                $inst_prov_ids = $inst_provs->pluck('id')->toArray();
                $inst_global_ids = $inst_provs->pluck('global_id')->toArray();
                $conso_prov_ids = Provider::where('inst_id',1)->whereNotIn('global_id',$inst_global_ids)->pluck('id')->toArray();
                $limit_prov_ids = array_merge($conso_prov_ids,$inst_prov_ids);
            }

            // Get sushi settings
            $data = SushiSetting::with('institution:id,name,is_active','provider','provider.globalProv')
                                  ->when(count($filters['inst']) > 0, function ($qry) use ($filters) {
                                      return $qry->whereIn('inst_id', $filters['inst']);
                                  })
                                  ->when($filters['group'] > 0, function ($qry) use ($group_insts) {
                                      return $qry->whereIn('inst_id', $group_insts);
                                  })
                                  ->when(count($filters['harv_stat']) > 0, function ($qry) use ($filters) {
                                      return $qry->whereIn('status', $filters['harv_stat']);
                                  })
                                  ->when(count($limit_prov_ids) > 0, function ($qry) use ($limit_prov_ids) {
                                      return $qry->whereIn('prov_id', $limit_prov_ids);
                                  })
                                  ->get();

            // Apply provider filter to returned data - it is dependent on context
            if ($context==1 && count($filters['global_prov']) > 0) {
                foreach ($data as $key => $rec) {
                    if ( !in_array($rec->provider->global_id,$filters['global_prov']) ) $data->forget($key);
                }
            } else if ($context>1 && count($filters['inst_prov']) > 0) {
                foreach ($data as $key => $rec) {
                    if ( !in_array($rec->prov_id,$filters['inst_prov']) ) $data->forget($key);
                }
            }

            // Build an array of connection_fields used across all providers (whether connected or not)
            $all_connectors = array();
            $seen_connectors = array();
            $global_connectors = ConnectionField::get();
            $providerIds = $data->unique('prov_id')->pluck('prov_id')->toArray();
            if ( ($context==1 && count($filters['global_prov']) > 0) ||
                 ($context>1 && count($filters['inst_prov']) > 0) ) {
                $includeIds = ($context==1) ? $filters['global_prov'] : $filters['inst_prov'];
                $providerIds = array_unique(array_merge($providerIds,$includeIds));
            }

            $providers = Provider::with('globalProv')->whereIn('id',$providerIds)->orWhere('inst_id', $context)
                                 ->orderBy('name', 'ASC')->get();
            foreach ($providers as $prov) {
                $prov->conso_id = $prov->id;
                // There are only 4... if they're all set, skip checking
                if (sizeof($seen_connectors) < 4) {
                    $connectors = $global_connectors->whereIn('id',$prov->globalProv->connectors);
                    foreach($connectors as $cnx) {
                        if (!in_array($cnx->name,$seen_connectors)) {
                            $all_connectors[] = array('name' => $cnx->name, 'label' => $cnx->label);
                            $seen_connectors[] = $cnx->name;
                        }
                    }
                }
            }

            // Add global connectors and can_edit flag to the provider records
            $settings = $data->map( function ($rec) {
                $rec->can_edit = $rec->canManage();
                if ($rec->provider->globalProv) {
                    $rec->provider->connectors = $rec->provider->globalProv->connectionFields();
                }
                return $rec;
            })->values();
            return response()->json(['settings' => $settings, 'connectors' => $all_connectors], 200);

        // Not returning JSON, the index/vue-component still needs these to setup the page
        } else {
            // Get ALL institutions, regardless of is_active
            $institutions = Institution::where('id', '<>', 1)->orderBy('name', 'ASC')
                                       ->get(['id', 'name', 'is_active']);

            // Get all providers, regardless of is_active
            $providers = Provider::with('globalProv')->orderBy('name', 'ASC')->get();
            $providers = $provider_data->map( function ($rec) {
                $rec->connectors = $rec->globalProv->connectionFields();
                return $rec;
            });
            // Get InstitutionGroups
            $inst_groups = InstitutionGroup::orderBy('name', 'ASC')->get(['name', 'id'])->toArray();

            $cur_instance = Consortium::where('ccp_key', session('ccp_con_key'))->first();
            $conso_name = ($cur_instance) ? $cur_instance->name : "Template";
            return view('sushisettings.index',compact('conso_name','institutions','inst_groups','providers','filters'));
        }

    }

    /**
     * Get and show the requested resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // User must be able to manage the settings
        $setting = SushiSetting::with(['institution', 'provider', 'provider.globalProv'])->findOrFail($id);
        abort_unless($setting->institution->canManage(), 403);

        // Map in the connector details
        $setting->provider->connectors = ConnectionField::whereIn('id',$setting->provider->globalProv->connectors)->get();

        // Set next_harvest date
        if (!$setting->provider->is_active || !$setting->institution->is_active || $setting->status != 'Enabled') {
            $setting['next_harvest'] = null;
        } else {
            $mon = (date("j") < $setting->provider->day_of_month) ? date("n") : date("n")+1;
            $setting['next_harvest'] = date("d-M-Y", mktime(0,0,0,$mon,$setting->provider->day_of_month,date("Y")));
        }

        // Get 10 most recent harvests
        $harvests = HarvestLog::with(
                                  'report:id,name',
                                  'sushiSetting',
                                  'sushiSetting.institution:id,name',
                                  'sushiSetting.provider:id,name'
                              )
                              ->where('sushisettings_id', $id)
                              ->orderBy('updated_at', 'DESC')->limit(10)
                              ->get()->toArray();

        return view('sushisettings.edit', compact('setting', 'harvests'));
    }

    /**
     * Pull settings and return JSON for the requested resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Json
     */
    public function refresh(Request $request)
    {
       // Validate form inputs
        $this->validate($request, ['inst_id' => 'required', 'prov_id' => 'required']);

        // User must be an admin or member-of inst to get the settings
        if (!(auth()->user()->hasRole("Admin") || auth()->user()->inst_id == $request->inst_id)) {
            return response()->json(array('error' => 'Invalid request'));
        }

       // Get sushi URL from provider record
        $provider = Provider::with('globalProv')->where('id', $request->prov_id)->get();

       // Get the settings
        $_where = ['inst_id' => $request->inst_id, 'prov_id' => $request->prov_id];
        $data = SushiSetting::where($_where)->first();
        $settings = (is_null($data)) ? array('count' => 0) : $data->toArray();

       // Return settings and url as json
        $return = array('settings' => $settings, 'url' => $provider->globalProv->server_url_r5);
        return response()->json($return);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);

        $form_data = $request->all();
        $fields = array_except($form_data,array('global_id'));

        if (!auth()->user()->hasAnyRole(['Admin']) && $fields['inst_id'] != auth()->user()->inst_id) {
            return response()->json(['result' => false, 'msg' => 'You can only assign settings for your institution']);
        }
        // if prov_id is missing, try to create a provider record on-the-fly as an institution-specific
        // provider before creating the sushisetting record.
        if (!isset($form_data['prov_id']) && !isset($form_data['global_id'])) {
            return response()->json(['result' => false, 'msg' => 'Provider assignment is required']);
        }
        if (!isset($form_data['prov_id'])) {
            if (!is_null($form_data['global_id'])) {
                $gp = GlobalProvider::where('id',$form_data['global_id'])->first();
                if ($gp) {
                    $provider_data = array('name' => $gp->name, 'global_id' => $gp->id, 'is_active' => $gp->is_active,
                                           'inst_id' => $fields['inst_id'], 'restricted' => 0, 'allow_inst_specific' => 0);
                    $new_provider = Provider::create($provider_data);
                    $fields['prov_id'] = $new_provider->id;
                } else {
                    return response()->json(['result' => false, 'msg' => 'Database error! Cannot find global provider record!']);
                }
            } else {
                return response()->json(['result' => false, 'msg' => 'Global provider value is missing or undefined']);
            }
        }
        // create the new sushi setting record (get existing if already defined)
        $setting = SushiSetting::firstOrCreate($fields);
        $setting->load('institution', 'provider', 'provider.globalProv');
        $setting->provider->connectors = $setting->provider->globalProv->connectionFields();
        // Set string for next_harvest
        if (!$setting->provider->is_active || !$setting->institution->is_active || $setting->status != 'Enabled') {
            $setting['next_harvest'] = null;
        } else {
            $mon = (date("j") < $setting->provider->day_of_month) ? date("n") : date("n")+1;
            $setting['next_harvest'] = date("d-M-Y", mktime(0,0,0,$mon,$setting->provider->day_of_month,date("Y")));
        }
        return response()->json(['result' => true, 'msg' => 'Settings successfully created', 'setting' => $setting]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function update(Request $request)
    {
        $thisUser = auth()->user();
       // Validate form inputs
        $this->validate($request, ['inst_id' => 'required', 'prov_id' => 'required']);
        $input = $request->all();
        $fields = array_except($input,array('global_id'));

       // Ensure user is allowed to change the settings
        $provider = Provider::findOrFail($input['prov_id']);
        $institution = Institution::findOrFail($input['inst_id']);
        if (!$institution->canManage() || (!$thisUser->hasRole('Admin') && $provider->restricted)) {
            return response()->json(['result' => false, 'msg' => 'Invalid request']);
        }

        // Get the settings record
        $setting = SushiSetting::with('institution','provider','provider.globalProv')
                               ->where('inst_id',$input['inst_id'])->where('prov_id',$input['prov_id'])
                               ->first();

        // Create a new setting?
        if (!$setting) {
            $setting = SushiSetting::create($fields);
            $setting->load('institution','provider','provider.globalProv');
        // Not creating, update $setting with user inputs
        } else {
            foreach ($fields as $fld => $val) {
                $setting->$fld = $val;
            }
        }

        // Get required connectors
        $connectors = ConnectionField::whereIn('id',$setting->provider->globalProv->connectors)->pluck('name')->toArray();

        // Check/update connection fields; any null/blank required connectors get updated
        foreach ($connectors as $cnx) {
            if (is_null($setting->$cnx) || $setting->$cnx == '') {
                $setting->$cnx = '-required-';
            }
        }

        // If user requested Disabled status, save as-is
        if ($input['status'] == 'Disabled') {
            $setting->save();
        // Otherwise, update status (based on connectors and prov/inst is_active)( and save)
        } else {
            $setting->resetStatus();
        }

        // Finish setting up the return object
        $setting->provider->connectors = $setting->provider->globalProv->connectionFields();
        // Set string for next_harvest
        if (!$setting->provider->is_active || !$setting->institution->is_active || $setting->status != 'Enabled') {
            $setting['next_harvest'] = null;
        } else {
            $mon = (date("j") < $setting->provider->day_of_month) ? date("n") : date("n")+1;
            $setting['next_harvest'] = date("d-M-Y", mktime(0,0,0,$mon,$setting->provider->day_of_month,date("Y")));
        }

        return response()->json(['result' => true, 'msg' => 'Setting updated successfully', 'setting' => $setting]);
    }

    /**
     * Test the Sushi settings for a given provider-institution.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function test(Request $request)
    {

      // Validate form inputs
      // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
        }
        $provider = Provider::with('globalProv')->findOrFail($input['prov_id']);

        // ASME (there may be others) checks the Agent and returns 403 if it doesn't like what it sees
        $options = [
            'headers' => ['User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0"]
        ];

       // Begin setting up the URI by cleaning/standardizing the server_url_r5 string in the setting
        $_url = rtrim($provider->globalProv->server_url_r5);    // remove trailing whitespace
        $_url = preg_replace('/\/reports\/?$/i', '', $_url);  // take off any methods with any leading slashes
        $_url = preg_replace('/\/status\/?$/i', '', $_url);  //   "   "   "     "      "   "     "        "
        $_url = preg_replace('/\/members\/?$/i', '', $_url); //   "   "   "     "      "   "     "        "
        $_uri = rtrim($_url, '/');                           // remove any remaining trailing slashes

       // If we got extra_args, try to clean it up and strip any leading "&" or "?"
        if (isset($input['extra_args'])) {
          $input['extra_args'] = trim($input['extra_args']);
          $input['extra_args'] = ltrim($input['extra_args'], "&?");
        }

       // Construct and execute the test request for a PR report of last month
        $_uri .= '/reports/pr?';
        $uri_auth = "";

        // If a platform value is set, start with it
        if (!is_null($provider->globalProv->platform_name)) {
            $uri_auth = "platform=" . $provider->globalProv->platform_name;
        }

        $fields = array('customer_id', 'requestor_id', 'api_key', 'extra_args');
        foreach ($fields as $fld) {
          if (isset($input[$fld])) {
              $uri_auth .= ($uri_auth == '') ? "" : "&";
              if ($fld == 'extra_args') {
                  $uri_auth .= urlencode($input['extra_args']);
              } else {
                  $uri_auth .= $fld . '=' . urlencode($input[$fld]);
              }
          }
        }
        $dates = '&begin_date=' . date('Y-m-d', strtotime('first day of previous month')) .
                 '&end_date=' . date('Y-m-d', strtotime('last day of previous month'));
        $request_uri = $_uri . $uri_auth . $dates;

       // Make the request and convert result into JSON
        $rows = array();
        $client = new Client();   //GuzzleHttp\Client
        try {
            $response = $client->request('GET', $request_uri, $options);
            $rows[] = "JSON Response:";
            $rows[] = json_decode($response->getBody(), JSON_PRETTY_PRINT);
            $result = 'Request response successfully received';
        } catch (\Exception $e) {
            $result = 'Request for service status failed!';
            $rows[] = $e->getMessage();
        }

       // return ... something
        $return = array('rows' => $rows, 'result' => $result);
        return response()->json($return);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\SushiSetting  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $setting = SushiSetting::findOrFail($id);
        if (!$setting->institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $setting->delete();
        return response()->json(['result' => true, 'msg' => 'Settings successfully deleted']);
    }
    /**
     * Export sushi settings records from the database.
     *
     * @param  array $inst    // (Optional) - limit to an institutionID, missing or zero means all
     * @param  array $prov    // (Optional) - limit to a providerID, missing or zero means all
     */
    public function export(Request $request)
    {
        // Only Admins and Managers can export institution data
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);

        // Handle and validate inputs
        $filters = null;
        if ($request->filters) {
            $filters = json_decode($request->filters, true);
        } else {
            $filters = array('inst' => [], 'global_prov' => [], 'inst_prov' => [], 'harv_stat' => [], 'group' => 0);
        }
        $context = 1;
        if ($request->input('context')) {
            $context = ($request->input('context') > 0) ? $request->input('context') : 1;
        }
        $export_missing = ($request->export_missing) ? json_decode($request->export_missing, true) : false;

        // Admins have export using group filter, manager can only export their own inst
        $group = null;
        if ($thisUser->hasRole("Admin")) {
            // If group-filter is set, pull the instIDs for the group and set as the "inst" filter
            if ($filters['group'] > 0) {
                $group = InstitutionGroup::with('institutions:id')->find($filters['group']);
                if ($group) {
                    $filters['inst'] = $group->institutions->pluck('id')->toArray();
                }
            }
            $provider_insts = array(1);   //default to consortium providers
        } else {
            $filters['inst'] = array($thisUser->inst_id);
            $provider_insts = array(1,$thisUser->inst_id);
        }

        // Get institution record(s)
        $inst_filters = null;
        if (sizeof($filters['inst']) == 0) {
            $institutions = Institution::get(['id', 'name', 'local_id', 'is_active']);
        } else {
            $institutions = Institution::whereIn('id', $filters['inst'])->get(['id', 'name', 'local_id', 'is_active']);
            $inst_filters = $filters['inst'];
        }
        if (!$institutions) {
            $msg = "Export failed : could not find requested institution(s).";
            return response()->json(['result' => false, 'msg' => $msg]);
        }
        // Set name if only one inst being exported
        $inst_name = ($institutions->count() == 1) ? $institutions[0]->name : "";

        // Get provider record(s)
        $prov_filters = null;
        if (sizeof($filters['global_prov']) == 0 && sizeof($filters['inst_prov']) == 0) {
            $providers = Provider::with('globalProv')->whereIn('inst_id', $provider_insts)->get();
        } else if ($context==1 && sizeof($filters['global_prov']) > 0) {
            $providers = Provider::with('globalProv')->whereIn('global_id', $filters['global_prov'])
                                 ->whereIn('inst_id', $provider_insts)->get();
            // $prov_filters = $filters['global_prov'];
            $prov_filters = $providers->pluck('id')->toArray();
        } else if ($context>1 && sizeof($filters['inst_prov']) > 0) {
            $providers = Provider::with('globalProv')->whereIn('id', $filters['inst_prov'])
                                 ->whereIn('inst_id', $provider_insts)->get();
            $prov_filters = $filters['inst_prov'];
        }
        if (!$providers) {
            $msg = "Export failed : could not find requested provider(s).";
            return response()->json(['result' => false, 'msg' => $msg]);
        }

        // Set status filter
        $status_filters = (count($filters['harv_stat'])>0) ? $filters['harv_stat'] : [];
        $status_name = (count($filters['harv_stat']) == 1) ? $filters['harv_stat'][0] : "";

        // Get all connection fields
        $all_connectors = ConnectionField::get();
        // Set name if only one provider being exported
        $prov_name = ($providers->count() == 1) ? $providers[0]->name : "";

        // Get sushi settings
        $settings = SushiSetting::with('institution:id,name,local_id','provider:id,name')
                      ->when($inst_filters, function ($query, $inst_filters) {
                        return $query->whereIn('inst_id', $inst_filters);
                      })
                      ->when($prov_filters, function ($query, $prov_filters) {
                        return $query->whereIn('prov_id', $prov_filters);
                      })
                      ->when(count($status_filters)>0, function ($qry) use ($status_filters) {
                          return $qry->whereIn('status', $status_filters);
                      })
                      ->get();

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
        $info_sheet->mergeCells('A1:E8');
        $info_sheet->getStyle('A1:E8')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:E8')->getAlignment()->setWrapText(true);
        $top_txt  = "The Settings tab represents a starting place for updating or importing sushi settings.\n";
        $top_txt .= "The table below describes the datatype and order that the import process requires.\n\n";
        $top_txt .= "Any Import rows without an existing (institution) CC+ System ID in column-A or Local ID";
        $top_txt .= " in column-B AND a valid (provider) ID in column-C will be ignored. If values for the other";
        $top_txt .= " columns are optional and are missing, null, or invalid, they will be set to the 'Default'.\n";
        $top_txt .= "The data rows on the 'Settings' tab provide reference values for the Provider-ID and";
        $top_txt .= " Institution-ID columns.\n\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'G' will be ignored. Columns J-K are informational only.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->setCellValue('A10', "NOTES: ");
        $info_sheet->mergeCells('B10:E12');
        $info_sheet->getStyle('A10:B12')->applyFromArray($head_style);
        $info_sheet->getStyle('A10:B12')->getAlignment()->setWrapText(true);
        $precedence_note  = "CC+ System ID values (A) take precedence over Local ID values (B) when processing import";
        $precedence_note .= " records. If a match is found for column-A, column-B is ignored. If no match is found for";
        $precedence_note .= " (A) or (B), the row is ignored. CC+ System ID=1 is reserved for system use.";
        $info_sheet->setCellValue('B10', $precedence_note);
        $info_sheet->mergeCells('B13:E14');
        $info_sheet->getStyle('B13:E14')->applyFromArray($info_style);
        $info_sheet->getStyle('B13:E14')->getAlignment()->setWrapText(true);
        $note_txt  = "When performing imports, be mindful about changing or overwriting existing (system) ID value(s).";
        $note_txt .= "The best approach is to add to, or modify, a full export avoid accidentally overwriting or";
        $note_txt .= " deleting existing settings.";
        $info_sheet->setCellValue('B13', $note_txt);
        $info_sheet->getStyle('A16:E16')->applyFromArray($head_style);
        $info_sheet->setCellValue('A16', 'Column Name');
        $info_sheet->setCellValue('B16', 'Data Type');
        $info_sheet->setCellValue('C16', 'Description');
        $info_sheet->setCellValue('D16', 'Required');
        $info_sheet->setCellValue('E16', 'Default');
        $info_sheet->setCellValue('A17', 'CC+ System ID');
        $info_sheet->setCellValue('B17', 'Integer > 1');
        $info_sheet->setCellValue('C17', 'Institution ID (CC+ System ID)');
        $info_sheet->setCellValue('D17', 'Yes - If LocalID not given');
        $info_sheet->setCellValue('A18', 'LocalID');
        $info_sheet->setCellValue('B18', 'String');
        $info_sheet->setCellValue('C18', 'Local Institution identifier');
        $info_sheet->setCellValue('D18', 'Yes - If CC+ System ID not given');
        $info_sheet->setCellValue('A19', 'Provider ID');
        $info_sheet->setCellValue('B19', 'Integer > 1');
        $info_sheet->setCellValue('C19', 'Unique CC-Plus Provider ID - required');
        $info_sheet->setCellValue('D19', 'Yes');
        $info_sheet->setCellValue('A20', 'Status');
        $info_sheet->setCellValue('B20', 'String');
        $info_sheet->setCellValue('C20', 'Enabled , Disabled, Suspended, or Incomplete');
        $info_sheet->setCellValue('D20', 'No');
        $info_sheet->setCellValue('E20', 'Enabled');
        $info_sheet->setCellValue('A21', 'Customer ID');
        $info_sheet->setCellValue('B21', 'String');
        $info_sheet->setCellValue('C21', 'SUSHI customer ID , provider-specific');
        $info_sheet->setCellValue('D21', 'No');
        $info_sheet->setCellValue('E21', 'NULL');
        $info_sheet->setCellValue('A22', 'Requestor ID');
        $info_sheet->setCellValue('B22', 'String');
        $info_sheet->setCellValue('C22', 'SUSHI requestor ID , provider-specific');
        $info_sheet->setCellValue('D22', 'No');
        $info_sheet->setCellValue('E22', 'NULL');
        $info_sheet->setCellValue('A23', 'API Key');
        $info_sheet->setCellValue('B23', 'String');
        $info_sheet->setCellValue('C23', 'SUSHI API Key , provider-specific');
        $info_sheet->setCellValue('D23', 'No');
        $info_sheet->setCellValue('E23', 'NULL');
        $info_sheet->setCellValue('A24', 'Extra Arguments');
        $info_sheet->setCellValue('B24', 'String');
        $info_sheet->setCellValue('C24', 'Extra Request Arguments, provider-specific');
        $info_sheet->setCellValue('D24', 'No');
        $info_sheet->setCellValue('E24', 'NULL');
        $info_sheet->setCellValue('A25', 'Support Email');
        $info_sheet->setCellValue('B25', 'String');
        $info_sheet->setCellValue('C25', 'Support email address, per-provider');
        $info_sheet->setCellValue('D25', 'No');
        $info_sheet->setCellValue('E25', 'NULL');
        $info_sheet->mergeCells('A27:E29');
        $info_sheet->getStyle('A27:E29')->applyFromArray($head_style);
        $info_sheet->getStyle('A27:E29')->getAlignment()->setWrapText(true);
        $bot_txt = "Status will be set to 'Suspended' for settings where the Institution or Provider is not active.\n";
        $bot_txt .= "Status will be set to 'Incomplete', and the field values marked as missing, if values are not";
        $bot_txt .= " supplied for fields required to connect to the provider (e.g. for customer_id, requestor_id, etc.)";
        $info_sheet->setCellValue('A27', $bot_txt);

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 25; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Process existing settings into an array
        // If we're adding in "missing" settings, keep track of the pairs added
        $data_rows = array();
        $existing = array();
        foreach ($settings as $setting) {
            $data_rows[] = array( 'A' => $setting->inst_id, 'B' => $setting->institution->local_id,
                                  'C' => $setting->prov_id, 'D' => $setting->status, 'E' => $setting->customer_id,
                                  'F' => $setting->requestor_id, 'G' => $setting->api_key, 'H' => $setting->extra_args,
                                  'J' => $setting->institution->name, 'K' => $setting->provider->name );
            if ($export_missing && !in_array(array($setting->inst_id, $setting->prov_id), $existing)) {
                $existing[] = array($setting->inst_id, $setting->prov_id);
            }
        }

        // If we're adding missing settings to the export, get and add output data rows
        // (Only includes settings that are missing for is_active INST <-> PROV pairs)
        if ($export_missing) {
            foreach ($institutions as $inst) {
                // If inst is inactive, skip it
                if (!$inst->is_active) continue;
                foreach ($providers as $prov) {
                    // If prov is inactive or not connected to a globalProv, skip it
                    if (!$prov->is_active || is_null($prov->globalProv)) continue;
                    // If setting is already in data_rows, skip it
                    if (in_array(array($inst->id, $prov->id), $existing)) continue;
                    // Okay, adding the data; get/set connector values
                    $required_connectors = $all_connectors->whereIn('id',$prov->globalProv->connectors)
                                                          ->pluck('name')->toArray();
                    $custID = (in_array('customer_id',$required_connectors)) ? '-required-' : '';
                    $reqID  = (in_array('requestor_id',$required_connectors)) ? '-required-' : '';
                    $apiKey = (in_array('api_key',$required_connectors)) ? '-required-' : '';
                    $extra_args = (in_array('extra_args',$required_connectors)) ? '-required-' : '';
                    $data_rows[] = array( 'A' => $inst->id, 'B' => $inst->local_id, 'C' => $prov->id, 'D' => 'Incomplete',
                                          'E' => $custID, 'F' => $reqID, 'G' => $apiKey, 'H' => $extra_args,
                                          'J' => $inst->name, 'K' => $prov->name );
                }
            }
        }

        // Sort data rows by inst_id, then by prov_id
        $colA  = array_column($data_rows, 'A');
        $colC = array_column($data_rows, 'C');
        array_multisort($colA, SORT_ASC, $colC, SORT_ASC, $data_rows);

        // Setup a new sheet for the data rows
        $inst_sheet = $spreadsheet->createSheet();
        $active_column_cells = "D2:D" . strval(count($data_rows)+1);  // align column-D for the data sheet on center
        $inst_sheet->getStyle($active_column_cells)->applyFromArray($centered_style);
        $inst_sheet->setTitle('Settings');
        $inst_sheet->setCellValue('A1', 'Institution ID (CC+ System ID)');
        $inst_sheet->setCellValue('B1', 'Local Institution Identifier');
        $inst_sheet->setCellValue('C1', 'Provider ID (CC+ System ID)');
        $inst_sheet->setCellValue('D1', 'Status');
        $inst_sheet->setCellValue('E1', 'Customer ID');
        $inst_sheet->setCellValue('F1', 'Requestor ID');
        $inst_sheet->setCellValue('G1', 'API Key');
        $inst_sheet->setCellValue('H1', 'Extra Args');
        $inst_sheet->setCellValue('J1', 'Institution-Name');
        $inst_sheet->setCellValue('K1', 'Provider-Name');

        // Put data rows into the sheet
        $row = 2;
        foreach ($data_rows as $data) {
            foreach ($data as $col => $val) {
                $inst_sheet->setCellValue($col.$row, $val);
            }
            $row++;
        }

        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I','J','K');
        foreach ($columns as $col) {
            $inst_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        $fileName = "CCplus";
        if (!$inst_filters && !$prov_filters && count($status_filters)==0 && is_null($group)) {
            $fileName .= "_" . session('ccp_con_key', '') . "_All";
        } else {
            if (!$inst_filters) {
                $fileName .= "_AllInstitutions";
            } else {
                if ($group) {
                    $fileName .= "_" . preg_replace('/ /', '', $group->name);
                } else {
                    $fileName .= ($inst_name == "") ? "_SomeInstitutions": "_" . preg_replace('/ /', '', $inst_name);
                }
            }
            if (!$prov_filters) {
                $fileName .= "_AllProviders";
            } else {
                $fileName .= ($prov_name == "") ? "_SomeProviders": "_" . preg_replace('/ /', '', $prov_name);
            }
            if ( count($status_filters) > 0) {
                $fileName .= ($status_name == "") ? "_SomeStauses" : "_".$status_name;
            }
        }
        $fileName .= "_SushiSettings.xlsx";

        // redirect output to client
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    /**
     * Import sushi settings from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        $thisUser = auth()->user();

        // Only Admins and Managers can import institution data
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        $is_admin = $thisUser->hasRole('Admin');
        $usersInst = $thisUser->inst_id;

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

        // Setup arrays of "allowable" institution and provider IDs
        $institutions = Institution::get();
        if ($is_admin) {
            $inst_ids = $institutions->pluck('id')->toArray();
            $providers = Provider::with('globalProv')->get();
        } else {
            $inst_ids = array($usersInst);
            $providers = Provider::with('globalProv')->whereIn('inst_id', [ 1, $usersInst ])->get();
        }
        $prov_ids = $providers->pluck('id')->toArray();

        // Get all connection fields
        $all_connectors = ConnectionField::get();

        // Process the input rows
        $updated = 0;
        $deleted = 0;
        $skipped = 0;
        $incomplete = 0;
        foreach ($rows as $rowNum => $row) {
            // Ignore header row and rows with bad/missing/invalid IDs
            if ($rowNum == 0 || !isset($row[0]) && !isset($row[1])) continue;

            // Look for a matching existing institution based on ID or local-ID
            $input_inst_id = (isset($row[0])) ? strval(trim($row[0])) : null;
            $localID = (strlen(trim($row[1])) > 0) ? trim($row[1]) : null;

            // empty/missing/invalid ID and no localID?  skip the row
            if (!$localID && ($input_inst_id == "" || !is_numeric($input_inst_id))) {
                $skipped++;
                continue;
            }

            // Get the settings' provider
            $current_prov = $providers->where('id',$row[2])->first();
            if (!$current_prov) {
                $skipped++;
                continue;
            }

            // Get the current institution
            $current_inst = null;
            if ($localID) {
                $current_inst = $institutions->where("local_id", $localID)->first();
            } else if (!is_null($input_inst_id)) {
                $current_inst = $institutions->where("id", $input_inst_id)->first();
            }

            // If no ID and $localID not found, skip the row
            if (!$current_inst) {
                $skipped++;
                continue;
            }

            // Process only Inst_ids and prov_ids found in the "allowed" arrays created above
            if ( !in_array($current_inst->id, $inst_ids) || !in_array($row[2], $prov_ids) ) {
                $skipped++;
                continue;
            }

            // Put settings (except status) into an array for the update call
            $_args = array('status' => $row[3], 'customer_id' => $row[4], 'requestor_id' => $row[5], 'api_key' => $row[6],
                           'extra_args' => $row[7]);

            // Mark any missing connectors
            $missing_count = 0;
            $required_connectors = $all_connectors->whereIn('id',$current_prov->globalProv->connectors)
                                                  ->pluck('name')->toArray();
            foreach ($required_connectors as $cnx) {
                if ( is_null($_args[$cnx]) || trim($_args[$cnx]) == '' ) {
                    $_args[$cnx] = "-required-";
                }
                if ($_args[$cnx] == "-required-") $missing_count++;
            }

            // Figure out/assign status - default to Enabled
            if ( !in_array($row[3], array('Enabled','Disabled','Suspended','Incomplete')) ||
                 ($missing_count==0 && $current_inst->is_active && $current_prov->is_active) ) {
                $_args['status'] = 'Enabled';
            } else if (!$current_inst->is_active || !$current_prov->is_active) {
              $_args['status'] = 'Suspended';
            }

            // Override status to Incomplete if a required connector is missing & it was about to be set to Enabled
            if ($_args['status'] == 'Enabled' && $missing_count>0) {
                $_args['status'] = 'Incomplete';
                $incomplete++;
            }

            // Update or create the settings
            $current_setting = SushiSetting::updateOrCreate(['inst_id' => $current_inst->id, 'prov_id' => $row[2]], $_args);
            $updated++;
        }

        // Setup return info message
        $msg = "";
        $msg .= ($updated > 0) ? $updated . " added or updated" : "";
        if ($incomplete > 0) {
            $msg .= " (" . $incomplete . " were incomplete)";
        }
        if ($skipped > 0) {
            $msg .= ($msg != "") ? ", " . $skipped . " skipped" : $skipped . " skipped";
        }
        $msg  = 'Sushi settings import completed : ' . $msg;

        // Create a return object with the settings to be returned.
        if (!$is_admin) {
            $settings = SushiSetting::with('institution:id,name','provider:id,name')
                                    ->whereIn('inst_id', [ 1, $usersInst ])->get();
        } else {
            $settings = SushiSetting::with('institution:id,name','provider:id,name')->get();
        }
        return response()->json(['result' => true, 'msg' => $msg, 'settings' => $settings]);
    }
}
