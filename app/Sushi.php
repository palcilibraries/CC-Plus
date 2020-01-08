<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\CcplusError;

class Sushi extends Model
{
    private static $begin;
    private static $end;
    public $status;     // Success, Fail, or Queued
    public $message;
    public $detail;
    public $step;

  /**
   * Class Constructor and setting methods
   */
    public function __construct($_begin, $_end)
    {
        self::$begin = $_begin;
        self::$end = $_end;
    }

    /**
     * Request the report
     *
     * @param string $uri
     * @return string $json
     */
    public function request($uri)
    {
        $this->status = "Success";
        $this->message = "";
        $this->detail = "";
        $this->step = "";
        $client = new Client();   //GuzzleHttp\Client

       // Make the request and convert into JSON
        try {
             $result = $client->get($uri);
        } catch (\Exception $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->detail  = "(" . $response->getStatusCode() . ") ";
                $this->detail .= $response->getReasonPhrase();
            } else {
                $this->detail = "No response from provider.";
            }
            $this->status = "Fail";
            $this->step = "HTTP";
            $this->message = "SUSHI HTTP request failed, verify URL : ";
            return "";
        }

       // Decode result body into $json, throw and log error if it fails
        $json = json_decode($result->getBody());
        if (json_last_error() !== JSON_ERROR_NONE) {
              $this->detail = json_last_error_msg();
              $this->status = "Fail";
              $this->step = "JSON";
              $this->message = "Error decoding JSON : ";
              return "";
        }

       // Make sure $json is a proper object
        if (! is_object($json)) {
            $this->detail = " request returned " . (is_array($json) ? 'an array' : 'a scalar');
            $this->status = "Fail";
            $this->step = "JSON";
            $this->message = "JSON is not an object : ";
            return "";
        }

       // Check JSON for Exceptions. Sometimes they're expressed differently
        $found_exception = false;
        if (property_exists($json, 'Exception')) {
            $found_exception = true;
            $Code = $json->Exception->Code;
            $Severity = strtoupper($json->Exception->Severity);
            $Message = $json->Exception->Message;
        } elseif (property_exists($json, 'Code') && property_exists($json, 'Message')) {
            $found_exception = true;
            $Code = $json->Code;
            $Severity = strtoupper($json->Severity);
            $Message = $json->Message;
        }

       // Check and/or handle the exception
        if ($found_exception) {
           // Check for "queued" state response
            if ($Code == 1011) {
                $this->status = "Queued";
                return "";
            }

           // Not queued, signal error
            if ($Severity == 'ERROR' || $Severity == 'FATAL') {
               // Get/Create entry from the sushi_errors table
                $error = CcplusError::firstOrCreate(
                    ['id' => $Code],
                    ['id' => $Code, 'message' => $Message, 'severity' => $Severity]
                );
                $this->status = "Fail";
                $this->step = "SUSHI";
                $this->message = "SUSHI Exception returned (" . $Code . ") : " . $Message;
                return "";
            } else {
                $this->message = "Non-Fatal SUSHI Exception: (" . $Code . ") : " . $Message;
            }
        }
        return $json;
    }

     /**
      * Build and return a SUSHI request URI based on a setting and report
      *
      * @param SushiSetting $setting
      * @param Report $_report
      * @return string $request_uri
      */
    public static function buildUri($setting, $report)
    {
       // Begin setting up the URI for the request
        $request_uri = rtrim($setting->provider->server_url_r5, '/') . "/";
        $uri_args = "/?begin_date=" . self::$begin . "&end_date=" . self::$end;

       // Construct and execute the Request
        $uri_args .= "&customer_id=" . $setting->customer_id;
        $uri_args .= "&requestor_id=" . $setting->requestor_id;
        if (!is_null($setting->API_key)) {
            $uri_args .= "&api_key=" . $setting->API_key;
        }

       // Setup attributes for the request
        if ($report->name == "TR") {
            $uri_atts  = "&attributes_to_show=Data_Type%7CAccess_Method%7CAccess_Type%7C";
            $uri_atts .= "Section_Type%7CYOP";
        } elseif ($report->name == "DR") {
            $uri_atts = "";
        } elseif ($report->name == "PR") {
            $uri_atts = "&attributes_to_show=Data_Type%7CAccess_Method";
        } elseif ($report->name == "IR") {
            $uri_atts = "";
        }

       // Construct URI for the request
        $request_uri .= $report->name . $uri_args . $uri_atts;
        return $request_uri;
    }
}
