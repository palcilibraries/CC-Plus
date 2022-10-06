<?php

namespace App\Http\Controllers;

use App\SushiSetting;
use App\Institution;
use App\Provider;
use App\HarvestLog;
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
        if (!$setting->provider->is_active || !$setting->institution->is_active || !$setting->is_active) {
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
        $setting['status'] = ($setting->is_active) ? 'Enabled' : 'Disabled';
        if (!$setting->provider->is_active || !$setting->institution->is_active || !$setting->is_active) {
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
     * @param  string  $type    // 'xls' or 'xlsx'
     * @param  integer $inst    // (Optional) - limit to an institutionID
     * @param  integer $prov    // (Optional) - limit to a providerID
     */
    public function export($type, $inst=null, $prov=null)
    {
        $thisUser = auth()->user();

        // Only Admins and Managers can export institution data
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);

        // Get institution record(s)
        if (!$thisUser->hasRole("Admin")) {
          $inst = $thisUser->inst_id;
        }
        $institutions = Institution::
                          when($inst, function ($query, $inst) {
                            return $query->where('id', $inst);
                          })->get();

        // If limiting to just one provider, get the record
        if ($prov) {
            $provider = Provider::where('id', $prov)->first();
            if (!$provider) {
                $msg = "Export failed : could not load record for requested provider.";
                return response()->json(['result' => false, 'msg' => $msg]);
            }
        }

        // Get sushi settings
        $settings = SushiSetting::with('institution:id,name','provider:id,name')
                      ->when($inst, function ($query, $inst) {
                        return $query->where('inst_id', $inst);
                      })
                      ->when($prov, function ($query, $prov) {
                        return $query->where('prov_id', $prov);
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

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:C9');
        $info_sheet->getStyle('A1:C9')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:C9')->getAlignment()->setWrapText(true);
        $top_txt  = "The Settings tab represents a starting place for updating or importing sushi settings. The\n";
        $top_txt .= "table below describes the datatype and order that the import expects. Any Import rows without\n";
        $top_txt .= "an Institution-ID in column-A and a Provider-ID in column-B will be ignored. If values for\n";
        $top_txt .= "the other columns are optional and are missing, null, or invalid, they will be set to the";
        $top_txt .= " 'Default'.\n\n";
        $top_txt .= "Creating an export of providers and institutions will supply the reference values for the\n";
        $top_txt .= "for the Provider-ID and Institution-ID columns.\n\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'G' will be ignored. Columns I-J are informational only.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->mergeCells('B11:D11');
        $info_sheet->getStyle('A11:A11')->applyFromArray($head_style);
        $info_sheet->setCellValue('A11', "NOTE: ");
        $info_sheet->mergeCells('B11:D13');
        $info_sheet->getStyle('B11:D13')->applyFromArray($info_style);
        $info_sheet->getStyle('B11:D13')->getAlignment()->setWrapText(true);
        $note_txt  = "When performing full-replacement imports, be VERY careful about changing or \n";
        $note_txt .= "overwriting existing ID value(s). The best approach is to add to, or modify, \n";
        $note_txt .= "a full export to keep from accidentally overwriting an existing institution ID.\n";
        $info_sheet->setCellValue('B11', $note_txt);
        $info_sheet->getStyle('A15:D15')->applyFromArray($head_style);
        $info_sheet->setCellValue('A15', 'Column Name');
        $info_sheet->setCellValue('B15', 'Data Type');
        $info_sheet->setCellValue('C15', 'Description');
        $info_sheet->setCellValue('D15', 'Default');
        $info_sheet->setCellValue('A16', 'Institution ID');
        $info_sheet->setCellValue('B16', 'Integer > 1');
        $info_sheet->setCellValue('C16', 'Unique CC-Plus Institution ID - required');
        $info_sheet->setCellValue('A17', 'Provider ID');
        $info_sheet->setCellValue('B17', 'Integer > 1');
        $info_sheet->setCellValue('C17', 'Unique CC-Plus Provider ID - required');
        $info_sheet->setCellValue('A18', 'Active');
        $info_sheet->setCellValue('B18', 'String (Y or N)');
        $info_sheet->setCellValue('C18', 'Make the setting active?');
        $info_sheet->setCellValue('D18', 'Y');
        $info_sheet->setCellValue('A19', 'Customer ID');
        $info_sheet->setCellValue('B19', 'String');
        $info_sheet->setCellValue('C19', 'SUSHI customer ID , provider-specific');
        $info_sheet->setCellValue('D19', 'NULL');
        $info_sheet->setCellValue('A20', 'Requestor ID');
        $info_sheet->setCellValue('B20', 'String');
        $info_sheet->setCellValue('C20', 'SUSHI requestor ID , provider-specific');
        $info_sheet->setCellValue('D20', 'NULL');
        $info_sheet->setCellValue('A21', 'API Key');
        $info_sheet->setCellValue('B21', 'String');
        $info_sheet->setCellValue('C21', 'SUSHI API Key , provider-specific');
        $info_sheet->setCellValue('D21', 'NULL');
        $info_sheet->setCellValue('A21', 'Support Email');
        $info_sheet->setCellValue('B21', 'String');
        $info_sheet->setCellValue('C21', 'Support email address, per-provider');
        $info_sheet->setCellValue('D21', 'NULL');

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
        $inst_sheet->setTitle('Settings');
        $inst_sheet->setCellValue('A1', 'Institution ID');
        $inst_sheet->setCellValue('B1', 'Provider ID');
        $inst_sheet->setCellValue('C1', 'Active');
        $inst_sheet->setCellValue('D1', 'Customer ID');
        $inst_sheet->setCellValue('E1', 'Requestor ID');
        $inst_sheet->setCellValue('F1', 'API Key');
        $inst_sheet->setCellValue('G1', 'Support Email');
        $inst_sheet->setCellValue('I1', 'Institution-Name');
        $inst_sheet->setCellValue('J1', 'Provider-Name');
        $row = 2;
        foreach ($settings as $setting) {
            $inst_sheet->getRowDimension($row)->setRowHeight(15);
            $inst_sheet->setCellValue('A' . $row, $setting->inst_id);
            $inst_sheet->setCellValue('B' . $row, $setting->prov_id);
            $_stat = ($setting->is_active) ? "Y" : "N";
            $inst_sheet->setCellValue('C' . $row, $_stat);
            $inst_sheet->setCellValue('D' . $row, $setting->customer_id);
            $inst_sheet->setCellValue('E' . $row, $setting->requestor_id);
            $inst_sheet->setCellValue('F' . $row, $setting->API_key);
            $inst_sheet->setCellValue('G' . $row, $setting->support_email);
            $inst_sheet->setCellValue('I' . $row, $setting->institution->name);
            $inst_sheet->setCellValue('J' . $row, $setting->provider->name);
            $row++;
        }

        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I','J');
        foreach ($columns as $col) {
            $inst_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        $fileName = "CCplus_";
        if (!$inst) {
            $fileName .= session('ccp_con_key', '') . "_";
        } else {
            $fileName .= preg_replace('/ /', '', $institutions[0]->name) . "_";
        }
        $fileName .= ($prov) ? preg_replace('/ /', '', $provider->name) : "AllProviders";
        $fileName .= "_SushiSettings." . $type;

        // redirect output to client
        if ($type == 'xlsx') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // } elseif ($type == 'xls') {
        //     $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        //     header('Content-Type: application/vnd.ms-excel');
        }
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

        // Handle and validate inputs
        $this->validate($request, ['type' => 'required', 'csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }
        $type = $request->input('type');
        if ($type != 'Full Replacement' && $type != 'Add or Update') {
            return response()->json(['result' => false, 'msg' => 'Error - unrecognized import type.']);
        }

        // Get the CSV data
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Setup arrays of "allowable" institution and provider IDs
        if ($is_admin) {
            $inst_ids = Institution::pluck('id')->toArray();
            $prov_ids = Provider::pluck('id')->toArray();
        } else {
            $inst_ids = array($thisUser->inst_id);
            $prov_ids = Provider::whereIn('inst_id', [ 1, $thisUser->inst_id ])->pluck('id')->toArray();
        }

        // For Full-Replacement, get all the current IDs and setup an array to track the IDs being kept
        if ($type == 'Full Replacement') {
            $cur_setting_ids = array();
            $old_setting_ids = SushiSetting::whereIn('prov_id',$prov_ids)->whereIn('inst_id',$inst_ids)
                                            ->pluck('id')->toArray();
        }

        // Process the input rows
        $updated = 0;
        $deleted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0])) {
                continue;
            }
            // Accept only Inst_ids and prov_ids found in the "allowed" arrays created above
            if ( !in_array($row[0], $inst_ids) || !in_array($row[1], $prov_ids) ) {
                $skipped++;
                continue;
            }

            // Update or create the settings
            $_active = ($row[2] == 'N') ? 0 : 1;
            $_args = array('is_active' => $_active, 'customer_id' => $row[3], 'requestor_id' => $row[4],
                           'API_key' => $row[5], 'support_email' => $row[6]);
            $current_setting = SushiSetting::
                updateOrCreate(['inst_id' => $row[0], 'prov_id' => $row[1]], $_args);
            if ($type == 'Full Replacement') {
                $cur_setting_ids[] = $current_setting->id;
            }
            $updated++;
        }

        // Setup return info message
        $msg = "";
        $msg .= ($updated > 0) ? $updated . " added or updated" : "";
        // If this is a Full-Replacement, delete current Settings not present in the import
        if ($type == 'Full Replacement') {
            $ids_to_delete = array_diff($old_setting_ids, $cur_setting_ids);
            $deleted = SushiSetting::whereIn('id',$ids_to_delete)->delete();
            if ($deleted > 0) {
                $msg .= ($msg != "") ? ", " . $deleted . " deleted" : $deleted . " deleted";
            }
        }
        if ($skipped > 0) {
            $msg .= ($msg != "") ? ", " . $skipped . " skipped" : $skipped . " skipped";
        }
        $msg  = 'Sushi settings import completed : ' . $msg;

        // If this is a manager (not an admin), create a return object with the
        // settings to be returned. The manager has run the import via the
        // AllSushiByInst component - and the settings array needs to be updated.
        // Admins run imports from the datatable and need no updated settings.
        if (!$thisUser->hasRole('Admin')) {
           $data = SushiSetting::with('provider')->where('inst_id',$thisUser->inst_id)->get();
           // map is_active to 'status'
           $settings = $data->map(function ($setting) {
               $setting['status'] = ($setting->is_active) ? 'Enabled' : 'Disabled';
               return $setting;
           });
           return response()->json(['result' => true, 'msg' => $msg, 'settings' => $settings]);
        } else {
          return response()->json(['result' => true, 'msg' => $msg]);
        }
    }
}
