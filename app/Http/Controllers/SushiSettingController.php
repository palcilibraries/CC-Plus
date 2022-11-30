<?php

namespace App\Http\Controllers;

use App\SushiSetting;
use App\Institution;
use App\Provider;
use App\HarvestLog;
use App\InstitutionGroup;
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
        if (!$thisUser->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
        }
        $json = ($request->input('json')) ? true : false;

        // Assign optional inputs to $filters array
        $filters = array('inst' => [], 'group' => 0, 'prov' => [], 'stat' => []);
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
            $group = InstitutionGroup::with('institutions:id')->find($filters['group']);
            if ($group) {
                $group_insts = $group->institutions->pluck('id')->toArray();
            }
        }
        // Skip querying for records unless we're returning json
        // The vue-component will run a request for initial data once it is mounted
        if ($json) {

            // Get sushi settings
            $data = SushiSetting::with('institution:id,name,is_active','provider:id,name,is_active')
                                  ->when(sizeof($filters['inst']) > 0, function ($qry) use ($filters) {
                                      return $qry->whereIn('inst_id', $filters['inst']);
                                  })
                                  ->when($filters['group'] > 0, function ($qry) use ($group_insts) {
                                      return $qry->whereIn('inst_id', $group_insts);
                                  })
                                  ->when(sizeof($filters['prov']) > 0, function ($qry) use ($filters) {
                                      return $qry->whereIn('prov_id', $filters['prov']);
                                  })
                                  ->when(sizeof($filters['stat']) > 0, function ($qry) use ($filters) {
                                      return $qry->whereIn('status', $filters['stat']);
                                  })
                                  ->get();

            // Add stuff to simplify the datatable
            $settings = $data->map( function($setting) {
                $setting->inst_name = $setting->institution->name;
                $setting->prov_name = $setting->provider->name;
                return $setting;
            });

            return response()->json(['settings' => $settings], 200);

        // Not returning JSON, the index/vue-component still needs these to setup the page
        } else {
            // Get ALL institutions, regardless of is_active
            $institutions = Institution::where('id', '<>', 1)->orderBy('name', 'ASC')
                                       ->get(['id', 'name', 'is_active']);

            // Get all providers, regardless of is_active
            $providers = Provider::with('connectors')->orderBy('name', 'ASC')
                                 ->get(['id', 'name', 'inst_id', 'is_active']);

            // Get InstitutionGroups
            $inst_groups = InstitutionGroup::orderBy('name', 'ASC')->get(['name', 'id'])->toArray();

            // Build an array of connection_fields used across all providers (whether connected or not)
            $all_connectors = array();
            $seen_connectors = array();
            foreach ($providers as $prov) {
                // There are only 4... if they're all set, skip checking
                if (sizeof($seen_connectors) < 4) {
                  foreach($prov->connectors as $cnx) {
                      if (!in_array($cnx->name,$seen_connectors)) {
                          $all_connectors[] = array('name' => $cnx->name, 'label' => $cnx->label);
                          $seen_connectors[] = $cnx->name;
                      }
                  }
                }
            }

            return view('sushisettings.index',
                        compact('all_connectors','institutions','inst_groups','providers','filters'));
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
        $setting = SushiSetting::with(['institution', 'provider', 'provider.connectors'])->findOrFail($id);
        abort_unless($setting->institution->canManage(), 403);

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
        $server_url = Provider::where('id', '=', $request->prov_id)->value('server_url_r5');

       // Get the settings
        $_where = ['inst_id' => $request->inst_id,
                 'prov_id' => $request->prov_id];
        $data = SushiSetting::where($_where)->first();
        $settings = (is_null($data)) ? array('count' => 0) : $data->toArray();

       // Return settings and url as json
        $return = array('settings' => $settings, 'url' => $server_url);
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

        $input = $request->all();
        if (!auth()->user()->hasAnyRole(['Admin']) && $input['inst_id'] != auth()->user()->inst_id) {
            return response()->json(['result' => false, 'msg' => 'You can only assign settings for your institution']);
        }
        $setting = SushiSetting::create($input);
        $setting->load('institution', 'provider');

        // Set status string based on is_active and add in a string for next_harvest
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
       // Validate form inputs
        $this->validate($request, ['inst_id' => 'required', 'prov_id' => 'required']);
        $input = $request->all();

       // Ensure user is allowed to change the settings
        $institution = Institution::findOrFail($request->inst_id);
        if (!$institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Invalid request']);
        }

       // Update or create the settings
        $setting = SushiSetting::updateOrCreate(
            ['inst_id' => $request->inst_id, 'prov_id' => $request->prov_id],
            $input
        );
        return response()->json(['result' => true, 'msg' => 'Setting updated successfully']);
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
        $provider = Provider::findOrFail($input['prov_id']);

        // ASME (there may be others) checks the Agent and returns 403 if it doesn't like what it sees
        $options = [
            'headers' => ['User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0"]
        ];

       // Begin setting up the URI by cleaning/standardizing the server_url_r5 string in the setting
        $_url = rtrim($provider->server_url_r5);    // remove trailing whitespace
        $_url = preg_replace('/\/?reports\/?/i', '', $_url); // take off any methods with any bounding slashes
        $_url = preg_replace('/\/?status\/?/i', '', $_url);  //   "   "   "     "      "   "     "        "
        $_url = preg_replace('/\/?members\/?/i', '', $_url); //   "   "   "     "      "   "     "        "
        $_uri = rtrim($_url, '/');                           // remove any remaining trailing slashes

       // If we got extra_args, try to clean it up and strip any leading "&" or "?"
        if (isset($input['extra_args'])) {
          $input['extra_args'] = trim($input['extra_args']);
          $input['extra_args'] = ltrim($input['extra_args'], "&?");
        }

       // Construct and execute the test request
        $_uri .= '/status?';
        $uri_auth = "";
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
        $request_uri = $_uri . $uri_auth;

       // Make the request and convert result into JSON
        $rows = array();
        $client = new Client();   //GuzzleHttp\Client
        try {
            $response = $client->request('GET', $request_uri, $options);
            $rows[] = "JSON Response:";
            $rows[] = json_decode($response->getBody(), JSON_PRETTY_PRINT);
            $result = 'Service status successfully received';
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

        // Handle input filters
        $filters = null;
        if ($request->filters) {
            $filters = json_decode($request->filters, true);
        } else {
            $filters = array('inst' => [], 'prov' => []);
        }
        if (!$thisUser->hasRole("Admin")) {
            $filters['inst'] = array($thisUser->inst_id);
        }

        // Get institution record(s)
        $inst_filters = null;
        if (sizeof($filters['inst']) == 0) {
            $institutions = Institution::get(['id', 'name']);
        } else {
            $institutions = Institution::whereIn('id', $filters['inst'])->get(['id', 'name']);
            $inst_filters = $filters['inst'];
        }
        if (!$institutions) {
            $msg = "Export failed : could not find requested institution(s).";
            return response()->json(['result' => false, 'msg' => $msg]);
        }
        // Set name if only one inst being exported
        $inst_name = ($institutions->count() == 1) ? $institution[0]->name : "";

        // Get provider record(s)
        $prov_filters = null;
        if (sizeof($filters['prov']) == 0) {
            $providers = Provider::get(['id', 'name']);
        } else {
            $providers = Provider::whereIn('id', $filters['prov'])->get(['id', 'name']);
            $prov_filters = $filters['prov'];
        }
        if (!$providers) {
            $msg = "Export failed : could not find requested provider(s).";
            return response()->json(['result' => false, 'msg' => $msg]);
        }
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
        $top_txt .= "Any header row or columns beyond 'H' will be ignored. Columns J-K are informational only.";
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
        $info_sheet->setCellValue('C18', 'Local institution identifier');
        $info_sheet->setCellValue('D18', 'Yes - If CC+ System ID not given');
        $info_sheet->setCellValue('A19', 'Provider ID');
        $info_sheet->setCellValue('B19', 'Integer > 1');
        $info_sheet->setCellValue('C19', 'Unique CC-Plus Provider ID - required');
        $info_sheet->setCellValue('D19', 'Yes');
        $info_sheet->setCellValue('A20', 'Active');
        $info_sheet->setCellValue('B20', 'String (Y or N)');
        $info_sheet->setCellValue('C20', 'Make the setting active?');
        $info_sheet->setCellValue('D20', 'No');
        $info_sheet->setCellValue('E20', 'Y');
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
        $info_sheet->setCellValue('A24', 'Support Email');
        $info_sheet->setCellValue('B24', 'String');
        $info_sheet->setCellValue('C24', 'Support email address, per-provider');
        $info_sheet->setCellValue('D24', 'No');
        $info_sheet->setCellValue('E24', 'NULL');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 22; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the settings data into a new sheet
        $inst_sheet = $spreadsheet->createSheet();

        // Align column-D for the data sheet on center
        $active_column_cells = "D2:D" . strval($settings->count()+1);
        $inst_sheet->getStyle($active_column_cells)->applyFromArray($centered_style);
        $inst_sheet->setTitle('Settings');
        $inst_sheet->setCellValue('A1', 'CC+ System ID');
        $inst_sheet->setCellValue('B1', 'Local ID');
        $inst_sheet->setCellValue('C1', 'Provider ID');
        $inst_sheet->setCellValue('D1', 'Active');
        $inst_sheet->setCellValue('E1', 'Customer ID');
        $inst_sheet->setCellValue('F1', 'Requestor ID');
        $inst_sheet->setCellValue('G1', 'API Key');
        $inst_sheet->setCellValue('H1', 'Support Email');
        $inst_sheet->setCellValue('J1', 'Institution-Name');
        $inst_sheet->setCellValue('K1', 'Provider-Name');
        $row = 2;
        foreach ($settings as $setting) {
            $inst_sheet->getRowDimension($row)->setRowHeight(15);
            $inst_sheet->setCellValue('A' . $row, $setting->inst_id);
            $inst_sheet->setCellValue('B' . $row, $setting->institution->local_id);
            $inst_sheet->setCellValue('C' . $row, $setting->prov_id);
            $inst_sheet->setCellValue('D' . $row, $setting->status);
            $inst_sheet->setCellValue('E' . $row, $setting->customer_id);
            $inst_sheet->setCellValue('F' . $row, $setting->requestor_id);
            $inst_sheet->setCellValue('G' . $row, $setting->API_key);
            $inst_sheet->setCellValue('H' . $row, $setting->support_email);
            $inst_sheet->setCellValue('J' . $row, $setting->institution->name);
            $inst_sheet->setCellValue('K' . $row, $setting->provider->name);
            $row++;
        }

        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I','J','K');
        foreach ($columns as $col) {
            $inst_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        $fileName = "CCplus";
        if (!$inst_filters && !$prov_filters) {
            $fileName .= "_" . session('ccp_con_key', '') . "_All";
        } else {
            if (!$inst_filters) {
                $fileName .= "_AllInstitutions";
            } else {
                $fileName .= ($inst_name == "") ? "_SomeInstitutions": "_" . $inst_name;
            }
            if (!$prov_filters) {
                $fileName .= "_AllProviders";
            } else {
                $fileName .= ($prov_name == "") ? "_SomeProviders": "_" . $prov_name;
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
        $institutions= Institution::get();
        if ($is_admin) {
            $inst_ids = $institutions->pluck('id')->toArray();
            $prov_ids = Provider::pluck('id')->toArray();
        } else {
            $inst_ids = array($usersInst);
            $prov_ids = Provider::whereIn('inst_id', [ 1, $usersInst ])->pluck('id')->toArray();
        }

        // Process the input rows
        $updated = 0;
        $deleted = 0;
        $skipped = 0;
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
            // If no ID and $localID not found, skip the row
            if (!$current_inst && $localID) {
                $current_inst = $institutions->where("local_id", $localID)->first();
                if (!$current_inst) {
                    $skipped++;
                    continue;
                }
                $cur_inst_id = $current_inst->id;
            }
            // Process only Inst_ids and prov_ids found in the "allowed" arrays created above
            if ( !in_array($cur_inst_id, $inst_ids) || !in_array($row[2], $prov_ids) ) {
                $skipped++;
                continue;
            }

            // Update or create the settings
            if (!is_array($row[3], array('Enabled','Disabled','Suspended','Incomplete'))) {
                $row[3] = ($row[3] == 1) ? 'Enabled' : 'Disabled';
            }
            $_args = array('status' => $row[3], 'customer_id' => $row[4], 'requestor_id' => $row[5],
                           'API_key' => $row[6], 'support_email' => $row[7]);
            $current_setting = SushiSetting::
                updateOrCreate(['inst_id' => $cur_inst_id, 'prov_id' => $row[2]], $_args);
            $updated++;
        }

        // Setup return info message
        $msg = "";
        $msg .= ($updated > 0) ? $updated . " added or updated" : "";
        if ($skipped > 0) {
            $msg .= ($msg != "") ? ", " . $skipped . " skipped" : $skipped . " skipped";
        }
        $msg  = 'Sushi settings import completed : ' . $msg;

        // If this is a manager (not an admin), create a return object with the
        // settings to be returned. The manager has run the import via the
        // AllSushiByInst component - and the settings array needs to be updated.
        // Admins run imports from the datatable and need no updated settings.
        if (!$is_admin) {
           $data = SushiSetting::with('institution:id,name','provider:id,name')
                               ->where('inst_id',$usersInst)->get();
           return response()->json(['result' => true, 'msg' => $msg, 'settings' => $settings]);
        } else {
          return response()->json(['result' => true, 'msg' => $msg]);
        }
    }
}
