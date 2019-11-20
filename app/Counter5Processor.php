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

           // Get Publisher
            $publisher_id = null;
            $_publisher = (isset($item->Publisher)) ? $item->Publisher : "";
            if ($_publisher != "") {
               // Get or create Publisher for this item
                $publisher = Publisher::firstOrCreate(['name' => $_publisher]);
                $publisher_id = $publisher->id;
            }

           // Get Platform
            $platform_id = null;
            $_platform = (isset($item->Platform)) ? $item->Platform : "";
            if ($_platform != "") {
               // Get or create Platform for this item.
                $platform = Platform::firstOrCreate(['name' => $_platform]);
                $platform_id = $platform->id;
            }

           // Initialize variables for Title and Item_ID fields
           // We'll use these as a basis for trying to match against known titles
            $_title = (isset($item->Title)) ? $item->Title : "";
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

            $accesstype_id = null;
            if (isset($item->Access_Type)) {
                if ($item->Access_Type != "") {
                    $accesstype = AccessType::firstOrCreate(['name' => $item->Access_Type]);
                    $accesstype_id = $accesstype->id;
                }
            }

            $accessmethod_id = null;
            if (isset($item->Access_Method)) {
                if ($item->Access_Method != "") {
                    $accessmethod = AccessMethod::firstOrCreate(['name' => $item->Access_Method]);
                    $accessmethod_id = $accessmethod->id;
                }
            }

            $sectiontype_id = null;
            if (isset($item->Section_Type)) {
                if ($item->Section_Type != "") {
                    $sectiontype = SectionType::firstOrCreate(['name' => $item->Section_Type]);
                    $sectiontype_id = $sectiontype->id;
                }
            }

            $datatype_id = null;
            if (isset($item->Data_Type)) {
                if ($item->Data_Type != "") {
                    $datatype = DataType::firstOrCreate(['name' => $item->Data_Type]);
                    $datatype_id = $datatype->id;
                }
            }

           // Get or Create Journal-or-Book entries based on Title and Proprietary_ID
           // Store the other Item_ID fields in the Journal/Book table
           // ALL 3 sections need re-working ... live w/ this for the time-being
            $jrnl_id = null;
            $book_id = null;
            $item_id = null;
            if ($datatype->name == "Journal") {
                $journal = Journal::firstOrCreate(['Title' => $_title, 'PropID' => $_PropID],
                                         ['ISSN' => $_ISSN, 'eISSN' => $_eISSN, 'DOI' => $_DOI, 'URI' => $_URI]);
                $jrnl_id = $journal->id;
               // If existing journal fields are null try to update them
                $save_it = false;
                if ($journal->ISSN == "" && $_ISSN != "") {
                    $save_it = true;
                    $journal->ISSN = $_ISSN;
                }
                if ($journal->eISSN == "" && $_eISSN != "") {
                    $save_it = true;
                    $journal->eISSN = $_eISSN;
                }
                if ($journal->DOI == "" && $_DOI != "") {
                    $save_it = true;
                    $journal->DOI = $_DOI;
                }
                if ($journal->URI == "" && $_URI != "") {
                    $save_it = true;
                    $journal->URI = $_URI;
                }
                if ($save_it) $journal->save();
            } else if ($datatype->name == "Book") {
                $book = Book::firstOrCreate(['Title' => $_title, 'PropID' => $_PropID],
                                      ['ISBN' => $_ISBN, 'DOI' => $_DOI, 'URI' => $_URI]);
                $book_id = $book->id;
               // If existing book fields are null try to update them
                $save_it = false;
                if ($journal->ISBN == "" && $_ISBN != "") {
                    $save_it = true;
                    $book->ISBN = $_ISBN;
                }
                if ($book->DOI == "" && $_DOI != "") {
                    $save_it = true;
                    $book->DOI = $_DOI;
                }
                if ($book->URI == "" && $_URI != "") {
                    $save_it = true;
                    $book->URI = $_URI;
                }
                if ($save_it) $book->save();
            } else {   // Not a Journal or Book, treat as an Item
                $item = Item::firstOrCreate(['Title' => $_title, 'PropID' => $_PropID],
                                      ['DOI' => $_DOI, 'URI' => $_URI, 'YOP' => $_YOP, 'ISSN' => $_ISSN,
                                       'ISBN' => $_ISBN, 'eISSN' => $_eISSN]);
                $item_id = $item->id;
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
            TitleReport::insert(['jrnl_id' => $jrnl_id, 'book_id' => $book_id, 'item_id' => $item_id,
                  'prov_id' => self::$prov, 'publisher_id' => $publisher_id, 'plat_id' => $platform_id,
                  'inst_id' => self::$inst, 'yearmon' => self::$yearmon, 'datatype_id' => $datatype_id,
                  'sectiontype_id' => $sectiontype_id, 'YOP' => $_YOP, 'accesstype_id' => $accesstype_id,
                  'accessmethod_id' => $accessmethod_id,
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
           // Database is required; if Null, skip the record.
            $_database = (isset($item->Database)) ? $item->Database : "";
            if ($_database == "") continue;
            $database = DataBase::firstOrCreate(['name' => $_database]);

           // Get Publisher
            $publisher_id = null;
            $_publisher = (isset($item->Publisher)) ? $item->Publisher : "";
            if ($_publisher != "") {
               // Get or create Publisher for this item
                $publisher = Publisher::firstOrCreate(['name' => $_publisher]);
                $publisher_id = $publisher->id;
            }

           // Get Platform
            $platform_id = null;
            $_platform = (isset($item->Platform)) ? $item->Platform : "";
            if ($_platform != "") {
               // Get or create Platform for this item.
                $platform = Platform::firstOrCreate(['name' => $_platform]);
                $platform_id = $platform->id;
            }

           // Get DOI for this item, and update model if necessary
            $_PropID = "";
            if (isset($item->PropID)) {
                foreach ( $item->PropID as $_id ) {
                    if ($_id->Type == "Proprietary") $_PropID = $_id->Value;
                }
                if ($_PropID!=$database->PropID) {
                    $database->PropID = $_PropID;
                    $database->save();
                }
            }

           // Pick up the optional attributes
            $datatype_id = null;
            if (isset($item->Data_Type)) {
                $datatype = DataType::firstOrCreate(['name' => $item->Data_Type]);
                $datatype_id = $datatype->id;
            }

            $accessmethod_id = null;
            if (isset($item->Access_Method)) {
                $accessmethod = AccessMethod::firstOrCreate(['name' => $item->Access_Method]);
                $accessmethod_id = $accessmethod->id;
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

            DatabaseReport::insert(['db_id' => $database->id, 'prov_id' => self::$prov, 'plat_id' => $platform_id,
                       'publisher_id' => $publisher_id, 'inst_id' => self::$inst, 'yearmon' => self::$yearmon,
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
            if ($_platform == "") continue;
            $platform = Platform::firstOrCreate(['name' => $item->Platform]);

           // Pick up the optional attributes
            $datatype_id = null;
            if (isset($item->Data_Type)) {
                if ($item->Data_Type != "") {
                    $datatype = DataType::firstOrCreate(['name' => $item->Data_Type]);
                    $datatype_id = $datatype->id;
                }
            }

            $accessmethod_id = null;
            if (isset($item->Access_Method)) {
                if ($item->Access_Method != "") {
                    $accessmethod = AccessMethod::firstOrCreate(['name' => $item->Access_Method]);
                    $accessmethod_id = $accessmethod->id;
                }
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
