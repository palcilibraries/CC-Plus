<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\CcplusError;
use \ubfr\c5tools\JsonR5Report;
use \ubfr\c5tools\CheckResult;
use \ubfr\c5tools\ParseException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class Sushi extends Model
{
    private static $begin;
    private static $end;
    public $json;
    public $message;
    public $detail;
    public $error_code;
    public $severity;
    public $help_url;
    public $step;
    public $raw_datafile;

   /**
    * Class Constructor and setting methods
    */
    public function __construct($_begin, $_end)
    {
        self::$begin = $_begin;
        self::$end = $_end;
        $this->raw_datafile = "";
    }

   /**
    * Request the report
    *
    * @param string $uri
    * @return string $status   // Success , Fail,  Queued
    */
    public function request($uri)
    {
        $this->json = "";
        $this->message = "";
        $this->detail = "";
        $this->step = "";
        $this->error_code = 0;
        $this->severity = "";
        $this->help_url = "";
        $client = new Client();   //GuzzleHttp\Client

        // ASME (there may be others) checks the Agent and returns 403 if it doesn't like what it sees
        $options = [
            'headers' => ['User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0"]
        ];

       // Make the request and convert into JSON
        try {
             $result = $client->request('GET', $uri, $options);
        } catch (\Exception $e) {
            $this->detail = substr($e->getMessage(), 0, intval(config('ccplus.max_name_length')));
            $this->step = "HTTP";
            $this->error_code = 10;
            $this->message = "SUSHI HTTP request failed, verify URL : ";
            return "Fail";
        }

       // Issue a warning if it looks like we'll run out of memory
        $mem_avail = intval(ini_get('memory_limit'));
        $body_len = strlen($result->getBody());
        $mem_needed = ($body_len * 8) + memory_get_usage(true);
        if ($mem_needed > ($mem_avail * 1024 * 1024)) {
            $mb_need = intval($mem_needed / (1024 * 1024));
            echo "Warning! Projected memory required: " . $mb_need . "Mb but only " . $mem_avail . "Mb available\n";
            echo "-------> Decoding this report may exhaust system memory (JSON len = $body_len)\n";
        }

       // Save raw data
        if ($this->raw_datafile != "") {
            // file_put_contents($this->raw_datafile, $result->getBody());
            if (File::put($this->raw_datafile, Crypt::encrypt(bzcompress($result->getBody(), 9), false)) === false) {
                echo "Failed to save raw data in: " . $this->raw_datafile;
                // ... OR ...
                // throw new \Exception("Failed to save raw data in: ".$this->raw_datafile);
            }
        }
       // Decode result body into $json, throw and log error if it fails
//  This could be much simpler...
//  Will probably also mean we don't need to do ANY validation if the c5tools handle it...
//    $this->json = jsonReportFromBuffer($result->getBody());
//
        $this->json = json_decode($result->getBody());
        if (json_last_error() !== JSON_ERROR_NONE) {
              $this->detail = json_last_error_msg();
              $this->step = "JSON";
              $this->error_code = 20;
              $this->message = "Error decoding JSON : ";
              return "Fail";
        }
        unset($result);

       // Make sure $json is a proper object
        if (! is_object($this->json)) {
            $this->detail = " request returned " . (is_array($this->json) ? 'an array' : 'a scalar');
            $this->step = "JSON";
            $this->message = "JSON is not an object : ";
            $this->error_code = 30;
            return "Fail";
        }

       // Check and/or handle the exception
        if ($this->jsonHasExceptions()) {
           // Check for "queued" state response
            if ($this->error_code == 1011) {
                return "Pending";
            }
           // Treat "No data" as success
            if ($this->error_code == 3030) {
                $this->message = "No Data For Requested Dates";
                return "Success";
            }

           // Not queued, signal error.
            $this->step = "SUSHI";

            // Override JSON severity with value from CC+ Error table if the code is found there.
            // If code unrecognized and severity is non-Fatal, return Success and let caller handle it.
            $known_error = CcplusError::with('severity')->where('id',$this->error_code)->first();
            if ($known_error) {
                $this->severity = strtoupper($known_error->severity->name);
            }
            if ($this->severity == 'ERROR' || $this->severity == 'FATAL') {
                return "Fail";
            }
        }
        return "Success";
    }

   /**
    * Build and return a SUSHI request URI based on a setting and report
    *
    * @param SushiSetting $setting
    * @param Array $connectors
    * @param Report $_report
    * @return string $request_uri
    */
    public function buildUri($setting, $connectors, $method = "reports", $report = "")
    {
       // Begin setting up the URI by cleaning/standardizing the server_url_r5 string in the setting
        $_url = rtrim($setting->provider->globalProv->server_url_r5);    // remove trailing whitespace
        $_url = preg_replace('/\/reports\/?$/i', '', $_url);  // take off any methods with any leading slashes
        $_url = preg_replace('/\/status\/?$/i', '', $_url);  //   "   "   "     "      "   "     "        "
        $_url = preg_replace('/\/members\/?$/i', '', $_url); //   "   "   "     "      "   "     "        "
        $_uri = rtrim($_url, '/');                           // remove any remaining trailing slashes
        $request_uri = $_uri . '/' . $method;

       // Construct and execute the Request
        $uri_auth = "";
        foreach ($connectors as $cnx) {
            $argv = ($uri_auth == "") ? "?" : "&";
            if ($cnx == 'extra_args') {
                // Remove leading '& or '?' and traiiling '=' from provider extra_pattern
                $pattern = rtrim(ltrim(trim($setting->provider->globalProv->extra_pattern),'&?'),'=');
                $argv .= $pattern . "=" . urlencode( $setting->{$cnx} );
            } else {
                $argv .= $cnx . "=" . urlencode( $setting->{$cnx} );
            }
            $uri_auth .= $argv;
        }

        // Return the URI if we're not building a report request
        if ($report == "" || $method != "reports") {
            return $request_uri . $uri_auth;
        }

       // Setup date range and attributes for the request
        $uri_dates = "&begin_date=" . self::$begin . "&end_date=" . self::$end;
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
        $request_uri .= '/' . strtolower($report->name) . $uri_auth . $uri_dates . $uri_atts;
        return $request_uri;
    }

   /**
    * Validate the JSON from a SUSHI against the COUNTER standard for Release-5
    *
    * @return boolean $result
    */
    public function validateJson()
    {
       // Confirm Report_Header is present and a valid object, store in $header
        if (! property_exists($this->json, 'Report_Header')) {
            throw new \Exception('Report_Header is missing');
        }
        $header = $this->json->Report_Header;
        if (! is_object($header)) {
            throw new \Exception('Report_Header must be an object, found ' .
                                 (is_array($header) ? 'an array' : 'a scalar'));
        }

       // Get release value; we're only handling Release 5
        if (! property_exists($header, 'Release')) {
            throw new \Exception("Could not determine COUNTER Release");
        }
        if (! is_scalar($header->Release)) {
            throw new \Exception('Report_Header.Release must be a scalar, found an ' .
                                 (is_array($header->Release) ? 'array' : 'object'));
        }
        $release = trim($header->Release);
        if ($release !== '5') {
            throw new \Exception("COUNTER Release '{$release}' invalid/unsupported");
        }

       // Make sure there are Report_Items to process
        if (!isset($this->json->Report_Items)) {
            throw new \Exception("SUSHI error: no Report_Items included in JSON response.");
        } else {
            if (sizeof($this->json->Report_Items) <= 0) {
                throw new \Exception("SUSHI error: Report_Items in JSON response is empty.");
            }
        }

       // Make sure there are Report_Items to process
        try {
            $report = new JsonR5Report($this->json);
            $checkResult = $report->getCheckResult();
        } catch (\Exception $e) {
            throw new \Exception("SUSHI error: c5tools CheckResult threw a validation error.");
            //NOTE:: this needs work... c5tools expects something different. For now, just throw simple exception
            // $checkResult = new CheckResult();
            // try {
            //     $checkResult->fatalError($e->getMessage());
            // } catch (ParseException $e) {
            //     // ignore
            // }
            // $message = $checkResult->asText();
            // throw new \Exception($message());
        }
       // If we modify Counter5Processor functions to handle the validated JSON
       // (to make it more O-O), we'll need to return $report instead of a boolean.
       // For now, we're just scanning for errors and not modifying the original data.
       // return $report;
        unset($report);
        return true;
    }

    /**
     * Scan the JSON from a SUSHI request for exceptions and set returned details in
     * public class variables (sometimes exceptions are expressed differently!)
     *   * JSON Property named "Exception" takes precedence over "Exceptions".
     *   * If an array of exceptions is returned, only the first is reported.
     *     The raw JSON, however, will still hold all the returned data.
     *
     * @return boolean $has_exception
     */
    public function jsonHasExceptions()
    {
        $jException = null;
        // Code+Message at the root of returned JSON treated-as-Exception
        if (property_exists($this->json, 'Code') && property_exists($this->json, 'Message')) {
            $this->saveExceptionData($this->json);
            $jException = $this->json;
        // Test for Exception(s) at the root of the JSON
        } elseif (property_exists($this->json, 'Exception') || property_exists($this->json, 'Exceptions')) {
            $ex_prop = (property_exists($this->json, 'Exception')) ? "Exception" : "Exceptions";
            $jException = (is_array($this->json->$ex_prop)) ? $this->json->$ex_prop[0] : $this->json->$ex_prop;
        // Test for Exception(s) returned in the JSON header
        } elseif (property_exists($this->json, 'Report_Header')) {
            $header = $this->json->Report_Header;
            if (is_object($header)) {
                if (property_exists($header, 'Exception') || property_exists($header, 'Exceptions')) {
                    $ex_prop = (property_exists($header, 'Exception')) ? "Exception" : "Exceptions";
                    $jException = (is_array($header->$ex_prop)) ? $header->$ex_prop[0] : $header->$ex_prop;
                }
            }
        }
        // Set class globals if found, and return true/false
        if (!is_null($jException)) {
            $this->saveExceptionData($jException);
            return true;
        }
        return false;
    }

    // Update class data with exception details
    public function saveExceptionData($e)
    {
      $this->severity = "ERROR";
      $this->error_code = $e->Code;
      if (property_exists($e, 'Severity')) {
          $this->severity = strtoupper($e->Severity);
      }
      $this->message = $e->Message;
      $this->detail = (property_exists($e, 'Data')) ? $e->Data : "";
      $this->help_url = (property_exists($e, 'Help_URL')) ? $e->Help_URL : "";
    }
}
