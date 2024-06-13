<?php

namespace App\Http\Controllers;

use App\GlobalProvider;
use App\Consortium;
use App\Report;
use App\Provider;
use App\ConnectionField;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use DB;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class GlobalProviderController extends Controller
{
    private $masterReports;
    private $allConnectors;
    private $instanceData;

    public function __construct()
    {
        $this->middleware(['auth','role:ServerAdmin']);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        global $masterReports, $allConnectors;
        $json = ($request->input('json')) ? true : false;

        // Assign optional inputs to $filters array
        $filters = array('stat' => 'ALL', 'refresh' => 'ALL');
        if ($request->input('filters')) {
            $filter_data = json_decode($request->input('filters'));
            foreach ($filter_data as $key => $val) {
                if (is_null($val) || $val == '') continue;
                $filters[$key] = $val;
            }
        } else {
            $keys = array_keys($filters);
            foreach ($keys as $key) {
                if ($request->input($key)) {
                    if (!is_null($request->input($key)) && $request->input($key) == '') {
                        $filters[$key] = $request->input($key);
                    }
                }
            }
        }

        // Pull master reports and connection fields regardless of JSON flag
        $this->getMasterReports();
        $this->getConnectionFields();
        $all_connectors = $allConnectors->toArray();

        // Skip querying for records unless we're returning json
        // The vue-component will run a request for initial data once it is mounted
        $providers = array();
        if ($json) {

            // Prep variables for use in querying
            $filter_stat = null;
            if ($filters['stat'] != 'ALL') {
                $filter_stat = ($filters['stat'] == 'Active') ? 1 : 0;
            }
            $filter_refresh = null;
            $filter_not_refreshable = ($filters['refresh'] == 'Disabled') ? true : null;
            if ($filters['refresh'] != 'ALL' && $filters['refresh'] != 'Disabled') {
                $filter_refresh = strtolower($filters['refresh']);
            }

            // Get provider records and filter as-needed
            $gp_data = GlobalProvider::when(!is_null($filter_stat), function ($qry) use ($filter_stat) {
                                          return $qry->where('is_active', $filter_stat);
                                       })
                                       ->when($filter_refresh, function ($qry) use ($filter_refresh) {
                                          return $qry->where('refresh_result',$filter_refresh);
                                       })
                                       ->when($filter_not_refreshable, function ($qry) {
                                          return $qry->where('refreshable',0);
                                       })
                                       ->orderBy('name', 'ASC')->get();

            // get all the consortium instances and preserve the current instance database setting
            $instances = Consortium::get();
            $keepDB  = config('database.connections.consodb.database');

            // Build the providers array to pass back to the datatable
            $providers = array();
            foreach ($gp_data as $gp) {
                $provider = $gp->toArray();
                $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";

                // Build arrays of booleans for connection fields and reports for the U/I chackboxes
                $provider['connector_state'] = $this->connectorState($gp->connectors);
                $provider['report_state'] = $this->reportState($gp->master_reports);

                // Walk all instances scan for harvests connected to this provider
                // If any are found, the can_delete flag will be set to false to disable deletion option in the U/I
                $provider['can_delete'] = true;
                $provider['connection_count'] = 0;
                foreach ($instances as $instance) {
                    // Collect details from the instance for this provider
                    $details = $this->instanceDetails($instance->ccp_key, $gp->id);
                    if ($details['harvest_count'] > 0) {
                        $provider['can_delete'] = false;
                    }
                    $provider['connection_count'] += $details['connections'];
                }
                $provider['updated_at'] = (is_null($gp->updated_at)) ? null : date("Y-m-d h:ia", strtotime($gp->updated_at));
                $provider['updated'] = (is_null($gp->updated_at)) ? "" : substr($gp->updated_at,0,10);
                $providers[] = $provider;
            }

            // Restore the database habdle return the data array
            config(['database.connections.consodb.database' => $keepDB]);
            return response()->json(['providers' => $providers], 200);

          // Not returning JSON, pass only what the index/vue-component needs to initialize the page
        } else {
          return view('globalproviders.index', compact('providers', 'masterReports', 'all_connectors', 'filters'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
      global $masterReports, $allConnectors;

      // Validate form inputs
      $this->validate($request, [ 'name' => 'required', 'is_active' => 'required', 'server_url_r5' => 'required' ]);
      $input = $request->all();

      // Create new global provider
      $provider = new GlobalProvider;
      $provider->name = $input['name'];
      $provider->is_active = $input['is_active'];
      $provider->refreshable = $input['refreshable'];
      $provider->refresh_result = null;
      $provider->server_url_r5 = $input['server_url_r5'];
      $provider->platform_parm = $input['platform_parm'];

      // Turn array of connection checkboxes into an array of IDs
      $connectors = array();
      $this->getConnectionFields();
      foreach ($allConnectors as $cnx) {
          if (!isset($input['connector_state'][$cnx->name])) continue;
          if ($input['connector_state'][$cnx->name]) {
              $connectors[] = $cnx->id;
          }
      }
      $provider->connectors = $connectors;

      // Turn array of report checkboxes into an array of IDs
      $master_reports = array();
      // $reports_string = "";
      if (isset($input['report_state'])) {
          $this->getMasterReports();
          foreach ($masterReports as $rpt) {
            if (!isset($input['report_state'][$rpt->name])) continue;
            if ($input['report_state'][$rpt->name]) {
                $master_reports[] = $rpt->id;
            }
          }
      }
      $provider->master_reports = $master_reports;
      $provider->save();

      // Build return object to match what index() rows
      $provider['can_delete'] = true;
      $provider['connection_count'] = 0;
      $provider['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $provider['connector_state'] = $input['connector_state'];
      $provider['report_state'] = (isset($input['report_state'])) ? $input['report_state'] : array();

      return response()->json(['result' => true, 'msg' => 'Provider successfully created',
                               'provider' => $provider]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\GlobalProvider  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
      global $masterReports, $allConnectors;

      $provider = GlobalProvider::findOrFail($id);
      $orig_name = $provider->name;
      $orig_isActive = $provider->is_active;

      // Validate form inputs
      $this->validate($request, [ 'is_active' => 'required' ]);
      $input = $request->all();
      $isActive = ($input['is_active']) ? 1 : 0;
      $provider->is_active = $isActive;
      if (isset($input['refreshable'])) {
          $provider->refreshable = ($input['refreshable']) ? 1 : 0;
          if ($provider->refreshable == 0) {
              $provider->refresh_result = null;
          }
      }

      // Pull all connection fields and master reports
      $all_connectors = ConnectionField::get();
      $this->getMasterReports();

      // Gather IDs of reports that have been removed. We'll detach these from the consortia instance tables.
      // NOTE:: adding to the global master list doesn't automatically enable new reports in the instance tables.
      $dropped_reports = array();
      $original_reports = $provider->master_reports;
      if (isset($input['report_state'])) {
          foreach ($original_reports as $mr) {
              $_master = $masterReports->where('id', $mr)->first();
              if (!$_master) continue;
              if (!isset($input['report_state'][$_master->name])) continue;
              if (!$input['report_state'][$_master->name]) {
                  $dropped_reports[] = $mr;
              }
          }
      }

      // Update the record in the global table
      $input_name = (isset($input['name'])) ? $input['name'] : $orig_name;
      if (isset($input['name'])) {
          $provider->name = $input_name;
      }
      if (isset($input['server_url_r5'])) {
          $provider->server_url_r5 = (isset($input['server_url_r5'])) ? $input['server_url_r5'] : null;
      }

      // Turn array of connection checkboxes into an array of IDs
      $new_connectors = array();
      $connectors_changed = false;
      if (isset($input['connector_state'])) {
          $extraArgs = false;
          foreach ($all_connectors as $cnx) {
              if (!isset($input['connector_state'][$cnx->name])) continue;
              if ($input['connector_state'][$cnx->name]) {
                  if ($cnx->name == 'extra_args') $extraArgs = true;
                  $new_connectors[] = $cnx->id;
              }
          }
          $connectors_changed = ($provider->connectors != $new_connectors);
          $provider->connectors = $new_connectors;
      }
      $provider->platform_parm = (isset($input['platform_parm'])) ? $input['platform_parm'] : null;
      $provider->content_provider = (isset($input['content_provider'])) ? $input['content_provider'] : null;

      // Turn array of report checkboxes into an array of IDs
      if (isset($input['report_state'])) {
          $master_reports = array();
          foreach ($masterReports as $rpt) {
            if (!isset($input['report_state'][$rpt->name])) continue;
            if ($input['report_state'][$rpt->name]) {
                $master_reports[] = $rpt->id;
            }
          }
          $provider->master_reports = $master_reports;
      }
      $provider->save();
      $provider['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $provider['connector_state'] = (isset($input['connector_state'])) ? $input['connector_state'] : array();
      // $provider['reports_string'] = ($reports_string == "") ? 'None' : $reports_string;
      $provider['report_state'] = (isset($input['report_state'])) ? $input['report_state'] : array();

      // Set connection field labels in an array for the datatable display
      $provider['connector_state'] = array();
      if (isset($input['connector_state'])) {
          $provider['connector_state'] = $input['connector_state'];
      }

      // Get connector fields
      $fields = $all_connectors->whereIn('id',$provider->connectors)->pluck('name')->toArray();
      $unused_fields = $all_connectors->whereNotIn('id',$provider->connectors)->pluck('name')->toArray();

      // If changes implicate consortia-provider settings, Loop through all consortia instances
      if ($input_name != $orig_name || $isActive!=$orig_isActive || count($dropped_reports)>0 || $connectors_changed) {
          $instances = Consortium::get();
          $keepDB  = config('database.connections.consodb.database');
          $prov_updates = array('name' => $input_name);
          // only update is_active if the global state is changing (otherwise leave consortium state as-is)
          if ($isActive != $orig_isActive) {
              $prov_updates['is_active'] = $isActive;
          }
          foreach ($instances as $instance) {
              // switch the database connection
              config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
              try {
                  DB::reconnect('consodb');
              } catch (\Exception $e) {
                  return response()->json(['result' => 'Error connecting to database for the ' . $instance->name . ' instance!']);
              }

              // Update the providers table
              $con_prov = Provider::where('global_id',$id)->first();
              if (!$con_prov) continue;
              $was_active = $con_prov->is_active;
              if ($input_name!=$orig_name || $isActive!=$orig_isActive) {
                  $con_prov->update($prov_updates);
              }

              // Detach any reports that are no longer available
              foreach ($dropped_reports as $rpt_id) {
                  $con_prov->reports()->detach($rpt_id);
              }

              // Check, and possibly update, status for related sushi settings (skip disabled settings)
              $con_prov->load('sushiSettings','sushiSettings.institution');
              $settings = $con_prov->sushiSettings->where('status','<>','Disabled');
              foreach ($settings as $setting) {
                  // If required connectors all have values, check to see if sushi setting status needs updating
                  $setting_updates = array();
                  if ($setting->isComplete()) {
                      // Setting is Enabled, provider going inactive, suspend it
                      if ($setting->status == 'Enabled' && $was_active && !$con_prov->is_active ) {
                          $setting_updates['status'] = 'Suspended';
                      }
                      // Setting is Suspended, provider going active with active institution, enable it
                      if ($setting->status == 'Suspended' && !$was_active && $con_prov->is_active &&
                          $setting->institution->is_active) {
                          $setting_updates['status'] = 'Enabled';
                      }
                      // Setting status is Incomplete, provider is active and institution is active, enable it
                      if ($setting->status == 'Incomplete') {
                          $setting_updates['status'] = ($con_prov->is_active && $setting->institution->is_active) ?
                                                        'Enabled' : 'Suspended';
                      }
                      // Setting is Complete; clear '-required--' labels on unused fields
                      foreach ($unused_fields as $uf) {
                          if ($setting->$uf == '-required-') {
                              $setting_updates[$uf]= '';
                          }
                      }
                  // If required conenctors are missing value(s), mark them and update setting status tp Incomplete
                  } else {
                      $setting_updates['status'] = 'Incomplete';
                      foreach ($fields as $fld) {
                          if ($setting->$fld == null || $setting->$fld == '') {
                              $setting_updates[$fld] = "-required-";
                          }
                      }
                  }
                  if (count($setting_updates) > 0) {
                      $setting->update($setting_updates);
                  }
              }
          }
          // Restore the database habdle
          config(['database.connections.consodb.database' => $keepDB]);
      }

      return response()->json(['result' => true, 'msg' => 'Global Provider settings successfully updated',
                               'provider' => $provider]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\GlobalProvider  $id
     */
    public function destroy($id)
    {
        $globalProvider = GlobalProvider::findOrFail($id);

        // Loop through all consortia instances and delete from the providers tables
        $instances = Consortium::get();
        $keepDB  = config('database.connections.consodb.database');
        foreach ($instances as $instance) {
            // switch the database connection
            config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
            try {
                DB::reconnect('consodb');
            } catch (\Exception $e) {
                return response()->json(['result' => 'Error connecting to database for the ' . $instance->name . ' instance!']);
            }

            try {
                Provider::where('global_id',$id)->delete();
            } catch (\Exception $ex) {
                return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
            }
        }
        // Restore the database habdle
        config(['database.connections.consodb.database' => $keepDB]);

        // Delete the global entry
        try {
            $globalProvider->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }

        return response()->json(['result' => true, 'msg' => 'Global Provider successfully deleted']);
    }

    /**
     * Pull and return a fresh copy of the registry data for a given provider
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function registryRefresh(Request $request)
    {
      global $masterReports, $allConnectors;

      // Validate form inputs
      $this->validate($request, [ 'id' => 'required' ]);
      $input = $request->all();
      $provider = GlobalProvider::where('id', $input['id'])->first();
      if (!$provider) {
          return response()->json(['result' => false, 'msg' => "Global Provider Not Found!"]);
      }
      if (is_null($provider->registry_id) || $provider->registry_id == '') {
          return response()->json(['result' => false, 'msg' => "COUNTER Registry ID undefined!"]);
      }
      if (!$provider->refreshable) {
          return response()->json(['result' => false, 'msg' => "Registry refresh is disallowed for " . $provider->name . " !"]);
      }

      // Get master reports and connection_fields
      $this->getMasterReports();
      $this->getConnectionFields();

      // Map a static array to conect what the COUNTER API sends back to conbnection_fields
      $api_connectors = array('customer_id_info'      => array('field' => 'customer_id', 'id' => null, 'label' => ''),
                              'requestor_id_required' => array('field' => 'requestor_id', 'id' => null, 'label' => ''),
                              'api_key_required'      => array('field' => 'api_key', 'id' => null, 'label' => '')
                             );
      foreach ($api_connectors as $key => $cnx) {
          $fld = $allConnectors->where('name', $cnx['field'])->first();
          if (!$fld) continue;
          $api_connectors[$key]['id'] = $fld->id;
          $api_connectors[$key]['label'] = $fld->label;
      }

      // Setup the client request for the registry JSON
      $client = new Client();   //GuzzleHttp\Client
      $options = [
          'headers' => ['User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0"]
      ];
      $registry_url = "https://registry.projectcounter.org/api/v1/platform/" . $provider->registry_id . "/?format=json";
      // Make the request
      try {
          $result = $client->request('GET', $registry_url, $options);
      } catch (\Exception $e) {
          return response()->json(['result' => false, 'msg' => "API request Failed: " . $e->getMessage()]);
      }
      // Get JSON from the response and do basic error checks
      $json = json_decode($result->getBody());
      if (json_last_error() !== JSON_ERROR_NONE) {
          return response()->json(['result' => false, 'msg' => "Error decoding JSON returned by registry!"]);
      }
      if (!is_object($json)) {
          return response()->json(['result' => false, 'msg' => "Error getting registry details - invalid datatype received!"]);
      }
      // Setup provider data to be updated and returned
      $return_data = array();
      $return_data['registry_id'] = $json->id;
      $return_data['name'] = $json->name;
      $return_data['content_provider'] = $json->content_provider_name;
      $return_data['abbrev'] = $json->abbrev;
      $return_data['platform'] = $provider->platform; // preserve unchanged

      // Get reports available
      $available = $masterReports->whereIn('name',array_column($json->reports,'report_id'));
      $reportIds = $available->pluck('id')->toArray();
      $return_data['master_reports'] = $reportIds;

      // Get connection fields from JSON sushi_services (for now, assumes customer_id is always required)
      $services = $json->sushi_services[0];
      $field_labels = array();
      foreach ($api_connectors as $key => $cnx) {
          if ($key == 'customer_id_info' || $services->{$key}) {
              $connectors[] = $cnx['id'];
              // $field_labels[] = $cnx['label'];
          }
      }
      // The registry API doesn't know about CC+ extra_args. If set in the original Global, preserve it
      foreach ($provider->connectionFields() as $cf) {
          if ($cf['name'] == 'extra_args') {
              $connectors[] = $cf['id'];
              // $field_labels[] = $cf['label'];
              break;
          }
      }

      $return_data['connectors'] = $connectors;
      // $return_data['connection_fields'] = $field_labels;
      $return_data['server_url_r5'] = $services->url;
      $return_data['notifications_url'] = $services->notifications_url;

      // Update the global provider record
      $provider->update($return_data);

      // Add more return data for the U/I
      $return_data['report_state'] = $this->reportState($reportIds);
      $reportNames = $available->pluck('name')->toArray();
      $return_data['connection_count'] = count($connectors);
      $return_data['connector_state'] = $this->connectorState($connectors);

      return response()->json(['result' => true, 'prov' => $return_data]);
    }

    /**
     * Export provider records from the database.
     *
     * @param  string  $type    // 'xls' or 'xlsx'
     * @return \Illuminate\Http\Response
     */
    public function export($type)
    {
        global $masterReports, $allConnectors;

        $thisUser = auth()->user();

       // Admins get all providers
        $global_providers = GlobalProvider::orderBy('name', 'ASC')->get();

        // get connection fields and master reports
        $this->getMasterReports();
        $this->getConnectionFields();

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
        $bold_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,],
        ];
        $centered_style = [
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,],
        ];
        $outline_style = [
            'borders' => [ 'outline' => [ 'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,],],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        for ($row=1; $row<7; $row++) {
            $info_sheet->mergeCells("A" . $row . ":H" . $row);
        }
        $info_sheet->setCellValue('A2',"  * The Platforms tab represents a starting place for updating or importing settings.");

        $richText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $approach = $richText->createTextRun("  * The recommended approach is to add to, or modify, a previously run full export.");
        $approach->getFont()
                 ->setColor( new \PhpOffice\PhpSpreadsheet\Style\Color( \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED ) );
        $info_sheet->setCellValue('A3', $richText);
        $_txt = "  * Only additions and updates are supported. Import cannot be used to delete existing platforms.";
        $info_sheet->setCellValue('A4', $_txt);
        $richText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $richText->createText("  * Once updates to the Platforms tab are complete, save the sheet as a ");
        $saving = $richText->createTextRun("CSV UTF-8");
        $saving->getFont()->setBold(true);
        $richText->createText(" file and import it into CC-Plus.");
        $info_sheet->setCellValue('A5', $richText);
        $info_sheet->getStyle('A1:H6')->applyFromArray($outline_style);
        for ($row=7; $row<13; $row++) {
            $info_sheet->mergeCells("A" . $row . ":H" . $row);
        }
        $info_sheet->setCellValue( 'A8',"  * The table below describes the data type and order that the import expects.");
        $info_sheet->setCellValue( 'A9',"  * Any Import rows without an ID value in column A and a name in column B are ignored.");
        $info_sheet->setCellValue('A10',"  * If values are missing or invalid for columns D-M, they will be set to the default.");
        $info_sheet->setCellValue('A11',"  * Any header row or columns beyond M will be ignored.");
        $info_sheet->getStyle('A7:H12')->applyFromArray($outline_style);
        $info_sheet->getStyle('A14:H14')->applyFromArray($head_style);
        $info_sheet->setCellValue('A14', 'COL');
        $info_sheet->setCellValue('B14', 'Column Name');
        $info_sheet->setCellValue('C14', 'Required');
        $info_sheet->setCellValue('D14', 'Data Type');
        $info_sheet->setCellValue('E14', 'Valid Values');
        $info_sheet->setCellValue('F14', 'Description');
        $info_sheet->setCellValue('G14', 'Default if empty');
        $info_sheet->setCellValue('H14', 'Notes');
        $info_sheet->getStyle('A15:A27')->applyFromArray($centered_style);
        $info_sheet->getStyle('C15:C17')->applyFromArray($bold_style);
        $info_sheet->getStyle('E15:E27')->applyFromArray($centered_style);
        $info_sheet->getStyle('F15:F17')->applyFromArray($head_style);
        $info_sheet->getStyle('G15:G27')->applyFromArray($centered_style);
        $info_sheet->setCellValue('A15', 'A');
        $info_sheet->setCellValue('B15', 'Id');
        $info_sheet->setCellValue('C15', 'Yes');
        $info_sheet->setCellValue('D15', 'Integer');
        $info_sheet->setCellValue('E15', '');
        $info_sheet->setCellValue('F15', 'Unique CC-Plus Platform ID');
        $info_sheet->setCellValue('G15', '');
        $info_sheet->setCellValue('H15', 'Increments if empty');
        $info_sheet->setCellValue('A16', 'B');
        $info_sheet->setCellValue('B16', 'Name');
        $info_sheet->setCellValue('C16', 'Yes');
        $info_sheet->setCellValue('D16', 'String');
        $info_sheet->setCellValue('E16', '');
        $info_sheet->setCellValue('F16', 'Platform name');
        $info_sheet->setCellValue('G16', '');
        $info_sheet->setCellValue('H16', '');
        $info_sheet->setCellValue('A17', 'C');
        $info_sheet->setCellValue('B17', 'Server URL');
        $info_sheet->setCellValue('C17', 'Yes');
        $info_sheet->setCellValue('D17', 'String');
        $info_sheet->setCellValue('E17', 'Valid URL');
        $info_sheet->setCellValue('F17', 'URL for Platform SUSHI service');
        $info_sheet->setCellValue('G17', '');
        $info_sheet->setCellValue('H17', '');
        $info_sheet->setCellValue('A18', 'D');
        $info_sheet->setCellValue('B18', 'Active');
        $info_sheet->setCellValue('C18', '');
        $info_sheet->setCellValue('D18', 'String');
        $info_sheet->setCellValue('E18', 'Y or N');
        $info_sheet->setCellValue('F18', 'Make the platform active?');
        $info_sheet->setCellValue('G18', 'Y');
        $info_sheet->setCellValue('H18', '');
        $info_sheet->setCellValue('A19', 'E');
        $info_sheet->setCellValue('B19', 'DR');
        $info_sheet->setCellValue('C19', '');
        $info_sheet->setCellValue('D19', 'String');
        $info_sheet->setCellValue('E19', 'Y or N');
        $info_sheet->setCellValue('F19', 'Platform supplies DR reports?');
        $info_sheet->setCellValue('G19', 'N');
        $info_sheet->setCellValue('H19', '');
        $info_sheet->setCellValue('A20', 'F');
        $info_sheet->setCellValue('B20', 'IR');
        $info_sheet->setCellValue('C20', '');
        $info_sheet->setCellValue('D20', 'String');
        $info_sheet->setCellValue('E20', 'Y or N');
        $info_sheet->setCellValue('F20', 'Platform supplies IR reports?');
        $info_sheet->setCellValue('G20', 'N');
        $info_sheet->setCellValue('H20', '');
        $info_sheet->setCellValue('A21', 'G');
        $info_sheet->setCellValue('B21', 'PR');
        $info_sheet->setCellValue('C21', '');
        $info_sheet->setCellValue('D21', 'String');
        $info_sheet->setCellValue('E21', 'Y or N');
        $info_sheet->setCellValue('F21', 'Platform supplies PR reports?');
        $info_sheet->setCellValue('G21', 'Y');
        $info_sheet->setCellValue('H21', '');
        $info_sheet->setCellValue('A22', 'H');
        $info_sheet->setCellValue('B22', 'TR');
        $info_sheet->setCellValue('C22', '');
        $info_sheet->setCellValue('D22', 'String');
        $info_sheet->setCellValue('E22', 'Y or N');
        $info_sheet->setCellValue('F22', 'Platform supplies TR reports?');
        $info_sheet->setCellValue('G22', 'N');
        $info_sheet->setCellValue('H22', '');
        $info_sheet->setCellValue('A23', 'I');
        $info_sheet->setCellValue('B23', 'Customer ID');
        $info_sheet->setCellValue('C23', '');
        $info_sheet->setCellValue('D23', 'String');
        $info_sheet->setCellValue('E23', 'Y or N');
        $info_sheet->setCellValue('F23', 'Customer ID is required for SUSHI connections');
        $info_sheet->setCellValue('G23', 'Y');
        $info_sheet->setCellValue('H23', '');
        $info_sheet->setCellValue('A24', 'J');
        $info_sheet->setCellValue('B24', 'Requestor ID');
        $info_sheet->setCellValue('C24', '');
        $info_sheet->setCellValue('D24', 'String');
        $info_sheet->setCellValue('E24', 'Y or N');
        $info_sheet->setCellValue('F24', 'Requestor ID is required for SUSHI connections');
        $info_sheet->setCellValue('G24', 'N');
        $info_sheet->setCellValue('H24', '');
        $info_sheet->setCellValue('A25', 'K');
        $info_sheet->setCellValue('B25', 'API Key');
        $info_sheet->setCellValue('C25', '');
        $info_sheet->setCellValue('D25', 'String');
        $info_sheet->setCellValue('E25', 'Y or N');
        $info_sheet->setCellValue('F25', 'API Key is required for SUSHI connections');
        $info_sheet->setCellValue('G25', 'N');
        $info_sheet->setCellValue('H25', '');
        $info_sheet->setCellValue('A26', 'L');
        $info_sheet->setCellValue('B26', 'Extra Arguments');
        $info_sheet->setCellValue('C26', '');
        $info_sheet->setCellValue('D26', 'String');
        $info_sheet->setCellValue('E26', 'Y or N');
        $info_sheet->setCellValue('F26', 'Extra Arguments are required for SUSHI connections');
        $info_sheet->setCellValue('G26', 'N');
        $info_sheet->setCellValue('H26', '');
        $info_sheet->setCellValue('A27', 'M');
        $info_sheet->setCellValue('B27', 'Extra Args Pattern');
        $info_sheet->setCellValue('C27', '');
        $info_sheet->setCellValue('D27', 'String');
        $info_sheet->setCellValue('E27', '');
        $info_sheet->setCellValue('F27', 'ExtraArgs Pattern (e.g. &extra_cred=value&some_parm=value)');
        $info_sheet->setCellValue('G27', 'NULL');
        $info_sheet->setCellValue('H27', 'ignored if Extra Args is "N"');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 28; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E','F','G','H');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }
        // setup arrays with the report and connectors mapped to their column ids
        $rpt_col = array('DR' => 'E', 'IR' => 'F', 'PR' => 'G', 'TR' => 'H');
        $cnx_col = array('customer_id' => 'I', 'requestor_id' => 'J', 'api_key' => 'K', 'extra_args' => 'L');

        // Load the provider data into a new sheet
        $providers_sheet = $spreadsheet->createSheet();
        $providers_sheet->setTitle('Platforms');
        $providers_sheet->setCellValue('A1', 'Id');
        $providers_sheet->setCellValue('B1', 'Name');
        $providers_sheet->setCellValue('C1', 'Active');
        $providers_sheet->setCellValue('D1', 'Server URL');
        $providers_sheet->setCellValue('E1', 'DR-Reports');
        $providers_sheet->setCellValue('F1', 'IR-Reports');
        $providers_sheet->setCellValue('G1', 'PR-Reports');
        $providers_sheet->setCellValue('H1', 'TR-Reports');
        $providers_sheet->setCellValue('I1', 'Customer-ID');
        $providers_sheet->setCellValue('J1', 'Requestor-ID');
        $providers_sheet->setCellValue('K1', 'API-Key');
        $providers_sheet->setCellValue('L1', 'Extra-Args');
        $providers_sheet->setCellValue('M1', 'Platform Name');
        $row = 2;
        foreach ($global_providers as $provider) {
            $providers_sheet->getRowDimension($row)->setRowHeight(15);
            $providers_sheet->setCellValue('A' . $row, $provider->id);
            $providers_sheet->setCellValue('B' . $row, $provider->name);
            $_stat = ($provider->is_active) ? "Y" : "N";
            $providers_sheet->setCellValue('C' . $row, $_stat);
            $providers_sheet->setCellValue('D' . $row, $provider->server_url_r5);
            foreach ($masterReports as $master) {
                $value = (in_array($master->id, $provider->master_reports)) ? 'Y' : 'N';
                $providers_sheet->setCellValue($rpt_col[$master->name] . $row, $value);
            }
            foreach ($allConnectors as $field) {
                $value = (in_array($field->id, $provider->connectors)) ? 'Y' : 'N';
                $providers_sheet->setCellValue($cnx_col[$field->name] . $row, $value);
            }
            $providers_sheet->setCellValue('M' . $row, $provider->platform_parm);
            $row++;
        }

        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I','J','K','L','M');
        foreach ($columns as $col) {
            $providers_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // redirect output to client browser
        $fileName = "CCplus_Global_Platforms." . $type;
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
        global $masterReports, $allConnectors;

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

        // Get existing providers, connection fields and master_reports
        $global_providers = GlobalProvider::orderBy('name', 'ASC')->get();
        $this->getMasterReports();
        $this->getConnectionFields();

        // Setup Mapping for report-COLUMN indeces to master_report ID's (ID => COL)
        $rpt_columns = array( 2 => 4, 4 => 5, 3 => 6, 1 => 7);
        $col_defaults = array( 3 => 'N', 4 => 'N', 5 => 'N', 6 => 'Y', 7 => 'N', 8 => 'Y', 9 => 'N', 10 => 'N', 11 => 'N');

        // Process the input rows
        $cur_prov_id = 0;
        $prov_skipped = 0;
        $prov_updated = 0;
        $prov_created = 0;
        $seen_provs = array();          // track providers already processed while looping
        foreach ($rows as $row) {
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0]) || !isset($row[1])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0]) || trim($row[1]) == "" || sizeof($row) < 4) {
                continue;
            }
            $cur_prov_id = intval($row[0]);
            if (in_array($cur_prov_id, $seen_provs)) {
              continue;
            }

            // Update/Add the provider data/settings
            // Check ID and name columns for silliness or errors
            $_name = trim($row[1]);
            $current_prov = $global_providers->where("id", $cur_prov_id)->first();
            if ($current_prov) {      // found existing ID
                if (strlen($_name) < 1) {       // If import-name empty, use current value
                    $_name = trim($current_prov->name);
                } else {                        // trap changing a name to a name that already exists
                    $existing_prov = $global_providers->where("name", $_name)->first();
                    if ($existing_prov) {
                        $_name = trim($current_prov->name);     // override, use current - no change
                    }
                }
            } else {        // existing ID not found, try to find by name
                $current_prov = $global_providers->where("name", $_name)->first();
                if ($current_prov) {
                    $_name = trim($current_prov->name);
                }
            }

            // Name and URL both required - skip if either is empty
            if (strlen($_name) < 1 || strlen(trim($row[2])) < 1) {
                $prov_skipped++;
                continue;
            }

            // Enforce defaults
            $seen_provs[] = $cur_prov_id;
            foreach ($col_defaults as $_col => $_val) {
                if (strlen(trim($row[$_col])) < 1) {
                    $row[$_col]  = $_val;
                }
            }
            $_active = ($row[3] == 'N') ? 0 : 1;

            // Setup provider data as an array
            $_prov = array('id' => $cur_prov_id, 'name' => $_name, 'is_active' => $_active, 'server_url_r5' => $row[2]);

            // Add reports to the array ($rpt_columns defined above)
            $reports = array();
            foreach ($rpt_columns as $id => $col) {
                if ($row[$col] == 'Y') $reports[] = $id;
            }
            $_prov['master_reports'] = $reports;

            // Add connectors to the array (columns 8-10 have the connector fields)
            $connectors = array();
            for ($cnx=2; $cnx<6; $cnx++) {
                if ($row[$cnx+6] == 'Y') $connectors[] = $cnx;
            }
            $_prov['connectors'] = $connectors;
            // Extra argument pattern gets saved only if ExtraArgs column = 'Y'
            if ($row[11] == 'Y') {
                $_prov['platform_parm'] = $row[12];
            }

            // Update or create the Provider record
            if ($current_prov) {      // Update
                $current_prov->update($_prov);
                $prov_updated++;
            } else {                 // Create
                $current_prov = GlobalProvider::create($_prov);
                $global_providers->push($current_prov);
                $cur_prov_id = $current_prov->id;
                $prov_created++;
            }
        }

        // get all the consortium instances and preserve the current instance database setting
        $instances = Consortium::get();
        $keepDB  = config('database.connections.consodb.database');

        // Rebuild full array of global providers to update (needs to match what index() does)
        $updated_providers = array();
        $gp_data = GlobalProvider::orderBy('name', 'ASC')->get();
        foreach ($gp_data as $gp) {
            $provider = $gp->toArray();
            $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";
            // $provider['reports_string'] = ($gp->master_reports) ?
            //                               $this->makeReportString($gp->master_reports) : 'None';
            $provider['connector_state'] = $this->connectorState($gp->connectors);
            $provider['report_state'] = $this->reportState($gp->master_reports);
            $provider['can_delete'] = true;
            $provider['connection_count'] = 0;
            // Collect details from the instance for this provider
            foreach ($instances as $instance) {
                $details = $this->instanceDetails($instance->ccp_key, $gp->id);
                if ($details['harvest_count'] > 0) {
                    $provider['can_delete'] = false;
                }
                $provider['connection_count'] += $details['connections'];
            }
            $updated_providers[] = $provider;
        }
        config(['database.connections.consodb.database' => $keepDB]);

        // return the current full list of providers with a success message
        $detail = "";
        $detail .= ($prov_updated > 0) ? $prov_updated . " updated" : "";
        if ($prov_created > 0) {
            $detail .= ($detail != "") ? ", " . $prov_created . " added" : $prov_created . " added";
        }
        if ($prov_skipped > 0) {
            $detail .= ($detail != "") ? ", " . $prov_skipped . " skipped" : $prov_skipped . " skipped";
        }
        $msg  = 'Import successful, Platforms : ' . $detail;

        return response()->json(['result' => true, 'msg' => $msg, 'platforms' => $updated_providers]);
    }

    /**
     * Build string representation of master_reports array
     *
     * @param  Array  $reports
     * @return String
     */
    private function makeReportString($reports) {
        $report_string = '';
        $master_reports = Report::where('revision',5)->where('parent_id',0)->orderBy('name','ASC')->get(['id','name']);
        foreach ($reports as $id) {
            $rpt = $master_reports->where('id',$id)->first();
            if ($rpt) {
                $report_string .= ($report_string == '') ? '' : ', ';
                $report_string .= $rpt->name;
            }
        }
        return $report_string;
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getMasterReports() {
        global $masterReports;
        $masterReports = Report::where('revision',5)->where('parent_id',0)->orderBy('name','ASC')->get(['id','name']);
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getConnectionFields() {
        global $allConnectors;
        $allConnectors = ConnectionField::get();
    }

    /**
     * Return an array of booleans for report-state from provider reports columns
     *
     * @param  Array  $reports
     * @return Array  $report-state
     */
    private function reportState($reports) {
        global $masterReports;
        $rpt_state = array();
        foreach ($masterReports as $rpt) {
            $rpt_state[$rpt->name] = (in_array($rpt->id, $reports)) ? true : false;
        }
        return $rpt_state;
    }

    /**
     * Return an array of booleans for connector-state from provider connectors columns
     *
     * @param  Array  $connectors
     * @return Array  $connector-state
     */
    private function connectorState($connectors) {
      global $allConnectors;
      $cnx_state = array();
      foreach ($allConnectors as $fld) {
          $cnx_state[$fld->name] = (in_array($fld->id, $connectors)) ? true : false;
      }
      return $cnx_state;
    }

    /**
     * Return an array of booleans for connector-state from provider connectors columns
     *
     * @param  String  $instnceKey
     * @param  Integer  $providerID
     * @return Array  $details
     */
    private function instanceDetails($instnceKey, $providerID) {

        $details = array('harvest_count' => 0, 'connections' => 0);

        // switch the database connection
        config(['database.connections.consodb.database' => "ccplus_" . $instnceKey]);
        try {
            DB::reconnect('consodb');
        } catch (\Exception $e) {
            return response()->json(['result' => 'Error connecting to database for instance with Key: ' . $instnceKey]);
        }
        // Get the provider and the number of harvests
        $con_prov = Provider::with('sushiSettings')->where('global_id', $providerID)->first();
        if ($con_prov) {
            $details['harvest_count'] += $con_prov->sushiSettings->whereNotNull('last_harvest')->count();
            $details['connections'] += $con_prov->sushiSettings->count();
        }
        return $details;
    }
}
