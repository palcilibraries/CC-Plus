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
            $publisher_id = (isset($reportitem->Publisher)) ? self::getPublisher($reportitem->Publisher) : 1;

           // Get Platform
            $platform_id = (isset($reportitem->Platform)) ? self::getPlatform($reportitem->Platform) : 1;

           // Get Title and Item_ID fields
            $_title = (isset($reportitem->Title)) ? substr($reportitem->Title, 0, 256) : "";

           // Get the Item_ID fields and store in an array along with the (title)type
           // if $_title is null and Item_ID is missing, skip the record (silently)
            if ($_title == "" && !isset($reportitem->Item_ID)) {
                continue;
            }
            $_item_id = (isset($reportitem->Item_ID)) ? $reportitem->Item_ID : array();
            $Item_ID = self::itemIDValues($_item_id);

           // Pick up the optional attributes
            $_YOP = (isset($reportitem->YOP)) ? $reportitem->YOP : "";
            $accesstype_id = (isset($reportitem->Access_Type)) ? self::getAccessType($reportitem->Access_Type)
                                                                 : 1;
            $accessmethod_id = (isset($reportitem->Access_Method)) ? self::getAccessMethod($reportitem->Access_Method)
                                                                   : 1;
            $sectiontype_id = (isset($reportitem->Section_Type)) ? self::getSectionType($reportitem->Section_Type)
                                                                 : 1;
            $datatype = (isset($reportitem->Data_Type)) ? self::getDataType($reportitem->Data_Type)
                                                        : self::getDataType("Unknown");
            $Item_ID['type'] = ($datatype->name == "Journal" || $datatype->name == "Book") ?
                               substr($datatype->name,0,1) :
                                "I";

           // Get or Create Title entry
            $title = self::titleFindOrCreate($_title, $Item_ID);
            if (is_null($title)) {  // bail silently
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
            TitleReport::insert(['title_id' => $title->id, 'prov_id' => self::$prov, 'publisher_id' => $publisher_id,
                  'plat_id' => $platform_id, 'inst_id' => self::$inst, 'yearmon' => self::$yearmon,
                  'datatype_id' => $datatype->id, 'sectiontype_id' => $sectiontype_id, 'YOP' => $_YOP,
                  'accesstype_id' => $accesstype_id, 'accessmethod_id' => $accessmethod_id,
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
             $publisher_id = (isset($reportitem->Publisher)) ? self::getPublisher($reportitem->Publisher) : 1;

            // Get Platform
             $platform_id = (isset($reportitem->Platform)) ? self::getPlatform($reportitem->Platform) : 1;

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
            $publisher_id = (isset($reportitem->Publisher)) ? self::getPublisher($reportitem->Publisher) : 1;

           // Get Platform
            $platform_id = (isset($reportitem->Platform)) ? self::getPlatform($reportitem->Platform) : 1;

           // Get Title and Item_ID fields
            $Title = (isset($reportitem->Item)) ? substr($reportitem->Item, 0, 256) : "";

           // If no Title or Item_ID skip the item..
            if ($Title == "" && !isset($reportitem->Item_ID)) {
                continue;
            }
            $_item_id = (isset($reportitem->Item_ID)) ? $reportitem->Item_ID : array();
            $Item_ID = self::itemIDValues($_item_id);

           // Pick up the optional attributes
            $YOP = (isset($reportitem->YOP)) ? $reportitem->YOP : "";
            $accesstype_id = (isset($reportitem->Access_Type)) ? self::getAccessType($reportitem->Access_Type)
                                                               : 1;
            $accessmethod_id = (isset($reportitem->Access_Method)) ? self::getAccessMethod($reportitem->Access_Method)
                                                                   : 1;
            $datatype = (isset($reportitem->Data_Type)) ? self::getDataType($reportitem->Data_Type)
                                                        : self::getDataType("Unknown");
            $Item_ID['type'] = ($datatype->name == "Journal" || $datatype->name == "Book") ?
                                substr($datatype->name,0,1) :
                                "I";

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

           // Get-Create Title entry
            $title = self::titleFindOrCreate($Title, $Item_ID, $pub_date, $article_version);
            if (is_null($title)) {  // skip silently
                continue;
            }

           // Ignore Components for now...
            $component_id = null;
            $component_datatype_id = null;

           // Find or Create the Parent
            $parent_id = null;
            $parent_datatype_id = null;
            $item_parent = (isset($reportitem->Item_Parent)) ? $reportitem->Item_Parent : "";
            if ($item_parent != "") {
                $_parentTitle = (isset($item_parent->Item_Name)) ? substr($item_parent->Item_Name, 0, 256) : "";
                $_parentItemid = (isset($item_parent->Item_ID)) ? $item_parent->Item_ID : array();
                if (sizeof($_parentItemid) > 0) {
                    $_pitem_ID = self::itemIDValues($_parentItemid);

                   // parent datatype
                    $_pdatatype = (isset($item_parent->Data_Type)) ? $item_parent->Data_Type : "Unknown";
                    $parent_datatype = self::getDataType($_pdatatype);
                    $_pitem_ID['type'] = ($parent_datatype->name == "Journal" || $parent_datatype->name == "Book") ?
                                          substr($parent_datatype->name,0,1) :
                                          "I";

                   // Get-Create the title for the parent
                    $parent_title = self::titleFindOrCreate($_parentTitle, $_pitem_ID);
                    if (is_null($parent_title)) {  // skip silently
                        continue;
                    }
                    $parent_id = $parent_title->id;
                    $parent_datatype_id = $parent_datatype->id;
                }
            }

           // Get or create the Item in the global table
            $_item = Item::firstOrCreate(['title_id' => $title->id],
                                         ['parent_id' => $parent_id],
                                         ['parent_datatype_id' => $parent_datatype_id]);
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
       // Initialize variables for Title and Item_ID fields.
       // We'll use these as a basis for trying to match against known titles
        $Values = ['type' => "", 'ISBN' => "", 'ISSN' => "", 'eISSN' => "", 'DOI' => "", 'PropID' => "", 'URI' => ""];
        foreach ($Item_ID as $_id) {
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
            if ($_id->Type == "Proprietary") {
                $Values['PropID'] = substr($_id->Value, 0, 256);
            }
            if ($_id->Type == "URI") {
                $Values['URI'] = substr($_id->Value, 0, 256);
            }
        }
        return $Values;
    }

    /**
     * Function to find-or-create a Title in/from the global table
     *
     * @param $_title, $ident, $pub, $ver
     * @return Title or null for errors/missing input
     *
     * $title = self::titleFindOrCreate($_title, $ident, $pub, $ver);
     */
    private static function titleFindOrCreate($_title, $ident, $pub="", $ver="")
    {
        if ($_title == "" && $ident['PropID'] = "" && $ident['ISBN'] == "" && $ident['ISSN'] == "" &&
            $ident['eISSN'] == "" && $ident['DOI'] == "" && $ident['URI'] == "") {
            return null;
        }
       // UFT8 Encode any special chars in the title
        $input_title = utf8_encode($_title);    // in case title has funky chars

       // Get any potential matches
        $matches = Title::where([['Title', '<>',''],['Title', '=',$input_title]])->
                          orWhere([['PropID','<>',''],['PropID','=',$ident['PropID']]])->
                          orWhere([['eISSN', '<>',''],['eISSN', '=',$ident['eISSN']]])->
                          orWhere([['ISSN',  '<>',''],['ISSN',  '=',$ident['ISSN']]])->
                          orWhere([['ISBN',  '<>',''],['ISBN',  '=',$ident['ISBN']]])->
                          orWhere([['DOI',   '<>',''],['DOI',   '=',$ident['DOI']]])->
                          orWhere([['URI',   '<>',''],['URI',   '=',$ident['URI']]])->get();

       // Loop through all the possibles
        $save_it = false;
        foreach ($matches as $match) {

            $matched = false;
           // If Title matches and other input fields are null, call it a match
            if (
                $input_title != ""
                && $input_title == $match->Title
                && ( ($ident['PropID'] == "" && $ident['ISSN'] == "" && $ident['eISSN'] == "" &&
                      $ident['DOI'] == "" && $ident['URI'] == "") ||
                     ($match->PropID == "" && $match->ISSN == "" && $match->eISSN == "" && $match->ISBN == "" &&
                      $match->DOI == "" && $match->URI == "")
                   )
               ) {
                 $matched = true;
            }

           // If URI matches and other input fields are null, call it a match
            if (
                $ident['URI'] != ""
                && $ident['URI'] == $match->URI
                && ( ($input_title == "" && $ident['PropID'] == "" && $ident['ISSN'] == "" &&
                      $ident['eISSN'] == "" && $ident['DOI'] == "") ||
                     ($match->title == "" && $match->PropID == "" && $match->ISSN == "" && $match->ISBN == "" &&
                      $match->eISSN == "" && $match->DOI == "")
                   )
               ) {
                 $matched = true;
            }

           // Test the remaining identifiers - except URI. If match is found, update fields in the
           // model that we have values for.
            if (
                $matched ||
                ($ident['PropID'] != "" && $match->PropID == $ident['PropID']) ||
                ($ident['DOI'] != "" && $match->DOI == $ident['DOI']) ||
                ($ident['ISSN'] != "" && $match->ISSN == $ident['ISSN']) ||
                ($ident['eISSN'] != "" && $match->eISSN == $ident['eISSN'])
                ) {
               // Check matched fields, don't overwrite non-null model values with null
                if ($input_title != "" && $match->Title != $input_title) {
                    $save_it = true;
                    $match->Title = $input_title;
                }
                if ($ident['PropID'] != "" && $match->PropID != $ident['PropID']) {
                    $save_it = true;
                    $match->PropID = $ident['PropID'];
                }
                if ($ident['DOI'] != "" && $match->DOI != $ident['DOI']) {
                    $save_it = true;
                    $match->DOI = $ident['DOI'];
                }
                if ($ident['ISSN'] != "" && $match->ISSN != $ident['ISSN']) {
                    $save_it = true;
                    $match->ISSN = $ident['ISSN'];
                }
                if ($ident['eISSN'] != "" && $match->eISSN != $ident['eISSN']) {
                    $save_it = true;
                    $match->eISSN = $ident['eISSN'];
                }
                if ($ident['URI'] != "" && $match->URI != $ident['URI']) {
                    $save_it = true;
                    $match->URI = $ident['URI'];
                }
                if ($save_it) {
                    $match->save();
                }
                return $match;
            }
        }

       // If we get here, create a new record
        try {
            $new_title = new Title(['Title' => $input_title, 'ISSN' => $ident['ISSN'], 'eISSN' => $ident['eISSN'],
                                    'ISBN' => $ident['ISBN'], 'DOI' => $ident['DOI'],
                                    'PropID' => $ident['PropID'], 'URI' => $ident['URI'],
                                    'type' => $ident['type'], 'pub_date' => $pub, 'article_version' => $ver]);
            $new_title->save();
            return $new_title;
        } catch (\PDOException $e) {
            echo $e->getMessage();
            return null;
        } catch(Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }
}
