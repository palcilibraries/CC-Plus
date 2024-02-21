<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use DB;
use App\Report;
use App\GlobalProvider;
use App\ConnectionField;

class GlobalProviderUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ccplus:global-provider-update';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Global Provider definitions using settings from the Project COUNTER API';
    private $client;
    private $options;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        global $client, $options;

        parent::__construct();
        $client = new Client();   //GuzzleHttp\Client
        $options = [
            'headers' => ['User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0"]
        ];

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        global $client, $options;

        // Set global_db for connecting to the ccplus global tables
        $global_db   = \Config::get('database.connections.globaldb.database');

        // Get Platform section from the API root
        $json = self::requestURI("https://registry.projectcounter.org/api/v1/platform/?format=json");
        if ( !is_array($json)) {
            $this->error("Error - Array expected from Registry Platform request... something else came back!");
            return false;
        }
        if (count($json) == 0) {
            $this->error("No Platform data returned from Registry Platform request!");
            return false;
        }

        // Pull master reports
        $master_reports = Report::where('parent_id', '=', 0)->get(['id','name']);
        // Pull connection fields and map a static array to what the API sends back
        $fields = ConnectionField::get();
        $api_connectors = array('customer_id_info'      => array('field' => 'customer_id', 'id' => null),
                                'requestor_id_required' => array('field' => 'requestor_id', 'id' => null),
                                'api_key_required'      => array('field' => 'api_key', 'id' => null)
                               );
        foreach ($api_connectors as $key => $cnx) {
            $fld = $fields->where('name', $cnx['field'])->first();
            if (!$fld) continue;
            $api_connectors[$key]['id'] = $fld->id;
        }

        // Walk the array by-platform
        foreach ($json as $platform) {

            if (is_null($platform->id)) continue;

            // Get reports available
            $reportIds = $master_reports->whereIn('name',array_column($platform->reports,'report_id'))->pluck('id')->toArray();

            // Pull the Sushi Servies page from the API
            $services = "";
            foreach ($platform->sushi_services as $svc) {
                if ($services != "") continue;
                $services = $svc->url;
            }

            // request the sushi details
            $connectors = array();
            $server_url_r5 = null;
            $notifications_url = null;
            $details = self::requestURI($services);
            if (!is_object($details)) {
                $this->error("Error getting sushi details for: " . $platform->name . " ... continuing");

            // Get connection fields (for now, assumes customer_id is always required)
            } else {
                $server_url_r5 = $details->url;
                $notifications_url = $details->notifications_url;
                foreach ($api_connectors as $key => $cnx) {
                    if ($key == 'customer_id_info' || $details->{$key}) {
                        $connectors[] = $cnx['id'];
                    }
                }
            }

            // Create or ppdate existing record in global_providers table
            $global_provider = GlobalProvider::where('registry_id',$platform->id)->orWhere('name',$platform->name)->first();
            if (!$global_provider) {
                $global_provider = new GlobalProvider;
            }
            $global_provider->registry_id = $platform->id;
            $global_provider->name = $platform->name;
            $global_provider->abbrev = $platform->abbrev;
            $global_provider->master_reports = $reportIds;
            $global_provider->connectors = $connectors;
            $global_provider->server_url_r5 = $server_url_r5;
            $global_provider->notifications_url = $notifications_url;
            $global_provider->save();
        }
    }

    private function requestURI($uri)
    {
      global $client, $options;

      // Get Platform section from the API root
      try {
          $result = $client->request('GET', $uri, $options);
      } catch (\Exception $e) {
          $this->error("API request Failed for: " . $uri);
          $this->error("Error was: " . $e->getMessage());
          return 0;
      }
      // Get JSON from the response and do basic error checks
      $json = json_decode($result->getBody());
      if (json_last_error() !== JSON_ERROR_NONE) {
          $this->error("Error decoding JSON returned from : " . $uri);
          return false;
      }
      return $json;
    }
}
