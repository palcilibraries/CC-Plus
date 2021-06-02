<?php

namespace App\Http\Controllers;

use App\SushiSetting;
use App\Institution;
use App\Provider;
use App\HarvestLog;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
//Enables us to output flash messaging
use Session;

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
        $setting = SushiSetting::with(['institution', 'provider'])->findOrFail($id);
        abort_unless($setting->institution->canManage(), 403);

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
        $this->validate($request, ['prov_id' => 'required']);
        $provider = Provider::findOrFail($request->prov_id);

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

       // Construct and execute the test request
        $_uri .= '/status';
        $uri_auth = "?customer_id=" . urlencode($request->customer_id);
        if (!is_null($request->requestor_id)) {
            $uri_auth .= "&requestor_id=" . urlencode($request->requestor_id);
        }
        if (!is_null($request->apikey)) {
            $uri_auth .= "&api_key=" . urlencode($request->apikey);
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
}
