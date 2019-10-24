<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
// use DateTime;
use App\Provider;
use App\Platform;
use App\Institution;
use App\PRreport;
use App\TRreport;
use App\DRreport;
use App\IRreport;

class Counter5Processor extends Model
{
    public $status = true;
    public $error = "Success";
    private static $prov;
    private static $inst;
    private static $begin;
    private static $end;
    private static $yearmon;
    private static $out_csv = "";

  /**
   * Class Constructor and setting methods
   */
    public function __construct($_prov, $_inst, $_begin, $_end, $_out_csv = "")
    {
        self::$prov = $_prov;
        self::$inst = $_inst;
        self::$begin = $_begin;
        self::$end = $_end;
        self::$out_csv = $_out_csv;
        self::$yearmon = substr($_begin, 0, 7);
    }

    public function setOutCsv($_out_csv)
    {
        self::$out_csv = $_out_csv;
    }

    public function setBegin($_begin)
    {
        self::$begin = $_begin;
    }

    public function setEnd($_end)
    {
        self::$end = $_end;
    }

  /**
   * Parse and save json data for a COUNTER-5 TR report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or error-description)
   */
    public static function TR($json_report)
    {
      // Set status to success
        $status = true;
    }

  /**
   * Parse and save json data for a COUNTER-5 DR report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or error-description)
   */
    public static function DR($json_report)
    {
      // Set status to success
        $status = true;
    }

  /**
   * Parse and save json data for a COUNTER-5 DR report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or error-description)
   */
    public static function PR($json_report)
    {
       // Setup an array to hold per-item counts
        $ICounts = ['Searches_Platform' => 0, 'Total_Item_Investigations' => 0, 'Total_Item_Requests' => 0,
                    'Unique_Item_Investigations' => 0, 'Unique_Item_Requests' => 0,
                    'Unique_Title_Investigations' => 0, 'Unique_Title_Requests' => 0];
        $_metric_keys = array_keys($ICounts);
        $_metric_count = count($ICounts);

       // Decode JSON and put header and report records into variables
        $header = $json_report->Report_Header;
        $ReportItems = $json_report->Report_Items;

       // Put out report header and total rows
        if (self::$out_csv != "") {
            $csv_file = fopen(self::$out_csv, 'w');
            fwrite($csv_file, "Platform Master Report (R5)\n");
            fwrite($csv_file, "Institution: " . (string) $header->Institution_Name . "\n");
            fwrite($csv_file, "CustomerID: " . (string) $header->Customer_ID . "\n");
            fwrite($csv_file, "Created: " . (string) $header->Created . "\n");
            fwrite($csv_file, "Created By: " . (string) $header->Created_By . "\n");
            fwrite($csv_file, "Period covered by Report: ");
            fwrite($csv_file, self::$begin . " to " . self::$end . "\n");
            $colhdr  = "\nPlatform,Data Type,Access Method,Platform Searches,Total Item Investigations,";
            $colhdr .= "Total Item Requests,Unique Item Investigations,Unique Item Requests,";
            $colhdr .= "Unique Title Investigations,Unique Title Requests\n";
            fwrite($csv_file, $colhdr);
        }

       // Loop through all ReportItems
        foreach ($ReportItems as $item) {
           // Platform is required; if Null, skip the item.
            $_platform = (isset($item->Platform)) ? $item->Platform : "";
            if ($_platform == "") {
                continue;
            }

           // Get or create Platform for this item.
            $platform = self::getPlatform($item->Platform);

           // Pick up the optional attributes
            $_datatype = (isset($item->Data_Type)) ? $item->Data_Type : "";
            $_accessmethod = (isset($item->Access_Method)) ? $item->Access_Method : "";

           // Init output-lne for writing to output file
            if (self::$out_csv != "") {
                $output_line = "\"" . $_platform . "\",\"" . $_datatype . "\",\"" . $_accessmethod . "\"";
            }

           // Loop $item->Performance elements and store counts when time-periods match
            foreach ($item->Performance as $perf) {
                if (
                    $perf->Period->Begin_Date == self::$begin  &&
                    $perf->Period->End_Date == self::$end
                ) {
                    foreach ($perf->Instance as $instance) {
                        $ICounts[$instance->Metric_Type] += $instance->Count;
                    }
                }
            }         // foreach performance clause

           // Insert the record
            PRreport::insert(['plat_id' => $platform->id, 'prov_id' => self::$prov,
                              'inst_id' => self::$inst, 'yearmon' => self::$yearmon,
                              'data_type' => $_datatype, 'access_method' => $_accessmethod,
                              'searches_platform' => $ICounts['Searches_Platform'],
                              'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                              'total_item_requests' => $ICounts['Total_Item_Requests'],
                              'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                              'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                              'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                              'unique_title_requests' => $ICounts['Unique_Title_Requests']]);

           // Send a record to the output file
            if (self::$out_csv != "") {
                for ($_m = 0; $_m < $_metric_count; $_m++) {
                    $output_line .= "," . $ICounts[$_metric_keys[$_m]];
                }
                $output_line .= "\n";
                fwrite($csv_file, $output_line);
            }

           // Reset metric counts
            for ($_m = 0; $_m < $_metric_count; $_m++) {
                $ICounts[$_metric_keys[$_m]] = 0;
            }
        }     // foreach $ReportItems

      // Close output file
        if (self::$out_csv != "") {
            fclose($csv_file);
        }

       // Set status to success
        $status = true;
    }

  /**
   * Parse and save json data for a COUNTER-5 DR report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or error-description)
   */
    public static function IR($json_report)
    {

      // Set status to success
        $status = true;
    }

   /**
    * Function to find-or-create a Platform based on name and return it
    *
    * @param  $name
    * @return Platform
    */
    private static function getPlatform($name)
    {

        $platform = Platform::where('name', '=', $name)->first();
        if ($platform === null) {   // create it
            $platform = new Platform(['name' => $name]);
            $platform->save();
        }
        return $platform;
    }
}
