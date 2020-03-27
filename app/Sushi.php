<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
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
            if (File::put($this->raw_datafile, Crypt::encrypt(bzcompress($result->getBody(), 9), false)) === false) {
                echo "Failed to save raw data in: ".$this->raw_datafile;
                // ... OR ...
                // throw new \Exception("Failed to save raw data in: ".$this->raw_datafile);
            }
            //  --->>> This needs to be added SOMEPLACE to be able to decrypt and decompress the file... <<<---
            // function get() {
            //     return bzdecompress(Crypt::decrypt(File::get($this->raw_datafile), false));
            // }
        }

       // Decode result body into $json, throw and log error if it fails
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

           // Not queued, signal error. If severity is non-Fatal, the message and code are
           // set already and we'll return Success to allow the caller to report it (or not).
            $this->step = "SUSHI";
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
    * @param Report $_report
    * @return string $request_uri
    */
    public function buildUri($setting, $method="reports", $report="")
    {
       // Begin setting up the URI by cleaning/standardizing the server_url_r5 string in the setting
        $_url = rtrim($setting->provider->server_url_r5);    // remove trailing whitespace
        $_url = preg_replace('/\/?reports\/?/i', '', $_url); // take off any methods with any bounding slashes
        $_url = preg_replace('/\/?status\/?/i', '', $_url);  //   "   "   "     "      "   "     "        "
        $_url = preg_replace('/\/?members\/?/i', '', $_url); //   "   "   "     "      "   "     "        "
        $_uri = rtrim($_url, '/');                           // remove any remaining trailing slashes
        $request_uri = $_url . '/' . $method . '/';

       // Construct and execute the Request
        $uri_auth = "?customer_id=" . urlencode($setting->customer_id);
        if (!is_null($setting->requestor_id)) {
            $uri_auth .= "&requestor_id=" . urlencode($setting->requestor_id);
        }
// Needs testing and confirmation:
// haven't (yet) found a vendor that recognizes api_key .. the value (or the argument-name?)
        if (!is_null($setting->API_key)) {
            $uri_auth .= "&api_key=" . urlencode($setting->API_key);
        }
        if ($report=="") {
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
        $request_uri .= $report->name . $uri_auth . $uri_dates . $uri_atts;
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
        }

       // Make sure there are Report_Items to process
        try {
            $report = new JsonR5Report($this->json);
            $checkResult = $report->getCheckResult();
        } catch (\Exception $e) {
            $checkResult = new CheckResult();
            try {
                $checkResult->fatalError($e->getMessage());
            } catch (ParseException $e) {
                // ignore
            }
            $message = $checkResult->asText();
            throw new \Exception($message());
        }
       // If we modify Counter5Processor functions to handle the validated JSON
       // (to make it more O-O), we'll need to return $report instead of a boolean.
       // For now, we're just scanning for errors and not modifying the original data.
       // return $report;
        unset($report);
        return true;
    }

    /**
     * Scan the JSON from a SUSHI for exceptions and set returned details in
     * public class variables
     *
     * @return boolean $has_exception
     */
    public function jsonHasExceptions()
    {
        // Check JSON for Exceptions. Sometimes they're expressed differently
         $has_exception = false;
         if (property_exists($this->json, 'Exception')) {
             $has_exception = true;
             $this->error_code = $this->json->Exception->Code;
             $this->severity = strtoupper($this->json->Exception->Severity);
             $this->message = $this->json->Exception->Message;
         } elseif (property_exists($this->json, 'Code') && property_exists($this->json, 'Message')) {
             $has_exception = true;
             $this->error_code = $this->json->Code;
             $this->severity = strtoupper($this->json->Severity);
             $this->message = $this->json->Message;
         } else {
             if (property_exists($this->json, 'Report_Header')) {
                 $header = $this->json->Report_Header;
                 if (is_object($header)) {
                    // Scan the JSON header for exception(s)
                     if (property_exists($header, 'Exception')) {
                         $has_exception = true;
                         $this->error_code = $header->Exception->Code;
                         $this->severity = strtoupper($header->Exception->Severity);
                         $this->message = $header->Exception->Message;
                     }
                     if (property_exists($header, 'Exceptions')) {
                         $has_exception = true;
                         $this->error_code = $header->Exceptions[0]->Code;
                         $this->severity = strtoupper($header->Exceptions[0]->Severity);
                         $this->message = $header->Exceptions[0]->Message;
                     }
                 }
             }
         }
         return $has_exception;
    }
}
