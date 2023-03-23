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

class GlobalProviderController extends Controller
{
    private $masterReports;
    private $allConnectors;

    public function __construct()
    {
        $this->middleware(['auth','role:GlobalAdmin']);
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
      $gp_data = GlobalProvider::orderBy('name', 'ASC')->get();

      // pull master reports and connection fields
      $this->getMasterReports();
      $this->getConnectionFields();
      $all_connectors = $allConnectors->toArray();

      // get all the consortium instances and preserve the current instance database setting
      $instances = Consortium::get();
      $keepDB  = config('database.connections.consodb.database');

      // Build the providers array to pass back to the datatable
      $providers = array();
      foreach ($gp_data as $gp) {
          $provider = $gp->toArray();
          $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";
          $provider['reports_string'] = ($gp->master_reports) ?
                                        $this->makeReportString($gp->master_reports) : 'None';

          // Build arrays of booleans for connecion fields and reports for the U/I chackboxes
          $provider['connector_state'] = $this->connectorState($gp->connectors);
          $provider['report_state'] = $this->reportState($gp->master_reports);

          // Walk all instances scan for harvests connected to this provider
          // If any are found, the can_delete flag will be set to false to disable deletion option in the U/I
          $harvest_count = 0;
          $provider['can_delete'] = true;
          foreach ($instances as $instance) {
              // switch the database connection
              config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
              try {
                  DB::reconnect('consodb');
              } catch (\Exception $e) {
                  return response()->json(['result' => 'Error connecting to database for the ' . $instance->name . ' instance!']);
              }
              // Get the provider and the number of harvests
              $con_prov = Provider::with('sushiSettings')->where('global_id', $gp->id)->first();
              if ($con_prov) {
                  $harvest_count += $con_prov->sushiSettings->whereNotNull('last_harvest')->count();
              }
              if ($harvest_count > 0) {
                  $provider['can_delete'] = false;
                  break;
              };
          }
          $providers[] = $provider;
      }

      // Restore the database habdle and load the view
      config(['database.connections.consodb.database' => $keepDB]);
      return view('globalproviders.index', compact('providers', 'masterReports', 'all_connectors'));
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
      $provider->server_url_r5 = $input['server_url_r5'];

      // ensure customer_id is always required
      $input['connector_state']['customer_id'] = true;

      // Turn array of connection checkboxes into an array of IDs
      $extraArgs = false;
      $connectors = array();
      $this->getConnectionFields();
      foreach ($allConnectors as $cnx) {
          if (!isset($input['connector_state'][$cnx->name])) continue;
          if ($input['connector_state'][$cnx->name]) {
              if ($cnx->name == 'extra_args') $extraArgs = true;
              $connectors[] = $cnx->id;
          }
      }
      $provider->connectors = $connectors;
      if ($extraArgs && !is_null($input['extra_pattern'])) {
          $provider->extra_pattern = $input['extra_pattern'];
      }

      // Turn array of report checkboxes into an array of IDs
      $master_reports = array();
      $reports_string = "";
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
      $provider['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $provider['connector_state'] = $input['connector_state'];
      $provider['reports_string'] = ($provider->master_reports) ?
                                    $this->makeReportString($provider->master_reports) : 'None';
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
      $this->validate($request, [ 'name' => 'required', 'is_active' => 'required', 'server_url_r5' => 'required' ]);
      $input = $request->all();
      $isActive = ($input['is_active']) ? 1 : 0;

      // Pull all connection fields and master reports
      $all_connectors = ConnectionField::get();
      $this->getMasterReports();

      // Gather IDs of reports that have been removed. We'll detach these from the consortia instance tables.
      // NOTE:: adding to the global master list doesn't automatically enable new reports in the instance tables.
      $dropped_reports = array();
      $original_reports = $provider->master_reports;
      foreach ($original_reports as $mr) {
          $_master = $masterReports->where('id', $mr)->first();
          if (!$_master) continue;
          if (!isset($input['report_state'][$_master->name])) continue;
          if (!$input['report_state'][$_master->name]) {
              $dropped_reports[] = $mr;
          }
      }

      // Update the record in the global table
      $provider->name = $input['name'];
      $provider->is_active = $isActive;
      $provider->server_url_r5 = $input['server_url_r5'];

      // ensure customer_id is always required
      $input['connector_state']['customer_id'] = true;

      // Turn array of connection checkboxes into an array of IDs
      $extraArgs = false;
      $new_connectors = array();
      foreach ($all_connectors as $cnx) {
          if (!isset($input['connector_state'][$cnx->name])) continue;
          if ($input['connector_state'][$cnx->name]) {
              if ($cnx->name == 'extra_args') $extraArgs = true;
              $new_connectors[] = $cnx->id;
          }
      }
      $connectors_changed = ($provider->connectors != $new_connectors);
      $provider->connectors = $new_connectors;
      $provider->extra_pattern = ($extraArgs) ? $input['extra_pattern'] : null;

      // Turn array of report checkboxes into an array of IDs
      $master_reports = array();
      $reports_string = "";
      foreach ($masterReports as $rpt) {
        if (!isset($input['report_state'][$rpt->name])) continue;
        if ($input['report_state'][$rpt->name]) {
            $master_reports[] = $rpt->id;
            $reports_string .= ($reports_string=="") ? "" : ", ";
            $reports_string .= $rpt->name;
        }
      }
      $provider->master_reports = $master_reports;
      $provider->save();
      $provider['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $provider['connector_state'] = $input['connector_state'];
      $provider['reports_string'] = ($reports_string == "") ? 'None' : $reports_string;
      $provider['report_state'] = (isset($input['report_state'])) ? $input['report_state'] : array();

      // Get connector fields
      $fields = $all_connectors->whereIn('id',$provider->connectors)->pluck('name')->toArray();
      $unused_fields = $all_connectors->whereNotIn('id',$provider->connectors)->pluck('name')->toArray();

      // If changes implicate consortia-provider settings, Loop through all consortia instances
      if ($input['name']!=$orig_name || $isActive!=$orig_isActive || count($dropped_reports)>0 || $connectors_changed) {
          $instances = Consortium::get();
          $keepDB  = config('database.connections.consodb.database');
          $prov_updates = array('name' => $input['name']);
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
              if ($input['name']!=$orig_name || $isActive!=$orig_isActive) {
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
                      // Setting is Complete; clear '-missing-' labels on unused fields
                      foreach ($unused_fields as $uf) {
                          if ($setting->$uf == '-missing-') {
                              $setting_updates[$uf]= '';
                          }
                      }
                  // If required conenctors are missing value(s), mark them and update setting status tp Incomplete
                  } else {
                      $setting_updates['status'] = 'Incomplete';
                      foreach ($fields as $fld) {
                          if ($setting->$fld == null || $setting->$fld == '') {
                              $setting_updates[$fld] = "-missing-";
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

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:D7');
        $info_sheet->getStyle('A1:D7')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:D7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Providers tab represents a starting place for updating or importing settings. The table\n";
        $top_txt .= "below describes the datatype and order that the import expects. Any Import rows without an\n";
        $top_txt .= "ID value in column A and a name in column B will be ignored. If values are missing or invalid\n";
        $top_txt .= "for columns (B-L), but not required, they will be set to the 'Default'.\n\n";
        $top_txt .= "Any header row or columns beyond 'L' will be ignored. Once the data sheet contains everything\n";
        $top_txt .= "to be updated or inserted, save the sheet as a CSV and import it into CC-Plus.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A8')->applyFromArray($head_style);
        $info_sheet->setCellValue('A8', "NOTE:");
        $info_sheet->mergeCells('B8:D10');
        $info_sheet->getStyle('B8:D10')->applyFromArray($info_style);
        $info_sheet->getStyle('B8:D10')->getAlignment()->setWrapText(true);
        $note_txt  = "Provider imports cannot be used to delete existing providers; only additions and updates are\n";
        $note_txt .= "supported. The recommended approach is to add to, or modify, a previously run full export\n";
        $note_txt .= "to ensure that desired end result is achieved.";
        $info_sheet->setCellValue('B8', $note_txt);
        $info_sheet->getStyle('A12:E12')->applyFromArray($head_style);
        $info_sheet->setCellValue('A12', 'Column Name');
        $info_sheet->setCellValue('B12', 'Data Type');
        $info_sheet->setCellValue('C12', 'Description');
        $info_sheet->setCellValue('D12', 'Required');
        $info_sheet->setCellValue('E12', 'Default');
        $info_sheet->setCellValue('A13', 'Id');
        $info_sheet->setCellValue('B13', 'Integer');
        $info_sheet->setCellValue('C13', 'Unique CC-Plus Provider ID');
        $info_sheet->setCellValue('D13', 'Y');
        $info_sheet->setCellValue('A14', 'Name');
        $info_sheet->setCellValue('B14', 'String');
        $info_sheet->setCellValue('C14', 'Provider name');
        $info_sheet->setCellValue('D14', 'Y');
        $info_sheet->setCellValue('A15', 'Active');
        $info_sheet->setCellValue('B15', 'String (Y or N)');
        $info_sheet->setCellValue('C15', 'Make the provider active?');
        $info_sheet->setCellValue('D15', 'N');
        $info_sheet->setCellValue('E15', 'Y');
        $info_sheet->setCellValue('A16', 'Server URL');
        $info_sheet->setCellValue('B16', 'String');
        $info_sheet->setCellValue('C16', 'URL for Provider SUSHI service');
        $info_sheet->setCellValue('D16', 'Y');
        $info_sheet->setCellValue('E16', 'NULL');
        $info_sheet->setCellValue('A17', 'harvest_day');
        $info_sheet->setCellValue('B17', 'Integer');
        $info_sheet->setCellValue('C17', 'Day of the month provider reports are ready (1-28)');
        $info_sheet->setCellValue('D17', 'N');
        $info_sheet->setCellValue('E17', '15');
        $info_sheet->setCellValue('A18', 'Institution ID');
        $info_sheet->setCellValue('B18', 'Integer');
        $info_sheet->setCellValue('C18', 'Institution ID (see above)');
        $info_sheet->setCellValue('D18', 'N');
        $info_sheet->setCellValue('E18', '1');
        $info_sheet->setCellValue('A19', 'PR Reports');
        $info_sheet->setCellValue('B19', 'String (Y or N)');
        $info_sheet->setCellValue('C19', 'Provider supplies PR reports?');
        $info_sheet->setCellValue('D19', 'N');
        $info_sheet->setCellValue('E19', 'Y');
        $info_sheet->setCellValue('A20', 'DR Reports');
        $info_sheet->setCellValue('B20', 'String (Y or N)');
        $info_sheet->setCellValue('C20', 'Provider supplies DR reports?');
        $info_sheet->setCellValue('D20', 'N');
        $info_sheet->setCellValue('E20', 'Y');
        $info_sheet->setCellValue('A21', 'TR Reports');
        $info_sheet->setCellValue('B21', 'String (Y or N)');
        $info_sheet->setCellValue('C21', 'Provider supplies TR reports?');
        $info_sheet->setCellValue('D21', 'N');
        $info_sheet->setCellValue('E21', 'Y');
        $info_sheet->setCellValue('A22', 'IR Reports');
        $info_sheet->setCellValue('B22', 'String (Y or N)');
        $info_sheet->setCellValue('C22', 'Provider supplies IR reports?');
        $info_sheet->setCellValue('D22', 'N');
        $info_sheet->setCellValue('E22', 'Y');
        $info_sheet->setCellValue('A23', 'Requestor ID');
        $info_sheet->setCellValue('B23', 'String (Y or N)');
        $info_sheet->setCellValue('C23', 'Requestor ID is required for Sushi connections');
        $info_sheet->setCellValue('D23', 'N');
        $info_sheet->setCellValue('E23', 'N');
        $info_sheet->setCellValue('A24', 'API Key');
        $info_sheet->setCellValue('B24', 'String (Y or N)');
        $info_sheet->setCellValue('C24', 'API Key is required for Sushi connections');
        $info_sheet->setCellValue('D24', 'N');
        $info_sheet->setCellValue('E24', 'N');
        $info_sheet->setCellValue('A25', 'Extra Arguments');
        $info_sheet->setCellValue('B25', 'String (Y or N)');
        $info_sheet->setCellValue('C25', 'Extra Arguments are required for Sushi connections');
        $info_sheet->setCellValue('D25', 'N');
        $info_sheet->setCellValue('E25', 'N');
        $info_sheet->setCellValue('A26', 'Extra Args Pattern');
        $info_sheet->setCellValue('B26', 'String');
        $info_sheet->setCellValue('C26', 'ExtraArgs Pattern - ignored if Extra Args is "N"');
        $info_sheet->setCellValue('D26', 'N');
        $info_sheet->setCellValue('E26', 'NULL');
        $info_sheet->setCellValue('C27', '(e.g.  &extra_cred=value&some_parm=value)');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 28; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }
        // setup arrays with the report and connectors mapped to their column ids
        $rpt_col = array('PR' => 'E', 'DR' => 'F', 'TR' => 'G', 'IR' => 'H');
        $cnx_col = array('requestor_id' => 'I', 'API_key' => 'J', 'extra_args' => 'K');

        // Load the provider data into a new sheet
        $providers_sheet = $spreadsheet->createSheet();
        $providers_sheet->setTitle('Providers');
        $providers_sheet->setCellValue('A1', 'Id');
        $providers_sheet->setCellValue('B1', 'Name');
        $providers_sheet->setCellValue('C1', 'Active');
        $providers_sheet->setCellValue('D1', 'Server URL');
        $providers_sheet->setCellValue('E1', 'PR-Reports');
        $providers_sheet->setCellValue('F1', 'DR-Reports');
        $providers_sheet->setCellValue('G1', 'TR-Reports');
        $providers_sheet->setCellValue('H1', 'IR-Reports');
        $providers_sheet->setCellValue('I1', 'Requestor-ID');
        $providers_sheet->setCellValue('J1', 'API-Key');
        $providers_sheet->setCellValue('K1', 'Extra-Args');
        $providers_sheet->setCellValue('L1', 'Extra-Args-Pattern');
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
                if ($field->name == 'customer_id') continue;
                $value = (in_array($field->id, $provider->connectors)) ? 'Y' : 'N';
                $providers_sheet->setCellValue($cnx_col[$field->name] . $row, $value);
            }
            $providers_sheet->setCellValue('L' . $row, $provider->extra_pattern);
            $row++;
        }

        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I','J','K','L');
        foreach ($columns as $col) {
            $providers_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // redirect output to client browser
        $fileName = "CCplus_Global_Providers." . $type;
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

        // Setup Mapping for report-COLUMN indeces to master_report ID's
        $rpt_columns = array( 4 => 3, 5 => 2, 6 => 1, 7 => 4);

        // Process the input rows
        $cur_prov_id = 0;
        $prov_skipped = 0;
        $prov_updated = 0;
        $prov_created = 0;
        $seen_provs = array();          // track providers already processed while looping
        foreach ($rows as $row) {
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0]) || sizeof($row) < 7) {
                continue;
            }
            $cur_prov_id = intval($row[0]);
            if (in_array($cur_prov_id, $seen_provs)) {
              continue;
            }

            // Update/Add the provider data/settings
            // Check ID and name columns for silliness or errors
            $_name = trim($row[1]);
            $current_prov = $global_providers->where("id", "=", $cur_prov_id)->first();
            if (!is_null($current_prov)) {      // found existing ID
                if (strlen($_name) < 1) {       // If import-name empty, use current value
                    $_name = trim($current_prov->name);
                } else {                        // trap changing a name to a name that already exists
                    $existing_prov = $global_providers->where("name", "=", $_name)->first();
                    if (!is_null($existing_prov)) {
                        $_name = trim($current_prov->name);     // override, use current - no change
                    }
                }
            } else {        // existing ID not found, try to find by name
                $current_prov = $global_providers->where("name", "=", $_name)->first();
                if (!is_null($current_prov)) {
                    $_name = trim($current_prov->name);
                }
            }

            // Dont store/create anything if name is still empty
            if (strlen($_name) < 1) {
                $prov_skipped++;
                continue;
            }

            // Enforce defaults
            $seen_provs[] = $cur_prov_id;
            $_active = ($row[2] == 'N') ? 0 : 1;
            $_url = ($row[3] == '') ? null : $row[3];

            // Setup provider data as an array
            $_prov = array('id' => $cur_prov_id, 'name' => $_name, 'is_active' => $_active, 'server_url_r5' => $_url);

            // Add reports to the array ($rpt_columns defined above)
            $reports = array();
            foreach ($rpt_columns as $idx => $id) {
                if ($row[$idx] == 'Y') $reports[] = $id;
            }
            $_prov['master_reports'] = $reports;

            // Add connectors to the array (columns 8-10 have the connector fields)
            $connectors = array(1);   // Customer ID is always ON
            for ($cnx=2; $cnx<5; $cnx++) {
                if ($row[$cnx+6] == 'Y') $connectors[] = $cnx;
            }
            $_prov['connectors'] = $connectors;
            // Extra argument pattern gets saved only if ExtraArgs column = 'Y'
            if ($row[10] == 'Y') {
                $_prov['extra_pattern'] = $row[11];
            }

            // Update or create the Provider record
            if (is_null($current_prov)) {      // Create
                $current_prov = GlobalProvider::create($_prov);
                $cur_prov_id = $current_prov->id;
                $prov_created++;
            } else {                            // Update
                $current_prov->update($_prov);
                $prov_updated++;
            }
        }

        // Rebuild full array of global providers to update (needs to match what index() does)
        $updated_providers = array();
        $gp_data = GlobalProvider::orderBy('name', 'ASC')->get();
        foreach ($gp_data as $gp) {
            $provider = $gp->toArray();
            $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";
            $provider['reports_string'] = ($gp->master_reports) ?
                                          $this->makeReportString($gp->master_reports) : 'None';
            $provider['connector_state'] = $this->connectorState($gp->connectors);
            $provider['report_state'] = $this->reportState($gp->master_reports);
            $provider['can_delete'] = true;
            $updated_providers[] = $provider;
        }

        // return the current full list of providers with a success message
        $detail = "";
        $detail .= ($prov_updated > 0) ? $prov_updated . " updated" : "";
        if ($prov_created > 0) {
            $detail .= ($detail != "") ? ", " . $prov_created . " added" : $prov_created . " added";
        }
        if ($prov_skipped > 0) {
            $detail .= ($detail != "") ? ", " . $prov_skipped . " skipped" : $prov_skipped . " skipped";
        }
        $msg  = 'Import successful, Providers : ' . $detail;

        return response()->json(['result' => true, 'msg' => $msg, 'providers' => $updated_providers]);
    }

    /**
     * Build string representation of master_reports array
     *
     * @param  Array  $reports
     * @return String
     */
    private function makeReportString($reports) {
        $report_string = '';
        $master_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
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
        $order = array('PR','DR','TR','IR');
        $_reports = Report::where('revision', '=', 5)->where('parent_id', '=', 0)->get(['id','name']);
        $reports_array = array();
        foreach ($order as $_name) {
            $rpt = $_reports->where('name',$_name)->first();
            if (!$rpt) continue;
            $reports_array[] = $rpt;
        }
        $masterReports = collect($reports_array);
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
}
