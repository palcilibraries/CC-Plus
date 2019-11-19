<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
use App\Provider;
use App\Platform;
use App\Publisher;
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

  /**
   * Class Constructor and setting methods
   */
    public function __construct($_prov, $_inst, $_begin, $_end)
    {
        self::$prov = $_prov;
        self::$inst = $_inst;
        self::$begin = $_begin;
        self::$end = $_end;
        self::$yearmon = substr($_begin, 0, 7);
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

       // Loop through all ReportItems
        foreach ($ReportItems as $item) {
            // Put Item, Publisher, and Platform fields into variables
             $_title = (isset($item->Title)) ? $item->Title : "";
             $_publisher = (isset($item->Publisher)) ? $item->Publisher : "";
             $_platform = (isset($item->Platform)) ? $item->Platform : "";

            // If title, publisher or platform are null skip the item.
             if ($_title == "" || $_publisher == "" || $_platform == "") {
                 continue;
             }

             // Get or create Platform for this item.
              $platform = Platform::firstOrCreate(['name' => $_platform]);

            // Allow a null value for Publisher_ID
             $_publisher_id = (isset($item->Publisher_ID)) ? $item->Publisher_ID : "";
            // Get or create Publisher for this item
             $publisher = Publisher::firstOrCreate(['name' => $_publisher], ['Publisher_ID' => $_publisher_id]);
            // Update Publisher_ID if saved value is null and this item has a value
             if ( ($publisher->Publisher_ID=="") && ($_publisher_id!="") ) {
                 $publisher->Publisher_ID = $_publisher_id;
                 $publisher->save();
             }

            // Database is required; if Null, skip the item.
             $_database = (isset($item->Database)) ? $item->Database : "";
            if ($_database == "") {
                continue;
            }

           // Initialize variables for Item_ID fields and set from the record
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

           // Data_Type is optional... if null, try to solve what this is based on IS*N field(s)
           // (If ISBN *and* one of ISSN/eISSN are present, treat it as a Journal)
           // Since this is a TR report... if its neither a Journal OR a Book, skip it.
            $_data_type = (isset($item->Data_Type)) ? $item->Data_Type : "";
            if ($_data_type == "") {
                if ($_ISSN == "" && $_eISSN = "") {
                    if ($_ISBN == "") {
                       // No IS*N provided... skip the record
                        continue;
                    } else {
                        $_data_type = "Book";
                    }
                } else {
                    $_data_type = "Journal";
                }
            }
            if ($_data_type != "Journal" && $_data_type != "Book") {
                // Unrecognized Data_Type... skip the record
                 continue;
             }

           // Get or Create Journal-or-Book entries based on Title and Proprietary_ID
           // Store the other Item_ID fields in the Journal/Book table
            if ($_data_type == "Journal") {
                $journal = firstOrCreate(['Title' => $_title, 'PropID' => $_PropID],
                                         ['ISSN' => $_ISSN, 'eISSN' => $_eISSN, 'DOI' => $_DOI, 'URI' => $_URI]);
                $_jrnl_id = $journal->id;
                $_book_id = null;
                $datatype_id = $journal_datatype->id;
               // If existing journal fields are null try to update them
                if ($journal->wasRecentlyCreated() === false ) {
                    if ($journal->ISSN == "" && $_ISSN != "") $journal->ISSN = $_ISSN;
                    if ($journal->eISSN == "" && $_eISSN != "") $journal->eISSN = $_eISSN;
                    if ($journal->DOI == "" && $_DOI != "") $journal->DOI = $_DOI;
                    if ($journal->URI == "" && $_URI != "") $journal->URI = $_URI;
                    if ($journal->ISSN != $_ISSN || $journal->eISSN != $_eISSN ||
                        $journal->DOI != $_DOI || $journal->URI != $_URI) $journal->save();
                }
            } else {    // Book
                $book = firstOrCreate(['Title' => $_title, 'PropID' => $_PropID],
                                      ['ISBN' => $_ISBN, 'DOI' => $_DOI, 'URI' => $_URI]);
                $_book_id = $book->id;
                $_jrnl_id = null;
                $datatype_id = $book_datatype->id;
               // If existing book fields are null try to update them
                if ($book->wasRecentlyCreated() === false ) {
                    if ($book->ISBN == "" && $_ISBN != "") $book->ISBN = $_ISBN;
                    if ($book->DOI == "" && $_DOI != "") $book->DOI = $_DOI;
                    if ($book->URI == "" && $_URI != "") $book->URI = $_URI;
                    if ($book->ISBN != $_ISBN || $book->DOI != $_DOI || $book->URI != $_URI) $book->save();
                 }
            }

           // Loop $item->Performance elements and store counts when time-periods match
            foreach ($item->Performance as $perf) {
                if ($perf->Period->Begin_Date == self::$begin  && $perf->Period->End_Date == self::$end) {
                    foreach ($perf->Instance as $instance) {
                        $ICounts[$instance->Metric_Type] += $instance->Count;
                    }
                }
            }         // foreach performance clause

           // Insert the record
            TitleReport::insert(['jrnl_id' => $_jrnl_id, 'book_id' => $_book_id, 'prov_id' => self::$prov,
                  'publisher_id' => $publisher->id, 'plat_id' => $platform->id, 'inst_id' => self::$inst,
                  'yearmon' => self::$yearmon, 'DOI' => $_DOI, 'PropID' => $_PropID, 'URI' => $_URI,
                  'datatype_id' => $datatype_id, 'sectiontype_id' => $sectiontype_id, 'YOP' => $_YOP,
                  'accesstype_id' => $accesstype_id, 'accessmethod_id' => $accessmethod_id,
                  'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                  'total_item_requests' => $ICounts['Total_Item_Requests'],
                  'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                  'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                  'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                  'unique_title_requests' => $ICounts['Unique_Title_Requests'],
                  'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License']]);

           // Reset metric counts
            for ($_m = 0; $_m < $_metric_count; $_m++) {
                $ICounts[$_metric_keys[$_m]] = 0;
            }
        }     // foreach $ReportItems

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

       // Loop through all ReportItems
        foreach ($ReportItems as $item) {
            // Publisher is required; if Null, skip the item.
            // Allow a null value for Publisher_ID
             $_publisher = (isset($item->Publisher)) ? $item->Publisher : "";
             $_publisher_id = (isset($item->Publisher_ID)) ? $item->Publisher_ID : "";
             if ($_publisher == "") {
                 continue;
             }
            // Get or create Publisher for this item
             $publisher = Publisher::firstOrCreate(['name' => $_publisher], ['Publisher_ID' => $_publisher_id]);
            // Update Publisher_ID if saved value is null and this item has a value
             if ( ($publisher->Publisher_ID=="") && ($_publisher_id!="") ) {
                 $publisher->Publisher_ID = $_publisher_id;
                 $publisher->save();
             }

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

            // Get DOI for this item.
             $_PropID = "";
             if (isset($item->PropID)) {
                 foreach ( $item->PropID as $_id ) {
                     if ( $_id->Type == "Proprietary" ) { $_PropID = $_id->Value; }
                 }
             }

            // Get or create DataBase for this item.
             $database = Database::firstOrCreate(['name' => $_database]);

            // Update DOI if necessary
             if ( ($_PropID!="") && ($_PropID!=$database->PropID) ) {
                 $database->PropID = $_PropID;
                 $database->save();
             }

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

           // Reset metric counts
            for ($_m = 0; $_m < $_metric_count; $_m++) {
                $ICounts[$_metric_keys[$_m]] = 0;
            }
        }     // foreach $ReportItems

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

           // Reset metric counts
            for ($_m = 0; $_m < $_metric_count; $_m++) {
                $ICounts[$_metric_keys[$_m]] = 0;
            }
        }     // foreach $ReportItems

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

}
