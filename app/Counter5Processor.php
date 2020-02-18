<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class Counter5Processor extends Model
{
    private static $prov;
    private static $inst;
    private static $begin;
    private static $end;
    private static $yearmon;
    private static $now;
    private static $replace;

  /**
   * Class Constructor and setting methods
   */
    public function __construct($_prov, $_inst, $_begin, $_end, $_replace = false)
    {
        self::$prov = $_prov;
        self::$inst = $_inst;
        self::$begin = $_begin;
        self::$end = $_end;
        self::$yearmon = substr($_begin, 0, 7);
        $curTime = new \DateTime();
        self::$now = $curTime->format("Y-m-d H:i:s");
        self::$replace = $_replace;
    }

  /**
   * Parse and save json data for a COUNTER-5 TR report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or "Failed")
   */
    public static function TR($json_report)
    {
        // If $replace flag is ON, clear out existing records first
        if (self::$replace) {
            TitleReport::where([['prov_id','=',self::$prov],
                                ['inst_id','=',self::$inst],
                                ['yearmon','=',self::$yearmon]])->delete();
        }

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
           // Get Publisher
            $publisher_id = (isset($reportitem->Publisher)) ? self::getPublisher($reportitem->Publisher) : null;

           // Get Platform
            $platform_id = (isset($reportitem->Platform)) ? self::getPlatform($reportitem->Platform) : null;

           // Get Title and Item_ID fields
            $_title = (isset($reportitem->Title)) ? substr($reportitem->Title, 0, 256) : "";

           // If no Title or Item_ID skip the item..
            if ($_title == "" && !isset($reportitem->Item_ID)) {
                continue;
            }
            $_item_id = (isset($reportitem->Item_ID)) ? $reportitem->Item_ID : array();
            $Item_ID = self::itemIDValues($_item_id);

           // Pick up the optional attributes
            $_YOP = (isset($reportitem->YOP)) ? $reportitem->YOP : "";
            $accesstype_id = (isset($reportitem->Access_Type)) ? self::getAccessType($reportitem->Access_Type)
                                                                 : null;
            $accessmethod_id = (isset($reportitem->Access_Method)) ? self::getAccessMethod($reportitem->Access_Method)
                                                                   : 1;
            $sectiontype_id = (isset($reportitem->Section_Type)) ? self::getSectionType($reportitem->Section_Type)
                                                                 : null;
            $datatype = (isset($reportitem->Data_Type)) ? self::getDataType($reportitem->Data_Type)
                                                        : self::getDataType("Unknown");

           // Get or Create Journal-or-Book entries based on Title and Proprietary_ID
           // Store the other Item_ID fields in the Journal/Book table
           // ALL 3 sections need re-working ... live w/ this for the time-being
            $jrnl_id = null;
            $book_id = null;
            $item_id = null;
            if ($datatype->name == "Journal") {
                $journal = self::journalFindOrCreate(
                    $_title,
                    $Item_ID['PropID'],
                    $Item_ID['ISSN'],
                    $Item_ID['eISSN'],
                    $Item_ID['DOI'],
                    $Item_ID['URI']
                );
                if (is_null($journal)) {
                    continue;
                }
                $jrnl_id = $journal->id;
            } elseif ($datatype->name == "Book") {
                $book = self::bookFindOrCreate(
                    $_title,
                    $Item_ID['PropID'],
                    $Item_ID['ISBN'],
                    $Item_ID['DOI'],
                    $Item_ID['URI']
                );
                if (is_null($book)) {
                    continue;
                }
                $book_id = $book->id;
            } else {   // Not a Journal or Book, treat as an Item
                $item = self::itemFindOrCreate(
                    $_title,
                    $Item_ID['PropID'],
                    $Item_ID['ISSN'],
                    $Item_ID['eISSN'],
                    $Item_ID['ISBN'],
                    $Item_ID['DOI'],
                    $Item_ID['URI']
                );
                if (is_null($item)) {
                    continue;
                }
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
                  'inst_id' => self::$inst, 'yearmon' => self::$yearmon, 'datatype_id' => $datatype->id,
                  'sectiontype_id' => $sectiontype_id, 'YOP' => $_YOP, 'accesstype_id' => $accesstype_id,
                  'accessmethod_id' => $accessmethod_id,
                  'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                  'total_item_requests' => $ICounts['Total_Item_Requests'],
                  'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                  'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                  'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                  'unique_title_requests' => $ICounts['Unique_Title_Requests'],
                  'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License'],
                  'created_at' => self::$now]);

           // Reset metric counts
            for ($_m = 0; $_m < $_metric_count; $_m++) {
                $ICounts[$_metric_keys[$_m]] = 0;
            }
        }     // foreach $ReportItems

        return 'Success';
    }

  /**
   * Parse and save json data for a COUNTER-5 DR master report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or "Failed")
   */
    public static function DR($json_report)
    {
        // If $replace flag is ON, clear out existing records first
        if (self::$replace) {
            DatabaseReport::where([['prov_id','=',self::$prov],
                                   ['inst_id','=',self::$inst],
                                   ['yearmon','=',self::$yearmon]])->delete();
        }

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
            if ($_database == "") {
                continue;
            }
            $database = DataBase::firstOrCreate(['name' => $_database]);

            // Get Publisher
             $publisher_id = (isset($reportitem->Publisher)) ? self::getPublisher($reportitem->Publisher) : null;

            // Get Platform
             $platform_id = (isset($reportitem->Platform)) ? self::getPlatform($reportitem->Platform) : null;

           // Get PropID for this item, and update model if necessary
            $_item_id = (isset($reportitem->Item_ID)) ? $reportitem->Item_ID : array();
            $Item_ID = self::itemIDValues($_item_id);
            if ($Item_ID['PropID'] != $database->PropID) {
                $database->PropID = $Item_ID['PropID'];
                $database->save();
            }

           // Pick up the optional attributes
            $accessmethod_id = (isset($reportitem->Access_Method)) ? self::getAccessMethod($reportitem->Access_Method)
                                                                   : 1;
            $datatype = (isset($reportitem->Data_Type)) ? self::getDataType($reportitem->Data_Type)
                                                        : self::getDataType("Unknown");

           // Loop $reportitem->Performance elements and store counts when time-periods match
            foreach ($reportitem->Performance as $perf) {
                if ($perf->Period->Begin_Date == self::$begin && $perf->Period->End_Date == self::$end) {
                    foreach ($perf->Instance as $instance) {
                        $ICounts[$instance->Metric_Type] += $instance->Count;
                    }
                }
            }         // foreach performance clause

            DatabaseReport::insert(['db_id' => $database->id, 'prov_id' => self::$prov, 'plat_id' => $platform_id,
                       'publisher_id' => $publisher_id, 'inst_id' => self::$inst, 'yearmon' => self::$yearmon,
                       'datatype_id' => $datatype->id, 'accessmethod_id' => $accessmethod_id,
                       'searches_automated' => $ICounts['Searches_Automated'],
                       'searches_federated' => $ICounts['Searches_Federated'],
                       'searches_regular' => $ICounts['Searches_Regular'],
                       'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                       'total_item_requests' => $ICounts['Total_Item_Requests'],
                       'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                       'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                       'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                       'unique_title_requests' => $ICounts['Unique_Title_Requests'],
                       'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License'],
                       'created_at' => self::$now]);

           // Reset metric counts
            for ($_m = 0; $_m < $_metric_count; $_m++) {
                $ICounts[$_metric_keys[$_m]] = 0;
            }
        }     // foreach $ReportItems

        return 'Success';
    }

  /**
   * Parse and save json data for a COUNTER-5 PR master report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or "Failed")
   */
    public static function PR($json_report)
    {
        // If $replace flag is ON, clear out existing records first
        if (self::$replace) {
            PlatformReport::where([['prov_id','=',self::$prov],
                                   ['inst_id','=',self::$inst],
                                   ['yearmon','=',self::$yearmon]])->delete();
        }

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
            if ($_platform == "") {
                continue;
            }
            $platform_id = self::getPlatform($_platform);

           // Pick up the optional attributes
            $accessmethod_id = (isset($reportitem->Access_Method)) ? self::getAccessMethod($reportitem->Access_Method)
                                                                   : 1;
            $datatype = (isset($reportitem->Data_Type)) ? self::getDataType($reportitem->Data_Type)
                                                        : self::getDataType("Unknown");

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
            PlatformReport::insert(['plat_id' => $platform_id, 'prov_id' => self::$prov, 'inst_id' => self::$inst,
                    'yearmon' => self::$yearmon, 'datatype_id' => $datatype->id, 'accessmethod_id' => $accessmethod_id,
                    'searches_platform' => $ICounts['Searches_Platform'],
                    'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                    'total_item_requests' => $ICounts['Total_Item_Requests'],
                    'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                    'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                    'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                    'unique_title_requests' => $ICounts['Unique_Title_Requests'], 'created_at' => self::$now]);

           // Reset metric counts
            for ($_m = 0; $_m < $_metric_count; $_m++) {
                $ICounts[$_metric_keys[$_m]] = 0;
            }
        }     // foreach $ReportItems

        return 'Success';
    }

  /**
   * Parse and save json data for a COUNTER-5 master IR report. Validation
   * should have already been performed on $json_report..
   *
   * @param  $json_report
   * @return string ("Success" or "Failed")
   */
    public static function IR($json_report)
    {
        // If $replace flag is ON, clear out existing records first
        if (self::$replace) {
            ItemReport::where([['prov_id','=',self::$prov],
                               ['inst_id','=',self::$inst],
                               ['yearmon','=',self::$yearmon]])->delete();
        }

       // Setup array to hold per-item counts
        $ICounts = ['Total_Item_Requests' => 0, 'Total_Item_Investigations' => 0,
                    'Unique_Item_Requests' => 0, 'Unique_Item_Investigations' => 0,
                    'Limit_Exceeded' => 0, 'No_License' => 0];
        $_metric_keys = array_keys($ICounts);
        $_metric_count = count($ICounts);

       // Decode JSON and put header and report records into variables
        $header = $json_report->Report_Header;
        $ReportItems = $json_report->Report_Items;

        $authors = "";  // skipping this for the time-being

       // Loop through all ReportItems
        foreach ($ReportItems as $reportitem) {
           // Author(s) processing would go here
            // $authors = (isset($reportitem->Item_Contributors)) ? $reportitem->Item_Contributors) : "";

           // Get Publisher
            $publisher_id = (isset($reportitem->Publisher)) ? self::getPublisher($reportitem->Publisher) : null;

           // Get Platform
            $platform_id = (isset($reportitem->Platform)) ? self::getPlatform($reportitem->Platform) : null;

           // Get Name and Item_ID fields
            $Name = (isset($reportitem->Item)) ? substr($reportitem->Item, 0, 256) : "";

           // If no Name or Item_ID skip the item..
            if ($Name == "" && !isset($reportitem->Item_ID)) {
                continue;
            }
            $_item_id = (isset($reportitem->Item_ID)) ? $reportitem->Item_ID : array();
            $Item_ID = self::itemIDValues($_item_id);

           // Pick up the optional attributes
            $YOP = (isset($reportitem->YOP)) ? $reportitem->YOP : "";
            $accesstype_id = (isset($reportitem->Access_Type)) ? self::getAccessType($reportitem->Access_Type)
                                                               : null;
            $accessmethod_id = (isset($reportitem->Access_Method)) ? self::getAccessMethod($reportitem->Access_Method)
                                                                   : 1;
            $datatype = (isset($reportitem->Data_Type)) ? self::getDataType($reportitem->Data_Type)
                                                        : self::getDataType("Unknown");

           // Get publication date and article version
            $pub_date = "";
            $item_dates = (isset($reportitem->Item_Dates)) ? $reportitem->Item_Dates : "";
            if ($item_dates != "") {
                foreach ($item_dates as $date) {
                    if ($date->Type == "Publication_Date") {
                        $pub_date = $date->Value;
                        break;
                    }
                }
            }
            $article_version = "";
            $item_attributes = (isset($reportitem->Item_Attributes)) ? $reportitem->Item_Attributes : "";
            if ($item_attributes != "") {
                foreach ($item_attributes as $attrib) {
                    if ($attrib->Type == "Article_Version") {
                        $article_version = $attrib->Value;
                        break;
                    }
                }
            }

           // Ignore Components for now...
            $component_id = null;
            $component_datatype_id = null;

           // Find or Create the Parent
            $parent_id = null;
            $parent_datatype_id = null;
            $item_parent = (isset($reportitem->Item_Parent)) ? $reportitem->Item_Parent : "";
            if ($item_parent != "") {
                $parent_name = (isset($item_parent->Item_Name)) ? substr($item_parent->Item_Name, 0, 256) : "";
                $parent_itemid = (isset($item_parent->Item_ID)) ? $item_parent->Item_ID : array();
                if (sizeof($parent_itemid) > 0) {
                    $_pitem_ID = self::itemIDValues($parent_itemid);

                   // parent datatype
                    $_pdatatype = (isset($item_parent->Data_Type)) ? $item_parent->Data_Type : "Unknown";
                    $parent_datatype = self::getDataType($_pdatatype);

                   // Get or create the parent
                    if ($parent_datatype->name == "Journal") {
                        $_jrnl = self::journalFindOrCreate(
                            $parent_name,
                            $_pitem_ID['PropID'],
                            $_pitem_ID['ISSN'],
                            $_pitem_ID['eISSN'],
                            $_pitem_ID['DOI'],
                            $_pitem_ID['URI']
                        );
                        if (is_null($_jrnl)) {
                            continue;
                        }
                        $parent_id = $_jrnl->id;
                    } elseif ($parent_datatype->name == "Book") {
                        $_book = self::bookFindOrCreate(
                            $parent_name,
                            $_pitem_ID['PropID'],
                            $_pitem_ID['ISBN'],
                            $_pitem_ID['DOI'],
                            $_pitem_ID['URI']
                        );
                        if (is_null($_book)) {
                            continue;
                        }
                        $parent_id = $_book->id;
                    } else {   // Parent is Not a Journal or Book, findorcreate as an Item
                        $_item = self::itemFindOrCreate(
                            $parent_name,
                            $_pitem_ID['PropID'],
                            $_pitem_ID['ISSN'],
                            $_pitem_ID['eISSN'],
                            $_pitem_ID['ISBN'],
                            $_pitem_ID['DOI'],
                            $_pitem_ID['URI']
                        );
                        if (is_null($_item)) {
                            continue;
                        }
                        $parent_id = $_item->id;
                    }
                    $parent_datatype_id = $parent_datatype->id;
                }
            }

           // Get or create the Item in the global table
            $_item = self::itemFindOrCreate(
                $Name,
                $Item_ID['PropID'],
                $Item_ID['ISSN'],
                $Item_ID['eISSN'],
                $Item_ID['ISBN'],
                $Item_ID['DOI'],
                $Item_ID['URI'],
                $pub_date,
                $article_version,
                $parent_id,
                $parent_datatype_id
            );
            if (is_null($_item)) {
                continue;
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
            ItemReport::insert(['item_id' => $_item->id, 'prov_id' => self::$prov, 'publisher_id' => $publisher_id,
                'plat_id' => $platform_id, 'inst_id' => self::$inst, 'yearmon' => self::$yearmon, 'YOP' => $YOP,
                'datatype_id' => $datatype->id, 'accesstype_id' => $accesstype_id,'accessmethod_id' => $accessmethod_id,
                'total_item_requests' => $ICounts['Total_Item_Requests'],
                'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License'],
                'created_at' => self::$now]);

           // Reset metric counts
            for ($_m = 0; $_m < $_metric_count; $_m++) {
                $ICounts[$_metric_keys[$_m]] = 0;
            }
        }

        return 'Success';
    }

    /**
     * Function accepts a $platform as a string. If no current Platform matches, create one.
     * Return the ID for the Platform.
     *
     * @param $input_platform
     * @return Int or null for errors/empty input
     *
     */
    private static function getPlatform($input_platform)
    {
        $platform_id = null;
        if ($input_platform != "") {
            $platform = Platform::firstOrCreate(['name' => $input_platform]);
            $platform_id = $platform->id;
        }
        return $platform_id;
    }

    /**
     * Function accepts a $publisher as a string. If no current Publisher matches, create one.
     * Return the ID for the Publisher.
     *
     * @param $input_publisher
     * @return Int or null for errors/empty input
     *
     */
    private static function getPublisher($input_publisher)
    {
        $publisher_id = null;
        if ($input_publisher != "") {
            $publisher = Publisher::firstOrCreate(['name' => $input_publisher]);
            $publisher_id = $publisher->id;
        }
        return $publisher_id;
    }

    /**
     * Function accepts a $accesstype as a string. If no current AccessType matches, create one
     * and return the ID for the AccessType.
     *
     * @param $input_type
     * @return Int or null for errors/empty input
     *
     */
    private static function getAccessType($input_type)
    {
        $accesstype_id = null;
        if ($input_type != "") {
            $accesstype = AccessType::firstOrCreate(['name' => $input_type]);
            $accesstype_id = $accesstype->id;
        }
        return $accesstype_id;
    }

    /**
     * Function accepts a $accessmethod as a string. If no current AccessMethod matches, create one
     * and return the ID for the AccessMethod.
     *
     * @param $input_method
     * @return Int or null for errors/empty input
     *
     */
    private static function getAccessMethod($input_method)
    {
        $accessmethod_id = 1;   // Regular
        if ($input_method != "") {
            $accessmethod = AccessMethod::firstOrCreate(['name' => $input_method]);
            $accessmethod_id = $accessmethod->id;
        }
        return $accessmethod_id;
    }

    /**
     * Function accepts a $datatype as a string. If no current DataType matches, create one.
     * If input string is empty, the type defaults to "Unknown"
     *
     * @param $input_type
     * @return DataType
     *
     */
    private static function getDataType($input_type)
    {
        if ($input_type == "") {
            $input_type = "Unknown";
        }
        $datatype = DataType::firstOrCreate(['name' => $input_type]);
        return $datatype;
    }

    /**
     * Function accepts a $sectiontype as a string. If no current SectionType matches, create one
     * and return the ID for the SectionType.
     *
     * @param $input_type
     * @return Int or null for errors/empty input
     *
     */
    private static function getSectionType($input_type)
    {
        $sectiontype_id = null;
        if ($input_type != "") {
            $sectiontype = SectionType::firstOrCreate(['name' => $input_type]);
            $sectiontype_id = $sectiontype->id;
        }
        return $sectiontype_id;
    }

    /**
     * Function accepts a JSON Item_ID object and returns an array of values for what
     * may be included; missing variables within the object are returned as null.
     *
     * @param $Item_ID
     * @return $Values
     *
     */
    private static function itemIDValues($Item_ID)
    {
       // Initialize variables for Title and Item_ID fields
       // We'll use these as a basis for trying to match against known titles
        $Values = ['PropID' => "", 'ISBN' => "", 'ISSN' => "", 'eISSN' => "", 'DOI' => "", 'URI' => ""];
        foreach ($Item_ID as $_id) {
            if ($_id->Type == "Proprietary") {
                $Values['PropID'] = substr($_id->Value, 0, 256);
            }
            if ($_id->Type == "ISBN") {
                $Values['ISBN'] = $_id->Value;
            }
            if ($_id->Type == "Print_ISSN") {
                $Values['ISSN'] = $_id->Value;
            }
            if ($_id->Type == "Online_ISSN") {
                $Values['eISSN'] = $_id->Value;
            }
            if ($_id->Type == "DOI") {
                $Values['DOI'] = substr($_id->Value, 0, 256);
            }
            if ($_id->Type == "URI") {
                $Values['URI'] = substr($_id->Value, 0, 256);
            }
        }
        return $Values;
    }

    /**
     * Function to find-or-create a Journal in/from the global table
     *
     * @param $title, $propID, $issn, $eissn, $doi, $uri
     * @return Journal or null for errors/missing input
     *
     * $journal = self::journalFindOrCreate($title, $propID, $issn, $eissn, $doi, $uri);
     */
    private static function journalFindOrCreate($_title, $propID, $issn, $eissn, $doi, $uri)
    {

        if ($_title == "" && $propID = "" && $issn == "" && $eissn == "" && $doi == "" && $uri == "") {
            return null;
        }

       // UFT8 Encode any special chars in the title
        $title = utf8_encode($_title);    // in case title has funky chars

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
            $matched = false;
           // If title matches and other input fields are null, call it a match
            if (
                $title != ""
                && $title == $journal->Title
                && ( ($propID == ""
                && $issn == ""
                && $eissn == ""
                && $doi == ""
                && $uri == "")
                  || ($journal->propID == ""
                  && $journal->issn == ""
                  && $journal->eissn == ""
                   && $journal->doi == ""
                   && $journal->uri == "")
                )
            ) {
                $matched = true;
            }

           // If URI matches and other input fields are null, call it a match
            if (
                $uri != ""
                && $uri == $journal->URI
                && ( ($title == ""
                && $propID == ""
                && $issn == ""
                && $eissn == ""
                && $doi == "")
                  || ($journal->title == ""
                  && $journal->propID == ""
                  && $journal->issn == ""
                   && $journal->eissn == ""
                   && $journal->doi == "")
                )
            ) {
                $matched = true;
            }

           // Test the remaining identifiers - except URI. If match is found, update fields in the
           // model that we have values for.
            if (
                $matched ||
                ($propID != "" &&
                $journal->PropID == $propID) ||
                ($doi != "" &&
                $journal->DOI == $doi) ||
                ($issn != "" &&
                $journal->ISSN == $issn) ||
                ($eissn != "" &&
                $journal->eISSN == $eissn)
            ) {
               // Check matched fields, don't overwrite non-null model values with null
                if ($title != "" && $journal->Title != $title) {
                    $save_it = true;
                    $journal->Title = $title;
                }
                if ($propID != "" && $journal->PropID != $propID) {
                    $save_it = true;
                    $journal->PropID = $propID;
                }
                if ($doi != "" && $journal->DOI != $doi) {
                    $save_it = true;
                    $journal->DOI = $doi;
                }
                if ($issn != "" && $journal->ISSN != $issn) {
                    $save_it = true;
                    $journal->ISSN = $issn;
                }
                if ($eissn != "" && $journal->eISSN != $eissn) {
                    $save_it = true;
                    $journal->eISSN = $eissn;
                }
                if ($uri != "" && $journal->URI != $uri) {
                    $save_it = true;
                    $journal->URI = $uri;
                }
                if ($save_it) {
                    $journal->save();
                }
                return $journal;
            }
        }

       // If we get here, create a new record
        try {
            $journal = new Journal(['Title' => $title, 'ISSN' => $issn, 'eISSN' => $eissn, 'DOI' => $doi,
                                    'PropID' => $propID, 'URI' => $uri]);
            $journal->save();
            return $journal;
        } catch (\PDOException $e) {
            echo $e->getMessage();
            return null;
        } catch(Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }

    /**
     * Function to find-or-create a Book in/from the global table
     *
     * @param $title, $propID, $isbn, $doi, $uri
     * @return Book or null for errors/missing input
     *
     *    $book = self::bookFindOrCreate($title, $propID, $isbn, $doi, $uri);
     */
    private static function bookFindOrCreate($_title, $propID, $isbn, $doi, $uri)
    {
        if ($_title == "" && $propID = "" && $isbn == "" && $doi == "" && $uri == "") {
            return null;
        }

       // UFT8 Encode any special chars in the title
        $title = utf8_encode($_title);    // in case title has funky chars

       // Get any potential matches
        $matches = Book::where([['Title', '<>',''],['Title', '=',$title]])->
                       orWhere([['PropID','<>',''],['PropID','=',$propID]])->
                       orWhere([['ISBN',  '<>',''],['ISBN',  '=',$isbn]])->
                       orWhere([['DOI',   '<>',''],['DOI',   '=',$doi]])->
                       orWhere([['URI',   '<>',''],['URI',   '=',$uri]])->get();

       // Loop through all the possibles
        $save_it = false;
        foreach ($matches as $book) {
            $matched = false;
           // If title matches and other input fields are null, call it a match
            if (
                $title != ""
                && $title == $book->Title
                && ( ($propID == ""
                && $isbn == ""
                && $doi == ""
                && $uri == "")
                  || ($book->propID == ""
                  && $book->ISBN == ""
                  && $book->DOI == ""
                  && $book->URI == "")
                )
            ) {
                $matched = true;
            }

           // If URI matches and other input fields are null, call it a match
            if (
                $uri != ""
                && $uri == $book->URI
                && ( ($title == ""
                && $propID == ""
                && $isbn == ""
                && $doi == "")
                  || ($book->Title == ""
                  && $book->propID == ""
                  && $book->ISBN == ""
                  && $book->DOI == "")
                )
            ) {
                $matched = true;
            }

           // Test the remaining identifiers - except URI. If match is found, update fields in the
           // model that we have values for.
            if (
                $matched ||
                ($propID != "" &&
                $book->PropID == $propID) ||
                ($doi != "" &&
                $book->DOI == $doi) ||
                ($isbn != ""  &&
                $book->ISBN == $isbn)
            ) {
               // Check matched fields, don't overwrite non-null model values with null
                if ($title != "" && $book->Title != $title) {
                    $save_it = true;
                    $book->Title = $title;
                }
                if ($propID != "" && $book->PropID != $propID) {
                    $save_it = true;
                    $book->PropID = $propID;
                }
                if ($doi != "" && $book->DOI != $doi) {
                    $save_it = true;
                    $book->DOI = $doi;
                }
                if ($isbn != "" && $book->ISBN != $isbn) {
                    $save_it = true;
                    $book->ISBN = $isbn;
                }
                if ($uri != "" && $book->URI != $uri) {
                    $save_it = true;
                    $book->URI = $uri;
                }
                if ($save_it) {
                    $book->save();
                }
                return $book;
            }
        }

       // If no match, create it
        try {
            $book = new Book(['Title' => $title, 'ISBN' => $isbn, 'DOI' => $doi, 'PropID' => $propID, 'URI' => $uri]);
            $book->save();
            return $book;
        } catch (\PDOException $e) {
            echo $e->getMessage();
            return null;
        } catch(Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }

    /**
     * Function to find-or-create an Item in/from the global table
     *
     * @param $name, $propID, $isbn, $doi, $uri, $pub, $ver, $parent_id, $parent_datatype_id
     * @return Item or null for errors/missing input
     *
     *    $item = self::itemFindOrCreate($name, $propID, $issn, $eissn, $isbn, $doi, $uri
     *                                   [, $pub][, $ver][, $parent_id][, $parent_datatype_id]);
     */
    private static function itemFindOrCreate(
        $_name,
        $propID,
        $issn,
        $eissn,
        $isbn,
        $doi,
        $uri,
        $pub = "",
        $ver = "",
        $parent_id = null,
        $parent_datatype_id = null
    ) {
        if ($_name == "" && $propID = "" && $issn == "" && $eissn == "" && $isbn == "" && $doi == "" && $uri == "") {
            return null;
        }

       // UFT8 Encode any special chars in the title
        $name = utf8_encode($_name);    // in case title has funky chars

       // Get any potential matches
        $matches = Item::where([['Name',  '<>',''],['Name',  '=',$name]])->
                       orWhere([['PropID','<>',''],['PropID','=',$propID]])->
                       orWhere([['ISSN',  '<>',''],['ISSN',  '=',$issn]])->
                       orWhere([['eISSN', '<>',''],['eISSN', '=',$eissn]])->
                       orWhere([['ISBN',  '<>',''],['ISBN',  '=',$isbn]])->
                       orWhere([['DOI',   '<>',''],['DOI',   '=',$doi]])->
                       orWhere([['URI',   '<>',''],['URI',   '=',$uri]])->get();

       // Loop through all the possibles
        $save_it = false;
        foreach ($matches as $item) {
            $matched = false;
           // If name matches and other input fields are null, call it a match
            if (
                $name != ""
                && $name == $item->Name
                && ( ($propID == ""
                && $issn == ""
                && $eissn == ""
                && $isbn == ""
                && $doi == ""
                && $uri == ""
                   && $pub == ""
                   && $ver == "")
                  || ($item->propID == ""
                  && $item->ISSN == ""
                  && $item->eISSN == ""
                  && $item->ISBN == ""
                   && $item->DOI == ""
                   && $item->URI == ""
                   && $item->pub_date == ""
                   && $item->article_version == "")
                )
            ) {
                $matched = true;
            }

           // If URI matches and other input fields are null, call it a match
            if (
                $uri != ""
                && $uri == $item->URI
                && ( ($name == ""
                && $propID == ""
                && $issn == ""
                && $eissn == ""
                && $isbn == ""
                && $doi == ""
                   && $pub == ""
                   && $ver == "")
                  || ($item->Name == ""
                  && $item->propID == ""
                  && $item->ISSN == ""
                  && $item->eISSN == ""
                   && $item->ISBN == ""
                   && $item->DOI == ""
                   && $item->pub_date == ""
                   && $item->article_version == "")
                )
            ) {
                $matched = true;
            }

           // Test the remaining identifiers - except URI. If match is found, update fields in the
           // model that we have values for.
            if (
                $matched ||
                   (
                      // It's only a match when pub_date and article_version match
                       ($item->pub_date == $pub &&
                       $item->article_version == $ver) &&
                       (
                           ($propID != "" &&
                           $item->PropID == $propID) ||
                           ($doi != "" &&
                           $item->DOI == $doi) ||
                           ($issn != "" &&
                           $item->ISSN == $issn) ||
                           ($eissn != "" &&
                           $item->eISSN == $eissn) ||
                           ($isbn != "" &&
                           $item->ISBN == $isbn)
                       )
                   )
            ) {
               // Check matched fields, don't overwrite non-null model values with null
                if ($name != "" && $item->Name != $name) {
                    $save_it = true;
                    $item->Name = $name;
                }
                if ($propID != "" && $item->PropID != $propID) {
                    $save_it = true;
                    $item->PropID = $propID;
                }
                if ($doi != "" && $item->DOI != $doi) {
                    $save_it = true;
                    $item->DOI = $doi;
                }
                if ($issn != "" && $item->ISSN != $issn) {
                    $save_it = true;
                    $item->ISSN = $issn;
                }
                if ($eissn != "" && $item->eISSN != $eissn) {
                    $save_it = true;
                    $item->eISSN = $eissn;
                }
                if ($isbn != "" && $item->ISBN != $isbn) {
                    $save_it = true;
                    $item->ISBN = $isbn;
                }
                if ($uri != "" && $item->URI != $uri) {
                    $save_it = true;
                    $item->URI = $uri;
                }
                if ($pub != "" && $item->pub_date != $pub) {
                    $save_it = true;
                    $item->pub_date = $pub;
                }
                if ($ver != "" && $item->article_version != $ver) {
                    $save_it = true;
                    $item->article_version = $ver;
                }
                if ($save_it) {
                    $item->save();
                }
                return $item;
            }
        }

       // If no match, create it
        try {
            $item = new Item(['Name' => $name, 'ISSN' => $issn, 'eISSN' => $eissn, 'ISBN' => $isbn, 'DOI' => $doi,
                              'PropID' => $propID, 'URI' => $uri, 'pub_date' => $pub, 'article_version' => $ver,
                              'parent_id' => $parent_id, 'parent_datatype_id' => $parent_datatype_id]);
            $item->save();
            return $item;
        } catch (\PDOException $e) {
            echo $e->getMessage();
            return null;
        } catch(Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }
}
