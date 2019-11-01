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
       // Setup array to hold per-item counts
        $ICounts = ['Total_Item_Investigations' => 0, 'Total_Item_Requests' => 0,
                    'Unique_Item_Investigations' => 0, 'Unique_Item_Requests' => 0,
                    'Unique_Title_Investigations' => 0, 'Unique_Title_Requests' => 0,
                    'Limit_Exceeded' => 0, 'No_License' =>0];
        $_metric_keys = array_keys($ICounts);
        $_metric_count = count($ICounts);

       // Decode JSON and put header and report records into variables
        $header = $json_report->Report_Header;
        $ReportItems = $json_report->Report_Items;

       // Put out report header and total rows
        if (self::$out_csv != "") {
            $csv_file = fopen(self::$out_csv, 'w');
            fwrite($csv_file, "Title Master Report (R5)\n");
            fwrite($csv_file, "Publisher: " . (string) $header->Publisher . "\n");
            fwrite($csv_file, "Institution: " . (string) $header->Institution_Name . "\n");
            fwrite($csv_file, "CustomerID: " . (string) $header->Customer_ID . "\n");
            fwrite($csv_file, "Created: " . (string) $header->Created . "\n");
            fwrite($csv_file, "Created By: " . (string) $header->Created_By . "\n");
            fwrite($csv_file, "Period covered by Report: ");
            fwrite($csv_file, self::$begin . " to " . self::$end . "\n");
            $colhdr  = "\nTitle,Platform,DOI,Proprietary ID,ISBN,Print ISSN,Online ISSN,URI,Data Type,Section Type,";
            $colhdr .= "YOP,Access Type,Access Method,Total Item Investigations,Total Item Requests,";
            $colhdr .= "Unique Item Investigations,Unique Item Requests,Unique Title Investigations,";
            $colhdr .= "Unique Title Requests,Limit Exceeded,No License\n";
            fwrite($csv_file, $colhdr);
        }

       // Loop through all ReportItems
        foreach ($ReportItems as $item) {

           // Put Item fields into variables
            $_title = (isset($item->Title)) ? $item->Title : "";
            $_platform = (isset($item->Platform)) ? $item->Platform : "";
            $_DOI = (isset($item->DOI)) ? $item->DOI : "";
            $_ISBN = (isset($item->ISBN)) ? $item->ISBN : "";
            $_ISSN = (isset($item->ISSN)) ? $item->ISSN : "";
            $_eISSN = (isset($item->eISSN)) ? $item->eISSN : "";
            $_PropID = (isset($item->Proprietary_ID)) ? $item->Proprietary_ID : "";
            $_URI = (isset($item->URI)) ? $item->URI : "";
           // Pick up the optional attributes
            $_datatype = (isset($item->Data_Type)) ? $item->Data_Type : "";
            $_sectiontype = (isset($item->Section_Type)) ? $item->Section_Type : "";
            $_YOP = (isset($item->YOP)) ? $item->YOP : "";
            $_accesstype = (isset($item->Access_Type)) ? $item->Access_Type : "";
            $_accessmethod = (isset($item->Access_Method)) ? $item->Access_Method : "";

           // If title or platform are null skip the item.
            if ($_title == "" || $_platform == "") {
                continue;
            }

           // Get or create Platform for this item.
            $platform = self::getPlatform($_platform);

           // Data_Type is optional... if null, decide what this is based on IS*N field(s)
           // (If ISBN *and* one of ISSN/eISSN are present, treat it as a Journal)
            if ($_datatype == "") {
                if ( $_ISSN == "" && $_eISSN = "") {
                    if ( $_ISBN == "") {
                       // No IS*N provided... skip the record (signal error?)
                        continue;
                    } else {
                        $_datatype = "Book";
                    }
                } else {
                    $_datatype = "Journal";
                }
            } else {
                if ($_datatype != "Journal" && $_datatype != "Book")) {
                   // Unrecognized Data_Type... skip the record (signal error?)
                    continue;
                }
            }

           // Get or Create Journal-or-Book entries based on Title / ISSNs / ISBN
            if ($_datatype == "Journal") {
                $journal = self::getJournal($_title,$_ISSN,$_eISSN);
                $_jrnl_id = $journal->id;
                $_book_id = 0;
            } else {    // Book
                $book = self::getBook($_title,$_ISBN);
                $_book_id = $book->id;
                $_jrnl_id = 0;
            }

           // Loop $item->Performance elements and store counts when time-periods match
            foreach ($item->Performance as $perf) {
                if ($perf->Period->Begin_Date == self::$begin  &&
                    $perf->Period->End_Date == self::$end) {
                    foreach ($perf->Instance as $instance) {
                        $ICounts[$instance->Metric_Type] += $instance->Count;
                    }
                }
            }         // foreach performance clause

           // Insert the record
            TRreport::insert(['jrnl_id' => $_jrnl_id, 'book_id' => $_book_id, 'prov_id' => self::$prov,
                  'plat_id' => $platform->id, 'inst_id' => self::$inst, 'yearmon' => self::$yearmon, 'DOI' => $_DOI,
                  'PropID' => $_PropID, 'URI' => $_URI, 'data_type' => $_datatype, 'section_type' => $_sectiontype,
                  'YOP' => $_YOP, 'access_type' => $_accesstype, 'access_method' => $_accessmethod,
                  'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                  'total_item_requests' => $ICounts['Total_Item_Requests'],
                  'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                  'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                  'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                  'unique_title_requests' => $ICounts['Unique_Title_Requests'],
                  'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License']]);

           // Send a record to the output file
            if (self::$out_csv != "") {
                $output_line = "\"" . $_title . "\",\"" . $_platform . "\"," . $_DOI . "\",\"" . $propID .
                               "\",\"" . $_ISBN . "\",\"" . $_ISSN . "\",\"" . $_eISSN . "\",\"" . $_URI .
                               "\",\"" . $_datatype . "\",\"" . $_sectiontype . "\",\"" . $_YOP .
                               "\",\"" . $_accesstype . "\",\"" . $_accessmethod . "\",";
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
   * Parse and save json data for a COUNTER-5 DR master report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or error-description)
   */
    public static function DR($json_report)
    {
       // Setup array to hold per-item counts
        $ICounts = ['Searches_Automated' => 0, 'Searches_Federated' => 0, 'Searches_Regular' =>0,
                    'Total_Item_Investigations' => 0, 'Total_Item_Requests' => 0,
                    'Unique_Item_Investigations' => 0, 'Unique_Item_Requests' => 0,
                    'Unique_Title_Investigations' => 0, 'Unique_Title_Requests' => 0,
                    'Limit_Exceeded' => 0, 'No_License' =>0];
        $_metric_keys = array_keys($ICounts);
        $_metric_count = count($ICounts);

       // Decode JSON and put header and report records into variables
        $header = $json_report->Report_Header;
        $ReportItems = $json_report->Report_Items;

       // Put out report header and total rows
        if (self::$out_csv != "") {
            $csv_file = fopen(self::$out_csv, 'w');
            fwrite($csv_file, "Database Master Report (R5)\n");
            fwrite($csv_file, "Publisher: " . (string) $header->Publisher . "\n");
            fwrite($csv_file, "Institution: " . (string) $header->Institution_Name . "\n");
            fwrite($csv_file, "CustomerID: " . (string) $header->Customer_ID . "\n");
            fwrite($csv_file, "Created: " . (string) $header->Created . "\n");
            fwrite($csv_file, "Created By: " . (string) $header->Created_By . "\n");
            fwrite($csv_file, "Period covered by Report: ");
            fwrite($csv_file, self::$begin . " to " . self::$end . "\n");
            $colhdr  = "\nDatabase,Platform,Data Type,Access Method,Automated Searches,Federated Searches,";
            $colhdr .= "Regular Searches,Total Item Investigations,Total Item Requests,Unique Item Investigations,";
            $colhdr .= "Unique Item Requests,Unique Title Investigations,Unique Title Requests";
            $colhdr .= "Limit Exceeded,No License\n";
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

            // Database is required; if Null, skip the item.
             $_database = (isset($item->Database)) ? $item->Database : "";
             if ($_database == "") {
                 continue;
             }
            // Get or create DataBase for this item.
             $database = self::getDataBase($item->Database);

            // Pick up the optional attributes
             $_datatype = (isset($item->Data_Type)) ? $item->Data_Type : "";
             $_accessmethod = (isset($item->Access_Method)) ? $item->Access_Method : "";

            // Loop $item->Performance elements and store counts when time-periods match
             foreach ($item->Performance as $perf) {
                 if ($perf->Period->Begin_Date == self::$begin  &&
                     $perf->Period->End_Date == self::$end) {
                     foreach ($perf->Instance as $instance) {
                         $ICounts[$instance->Metric_Type] += $instance->Count;
                     }
                 }
             }         // foreach performance clause

            // Insert the record
             DRreport::insert(['db_id' => $database->id, 'prov_id' => self::$prov, 'plat_id' => $platform->id,
                       'inst_id' => self::$inst, 'yearmon' => self::$yearmon,
                       'data_type' => $_datatype, 'access_method' => $_accessmethod,
                       'searches_automated' => $ICounts['Searches_Automated'],
                       'searches_federated' => $ICounts['Searches_Federated'],
                       'searches_regular' => $ICounts['Searches_Regular'],
                       'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                       'total_item_requests' => $ICounts['Total_Item_Requests'],
                       'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                       'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                       'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                       'unique_title_requests' => $ICounts['Unique_Title_Requests'],
                       'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License']]);

             // Send a record to the output file
              if (self::$out_csv != "") {
                  $output_line = "\"" . $_database . "\",\"" . $_platform . "\",\"" . $_datatype . "\",\"" .
                                 $_accessmethod . "\"";
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
   * Parse and save json data for a COUNTER-5 PR master report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or error-description)
   */
    public static function PR($json_report)
    {
       // Setup array to hold per-item counts
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
            fwrite($csv_file, "Publisher: " . (string) $header->Publisher . "\n");
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
            PRreport::insert(['plat_id' => $platform->id, 'prov_id' => self::$prov, 'inst_id' => self::$inst,
                      'yearmon' => self::$yearmon, 'data_type' => $_datatype, 'access_method' => $_accessmethod,
                      'searches_platform' => $ICounts['Searches_Platform'],
                      'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                      'total_item_requests' => $ICounts['Total_Item_Requests'],
                      'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                      'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                      'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                      'unique_title_requests' => $ICounts['Unique_Title_Requests']]);

           // Send a record to the output file
            if (self::$out_csv != "") {
                $output_line = "\"" . $_platform . "\",\"" . $_datatype . "\",\"" . $_accessmethod . "\"";
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
   * Parse and save json data for a COUNTER-5 master IR report. Validation
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
    * Function to find-or-create a Platform by name and return it
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

//     $journal = self::getJournal($item->Title,$item->Print_ISSN,$item->Online_ISSN,$item->Proprietary_ID);
// } else if ($item->Data_Type == "Book") {
//     $book = self::getBook($item->Title,$item->ISBN,$item->Proprietary_ID);

    /**
     * Function to find-or-create a Journal and return it
     *
     * @return Journal
     */
     private static function getJournal($title,$print_issn,$online_issn)
     {
         $_journal = Journal::where('Title', '=', $title)
                        ->orWhere('ISSN', '=', $print_issn)
                        ->orWhere('eISSN', '=', $online_issn)
                        ->first();
         if ($_journal === null) {   // create it
             $_journal = new Journal(['Title' => $title, 'ISSN' => $print_issn,
                                   'eISSN' => $online_issn]);
             $_journal->save();
         }
         return $_journal;
     }

     /**
      * Function to find-or-create a Book and return it
      *
      * @return Book
      */
      private static function getBook($title,$isbn)
      {
          $_book = Book::where('Title', '=', $title)
                         ->orWhere('ISBN', '=', $isbn)
                         ->first();
          if ($_book === null) {   // create it
              $_book = new Book(['Title' => $title, 'ISBN' => $isbn]);
              $_book->save();
          }
          return $_book;
      }

    /**
     * Function to find-or-create a DataBase by name and return it
     *
     * @param  $name
     * @return DataBase
     */
     private static function getDataBase($name)
     {
         $_data_base = DataBase::where('name', '=', $name)->first();
         if ($_data_base === null) {   // create it
             $_data_base = new DataBase(['name' => $name]);
             $_data_base->save();
         }
         return $_data_base;
     }

}
