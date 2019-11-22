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
        foreach ($ReportItems as $reportitem) {

           // If there's $reportitem-Performance, there's nothing to save/store
            // if (!isset($reportitem->Performance)) {continue;}

           // Get Publisher
            $publisher_id = null;
            $_publisher = (isset($reportitem->Publisher)) ? $reportitem->Publisher : "";
            if ($_publisher != "") {
               // Get or create Publisher for this item
                $publisher = Publisher::firstOrCreate(['name' => $_publisher]);
                $publisher_id = $publisher->id;
            }

           // Get Platform
            $platform_id = null;
            $_platform = (isset($reportitem->Platform)) ? $reportitem->Platform : "";
            if ($_platform != "") {
               // Get or create Platform for this item.
                $platform = Platform::firstOrCreate(['name' => $_platform]);
                $platform_id = $platform->id;
            }

           // Initialize variables for Title and Item_ID fields
           // We'll use these as a basis for trying to match against known titles
            $_title = (isset($reportitem->Title)) ? $reportitem->Title : "";
            $_PropID = "";
            $_ISBN = "";
            $_ISSN = "";
            $_eISSN = "";
            $_DOI = "";
            $_URI = "";
            foreach ( $reportitem->Item_ID as $_id ) {
                if ( $_id->Type == "Proprietary" ) { $_PropID = $_id->Value; }
                if ( $_id->Type == "ISBN" ) { $_ISBN = $_id->Value; }
                if ( $_id->Type == "Print_ISSN" ) { $_ISSN = $_id->Value; }
                if ( $_id->Type == "Online_ISSN" ) { $_eISSN = $_id->Value; }
                if ( $_id->Type == "DOI" ) { $_DOI = $_id->Value; }
                if ( $_id->Type == "URI" ) { $_URI = $_id->Value; }
            }

           // Pick up the optional attributes
            $_YOP = (isset($reportitem->YOP)) ? $reportitem->YOP : "";

            $accesstype_id = null;
            if (isset($reportitem->Access_Type)) {
                if ($reportitem->Access_Type != "") {
                    $accesstype = AccessType::firstOrCreate(['name' => $reportitem->Access_Type]);
                    $accesstype_id = $accesstype->id;
                }
            }

            $accessmethod_id = null;
            if (isset($reportitem->Access_Method)) {
                if ($reportitem->Access_Method != "") {
                    $accessmethod = AccessMethod::firstOrCreate(['name' => $reportitem->Access_Method]);
                    $accessmethod_id = $accessmethod->id;
                }
            }

            $sectiontype_id = null;
            if (isset($reportitem->Section_Type)) {
                if ($reportitem->Section_Type != "") {
                    $sectiontype = SectionType::firstOrCreate(['name' => $reportitem->Section_Type]);
                    $sectiontype_id = $sectiontype->id;
                }
            }

            $datatype_id = null;
            if (isset($reportitem->Data_Type)) {
                if ($reportitem->Data_Type != "") {
                    $datatype = DataType::firstOrCreate(['name' => $reportitem->Data_Type]);
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
                $journal = self::journalFindOrCreate($_title, $_PropID, $_ISSN, $_eISSN, $_DOI, $_URI);
                if (is_null($journal)) continue;
                $jrnl_id = $journal->id;

            } else if ($datatype->name == "Book") {
                $book = self::bookFindOrCreate($_title, $_PropID, $_ISBN, $_DOI, $_URI);
                if (is_null($book)) continue;
                $book_id = $book->id;

            } else {   // Not a Journal or Book, treat as an Item
                $item = Item::firstOrCreate(['Title' => $_title, 'PropID' => $_PropID],
                                      ['DOI' => $_DOI, 'URI' => $_URI, 'YOP' => $_YOP, 'ISSN' => $_ISSN,
                                       'ISBN' => $_ISBN, 'eISSN' => $_eISSN]);
                $item_id = $item->id;
            }

           // Loop $reportitem->Performance elements and store counts when time-periods match
            foreach ($reportitem->Performance as $perf) {
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
        foreach ($ReportItems as $reportitem) {
           // Database is required; if Null, skip the record.
            $_database = (isset($reportitem->Database)) ? $reportitem->Database : "";
            if ($_database == "") continue;
            $database = DataBase::firstOrCreate(['name' => $_database]);

           // Get Publisher
            $publisher_id = null;
            $_publisher = (isset($reportitem->Publisher)) ? $reportitem->Publisher : "";
            if ($_publisher != "") {
               // Get or create Publisher for this item
                $publisher = Publisher::firstOrCreate(['name' => $_publisher]);
                $publisher_id = $publisher->id;
            }

           // Get Platform
            $platform_id = null;
            $_platform = (isset($reportitem->Platform)) ? $reportitem->Platform : "";
            if ($_platform != "") {
               // Get or create Platform for this item.
                $platform = Platform::firstOrCreate(['name' => $_platform]);
                $platform_id = $platform->id;
            }

           // Get DOI for this item, and update model if necessary
            $_PropID = "";
            if (isset($reportitem->PropID)) {
                foreach ( $reportitem->PropID as $_id ) {
                    if ($_id->Type == "Proprietary") $_PropID = $_id->Value;
                }
                if ($_PropID!=$database->PropID) {
                    $database->PropID = $_PropID;
                    $database->save();
                }
            }

           // Pick up the optional attributes
            $datatype_id = null;
            if (isset($reportitem->Data_Type)) {
                $datatype = DataType::firstOrCreate(['name' => $reportitem->Data_Type]);
                $datatype_id = $datatype->id;
            }

            $accessmethod_id = null;
            if (isset($reportitem->Access_Method)) {
                $accessmethod = AccessMethod::firstOrCreate(['name' => $reportitem->Access_Method]);
                $accessmethod_id = $accessmethod->id;
            }

           // Loop $reportitem->Performance elements and store counts when time-periods match
            foreach ($reportitem->Performance as $perf) {
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
        foreach ($ReportItems as $reportitem) {
           // Platform is required; if Null, skip the item.
            $_platform = (isset($reportitem->Platform)) ? $reportitem->Platform : "";
            if ($_platform == "") continue;
            $platform = Platform::firstOrCreate(['name' => $reportitem->Platform]);

           // Pick up the optional attributes
            $datatype_id = null;
            if (isset($reportitem->Data_Type)) {
                if ($reportitem->Data_Type != "") {
                    $datatype = DataType::firstOrCreate(['name' => $reportitem->Data_Type]);
                    $datatype_id = $datatype->id;
                }
            }

            $accessmethod_id = null;
            if (isset($reportitem->Access_Method)) {
                if ($reportitem->Access_Method != "") {
                    $accessmethod = AccessMethod::firstOrCreate(['name' => $reportitem->Access_Method]);
                    $accessmethod_id = $accessmethod->id;
                }
            }

           // Loop $reportitem->Performance elements and store counts when time-periods match
            foreach ($reportitem->Performance as $perf) {
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

    /**
     * Function to find-or-create a Journal in/from the global table
     *
     * @param $title, $propID, $issn, $eissn, $doi, $uri
     * @return Journal or null for errors/missing input
     *
     * $journal = self::journalFindOrCreate($title, $propID, $issn, $eissn, $doi, $uri);
     */
    private static function journalFindOrCreate($title, $propID, $issn, $eissn, $doi, $uri)
    {

        if ($title=="" && $propID="" && $issn=="" && $eissn=="" && $doi=="" && $uri=="") return null;
       // Get any potential matches
        $matches = Journal::where([['Title', '<>',''],['Title', '=',$title]])->
                          orWhere([['PropID','<>',''],['PropID','=',$propID]])->
                          orWhere([['eISSN', '<>',''],['eISSN', '=',$eissn]])->
                          orWhere([['ISSN',  '<>',''],['ISSN',  '=',$issn]])->
                          orWhere([['DOI',   '<>',''],['DOI',   '=',$doi]])->
                          orWhere([['URI',   '<>',''],['URI',   '=',$uri]])->get();

       // Loop through all the possibles
        $save_it = false;
        foreach ($matches as $journal) {

           // If title matches and other input fields are null, we're done
            if ($title!="" && $title==$journal->Title &&
                $propID=="" && $issn=="" && $eissn=="" && $doi=="" && $uri=="") return $journal;
           // If URI matches and other input fields are null, we're done
            if ($uri!="" && $uri==$journal->URI &&
                $title=="" && $propID=="" && $issn=="" && $eissn=="" && $doi=="") return $journal;

           // Test the remaining identifiers - except URI. If match is found, update fields in the
           // model that we have values for.
            if (($propID!="" && $journal->PropID==$propID) || ($doi!="" && $journal->DOI==$doi) ||
                ($issn!="" && $journal->ISSN==$issn) || ($eissn!="" && $journal->eISSN==$eissn)) {

               // Check matched fields, don't overwrite non-null model values with null
                if ($title!="" && $journal->Title!=$title) {
                    $save_it = true;
                    $journal->Title = $title;
                }
                if ($propID!="" && $journal->PropID!=$propID) {
                    $save_it = true;
                    $journal->PropID = $propID;
                }
                if ($doi!="" && $journal->DOI!=$doi) {
                    $save_it = true;
                    $journal->DOI = $doi;
                }
                if ($issn!="" && $journal->ISSN!=$issn) {
                    $save_it = true;
                    $journal->ISSN = $issn;
                }
                if ($eissn!="" && $journal->eISSN!=$eissn) {
                    $save_it = true;
                    $journal->eISSN = $eissn;
                }
                if ($uri!="" && $journal->URI!=$uri) {
                    $save_it = true;
                    $journal->URI = $uri;
                }
                if ($save_it) $journal->save();
                return $journal;
            }
        }

       // If we get here, create a new record
        $journal = new Journal(['Title' => $title, 'ISSN' => $issn, 'eISSN' => $eissn, 'DOI' => $doi,
                                'PropID' => $propID, 'URI' =>$uri]);
        $journal->save();
        return $journal;
    }

    /**
     * Function to find-or-create a Book in/from the global table
     *
     * @param $title, $propID, $isbn, $doi, $uri
     * @return Book or null for errors/missing input
     *
     *    $book = self::bookFindOrCreate($title, $propID, $isbn, $doi, $uri);
     */
    private static function bookFindOrCreate($title, $propID, $isbn, $doi, $uri)
    {
        if ($title=="" && $propID="" && $isbn=="" && $doi=="" && $uri=="") return null;
       // Get any potential matches
        $matches = Book::where([['Title', '<>',''],['Title', '=',$title]])->
                       orWhere([['PropID','<>',''],['PropID','=',$propID]])->
                       orWhere([['ISBN',  '<>',''],['ISBN',  '=',$isbn]])->
                       orWhere([['DOI',   '<>',''],['DOI',   '=',$doi]])->
                       orWhere([['URI',   '<>',''],['URI',   '=',$uri]])->get();

       // Loop through all the possibles
        $save_it = false;
        foreach ($matches as $book) {
            // If title matches and other input fields are null, we're done
             if ($title!="" && $title==$book->Title &&
                 $propID=="" && $isbn=="" && $doi=="" && $uri=="") return $book;
            // If URI matches and other input fields are null, we're done
             if ($uri!="" && $uri==$book->URI &&
                 $title=="" && $propID=="" && $isbn=="" && $doi=="") return $book;

            // Test the remaining identifiers - except URI. If match is found, update fields in the
            // model that we have values for.
             if (($propID!="" && $book->PropID==$propID) || ($doi!="" && $book->DOI==$doi) ||
                 ($isbn!="" && $book->ISBN==$isbn)) {

                // Check matched fields, don't overwrite non-null model values with null
                 if ($title!="" && $book->Title!=$title) {
                     $save_it = true;
                     $book->Title = $title;
                 }
                 if ($propID!="" && $book->PropID!=$propID) {
                     $save_it = true;
                     $book->PropID = $propID;
                 }
                 if ($doi!="" && $book->DOI!=$doi) {
                     $save_it = true;
                     $book->DOI = $doi;
                 }
                 if ($isbn!="" && $book->ISBN!=$isbn) {
                     $save_it = true;
                     $book->ISBN = $isbn;
                 }
                 if ($uri!="" && $book->URI!=$uri) {
                     $save_it = true;
                     $book->URI = $uri;
                 }
                 if ($save_it) $book->save();
                 return $book;
             }
         }

       // If no match, create it
        $book = new Book(['Title' => $title, 'ISBN' => $isbn, 'DOI' => $doi, 'PropID' => $propID, 'URI' =>$uri]);
        $book->save();
        return $book;
    }

}
