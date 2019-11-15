<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
use App\Provider;
use App\Platform;
use App\Institution;
use App\PlatformReport;
use App\TitleReport;
use App\DatabaseReport;
use App\ItemReport;
use App\DataType;
use App\AccessMethod;
use App\AccessType;
use App\SectionType;

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
       // Pull datatypes for Book and Journal and store for later
        $book_datatype = DataType::firstOrCreate(['name' => "Book"]);
        $journal_datatype = DataType::firstOrCreate(['name' => "Journal"]);

       // Setup array to hold per-item counts
        $ICounts = ['Total_Item_Investigations' => 0, 'Total_Item_Requests' => 0,
                    'Unique_Item_Investigations' => 0, 'Unique_Item_Requests' => 0,
                    'Unique_Title_Investigations' => 0, 'Unique_Title_Requests' => 0,
                    'Limit_Exceeded' => 0, 'No_License' => 0];
        $_metric_keys = array_keys($ICounts);
        $_metric_count = count($ICounts);

       // Decode JSON and put header and records into variables
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

           // Initialize identifiers
            $_PropID = "";
            $_ISBN = "";
            $_ISSN = "";
            $_eISSN = "";
            $_DOI = "";
            $_URI = "";
            foreach ( $item->Item_ID as $_id ) {
                if ( $_id->Type == "Proprietary" ) { $_PropID = $_id->Value; }
                if ( $_id->Type == "ISBN" ) { $_ISBN = $_id->Value; }
                if ( $_id->Type == "Print_ISSN" ) { $_ISSN = $_id->Value; }
                if ( $_id->Type == "Online_ISSN" ) { $_eISSN = $_id->Value; }
                if ( $_id->Type == "DOI" ) { $_DOI = $_id->Value; }
                if ( $_id->Type == "URI" ) { $_URI = $_id->Value; }
            }

           // Pick up the optional attributes
            $_YOP = (isset($item->YOP)) ? $item->YOP : "";

            if (isset($item->Access_Type)) {
                $accesstype = AccessType::firstOrCreate(['name' => $item->Access_Type]);
                $accesstype_id = $accesstype->id;
                $accesstype_name = $accesstype->name;
            } else {
                $accesstype_id = null;
                $accesstype_name = "";
            }

            if (isset($item->Access_Method)) {
                $accessmethod = AccessMethod::firstOrCreate(['name' => $item->Access_Method]);
                $accessmethod_id = $accessmethod->id;
                $accessmethod_name = $accessmethod->name;
            } else {
                $accessmethod_id = null;
                $accessmethod_name = "";
            }

            if (isset($item->Section_Type)) {
                $sectiontype = SectionType::firstOrCreate(['name' => $item->Section_Type]);
                $sectiontype_id = $sectiontype->id;
                $sectiontype_name = $sectiontype->name;
            } else {
                $sectiontype_id = null;
                $sectiontype_name = "";
            }

           // If title or platform are null skip the item.
            if ($_title == "" || $_platform == "") {
                continue;
            }

           // Get or create Platform for this item.
            $platform = Platform::firstOrCreate(['name' => $_platform]);

           // Data_Type is also optional... if null, try to solve what this is based on IS*N field(s)
           // (If ISBN *and* one of ISSN/eISSN are present, treat it as a Journal)
           // Since this is a TR report... if its neither a Journal OR a Book, skip it.
            $_data_type = (isset($item->Data_Type)) ? $item->Data_Type : "";
            if ($_data_type == "") {
                if ($_ISSN == "" && $_eISSN = "") {
                    if ($_ISBN == "") {
                       // No IS*N provided... skip the record
                        continue;
                    } else {
                        $_data_type = "Book";                    }
                } else {
                    $_data_type = "Journal";                    }
                }
            }
            if ($_data_type != "Journal" && $_data_type != "Book") {
                // Unrecognized Data_Type... skip the record
                 continue;
             }

           // Get or Create Journal-or-Book entries based on Title / ISSNs / ISBN
            if ($_data_type == "Journal") {
                $journal = self::getJournal($_title, $_ISSN, $_eISSN);
                $_jrnl_id = $journal->id;
                $_book_id = null;
                $datatype_id = $journal_datatype->id;
            } else {    // Book
                $book = self::getBook($_title, $_ISBN);
                $_book_id = $book->id;
                $_jrnl_id = null;
                $datatype_id = $book_datatype->id;
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
            TitleReport::insert(['jrnl_id' => $_jrnl_id, 'book_id' => $_book_id, 'prov_id' => self::$prov,
                  'plat_id' => $platform->id, 'inst_id' => self::$inst, 'yearmon' => self::$yearmon,
                  'DOI' => $_DOI, 'PropID' => $_PropID, 'URI' => $_URI, 'datatype_id' => $datatype_id,
                  'sectiontype_id' => $sectiontype_id, 'YOP' => $_YOP, 'accesstype_id' => $accesstype_id,
                  'accessmethod_id' => $accessmethod_id,
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
                               "\",\"" . $_data_type . "\",\"" . $sectiontype_name . "\",\"" . $_YOP .
                               "\",\"" . $accesstype_name . "\",\"" . $accessmethod_name . "\",";
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
        $ICounts = ['Searches_Automated' => 0, 'Searches_Federated' => 0, 'Searches_Regular' => 0,
                    'Total_Item_Investigations' => 0, 'Total_Item_Requests' => 0,
                    'Unique_Item_Investigations' => 0, 'Unique_Item_Requests' => 0,
                    'Unique_Title_Investigations' => 0, 'Unique_Title_Requests' => 0,
                    'Limit_Exceeded' => 0, 'No_License' => 0];
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
             $platform = Platform::firstOrCreate(['name' => $_platform]);

            // Database is required; if Null, skip the item.
             $_database = (isset($item->Database)) ? $item->Database : "";
            if ($_database == "") {
                continue;
            }
            // Get or create DataBase for this item.
             $database = Database::firstOrCreate(['name' => $_database]);

            // Pick up the optional attributes
            if (isset($item->Data_Type)) {
                $datatype = DataType::firstOrCreate(['name' => $item->Data_Type]);
                $datatype_id = $datatype->id;
                $datatype_name = $datatype->name;
            } else {
                $datatype_id = null;
                $datatype_name = "";
            }

            if (isset($item->Access_Method)) {
                $accessmethod = AccessMethod::firstOrCreate(['name' => $item->Access_Method]);
                $accessmethod_id = $accessmethod->id;
                $accessmethod_name = $accessmethod->name;
            } else {
                $accessmethod_id = null;
                $accessmethod_name = "";
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
             DatabaseReport::insert(['db_id' => $database->id, 'prov_id' => self::$prov, 'plat_id' => $platform->id,
                       'inst_id' => self::$inst, 'yearmon' => self::$yearmon,
                       'datatype_id' => $datatype_id, 'accessmethod_id' => $accessmethod_id,
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
                $output_line = "\"" . $_database . "\",\"" . $_platform . "\",\"" . $datatype_name . "\",\"" .
                               $accessmethod_name . "\"";
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
            $platform = Platform::firstOrCreate(['name' => $item->Platform]);

            // Pick up the optional attributes
            if (isset($item->Data_Type)) {
                $datatype = DataType::firstOrCreate(['name' => $item->Data_Type]);
                $datatype_id = $datatype->id;
                $datatype_name = $datatype->name;
            } else {
                $datatype_id = null;
                $datatype_name = "";
            }

            if (isset($item->Access_Method)) {
                $accessmethod = AccessMethod::firstOrCreate(['name' => $item->Access_Method]);
                $accessmethod_id = $accessmethod->id;
                $accessmethod_name = $accessmethod->name;
            } else {
                $accessmethod_id = null;
                $accessmethod_name = "";
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
            PlatformReport::insert(['plat_id' => $platform->id, 'prov_id' => self::$prov, 'inst_id' => self::$inst,
                      'yearmon' => self::$yearmon, 'datatype_id' => $datatype_id, 'accessmethod_id' => $accessmethod_id,
                      'searches_platform' => $ICounts['Searches_Platform'],
                      'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                      'total_item_requests' => $ICounts['Total_Item_Requests'],
                      'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                      'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                      'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                      'unique_title_requests' => $ICounts['Unique_Title_Requests']]);

           // Send a record to the output file
            if (self::$out_csv != "") {
                $output_line = "\"" . $_platform . "\",\"" . $datatype_name . "\",\"" . $accessmethod_name . "\"";
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
     * Function to find-or-create a Journal and return it
     * firstOrCreate would be nice, but we only want to create when
     * NONE of the 3 fields (title, issn, or eissn) match.
     *
     * @return Journal
     */
    private static function getJournal($title, $print_issn, $online_issn)
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
      * firstOrCreate would be nice, but we only want to create if
      * neither of the 2 fields (title, or isbn) match.
      *
      * @return Book
      */
    private static function getBook($title, $isbn)
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

}
