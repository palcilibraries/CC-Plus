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
            $_title = (isset($reportitem->Title)) ? mb_substr($reportitem->Title, 0, 256) : "";

           // Get the Item_ID fields and store in an array along with the (title)type
           // if $_title is null and Item_ID is missing, skip the record (silently)
            if ($_title == "" && !isset($reportitem->Item_ID)) {
                continue;
            }
            $_item_id = (isset($reportitem->Item_ID)) ? $reportitem->Item_ID : array();
            $Item_ID = self::itemIDValues($_item_id);

           // Pick up the optional attributes
            $_yop = (isset($reportitem->yop)) ? $reportitem->yop : "";
            $accesstype_id = (isset($reportitem->Access_Type)) ? self::getAccessType($reportitem->Access_Type)
                                                                 : 1;
            $accessmethod_id = (isset($reportitem->Access_Method)) ? self::getAccessMethod($reportitem->Access_Method)
                                                                   : 1;
            $sectiontype_id = (isset($reportitem->Section_Type)) ? self::getSectionType($reportitem->Section_Type)
                                                                 : 1;
            $datatype = (isset($reportitem->Data_Type)) ? self::getDataType($reportitem->Data_Type)
                                                        : self::getDataType("Unknown");
            $Item_ID['type'] = ($datatype->name == "Journal" || $datatype->name == "Book") ?
                               mb_substr($datatype->name, 0, 1) :
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
                        // ignore unrecognized metrics
                        if (isset($ICounts[$instance->Metric_Type])) {
                            $ICounts[$instance->Metric_Type] += $instance->Count;
                        }
                    }
                }
            }         // foreach performance clause

           // Insert the record
            TitleReport::insert(['title_id' => $title->id, 'prov_id' => self::$prov, 'publisher_id' => $publisher_id,
                  'plat_id' => $platform_id, 'inst_id' => self::$inst, 'yearmon' => self::$yearmon,
                  'datatype_id' => $datatype->id, 'sectiontype_id' => $sectiontype_id, 'yop' => $_yop,
                  'accesstype_id' => $accesstype_id, 'accessmethod_id' => $accessmethod_id,
                  'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                  'total_item_requests' => $ICounts['Total_Item_Requests'],
                  'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                  'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                  'unique_title_investigations' => $ICounts['Unique_Title_Investigations'],
                  'unique_title_requests' => $ICounts['Unique_Title_Requests'],
                  'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License']]);
                  // 'created_at' => self::$now]);

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
            $_name = (isset($reportitem->Database)) ? $reportitem->Database : "";
            if ($_name == "") {
                continue;
            }
           // UTF8 Encode name if it isnt already UTF-8
            $cur_encoding = mb_detect_encoding($_name);
            if ($cur_encoding == "UTF-8" && mb_check_encoding($_name, "UTF-8")) {
                $_database = $_name;
            } else {
                $_database = utf8_encode($_name);    // force to utf-8
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
                       'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License']]);
                       // 'created_at' => self::$now]);

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
                    $perf->Period->End_Date == self::$end &&
                    isset($perf->Instance)
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
                    'unique_title_requests' => $ICounts['Unique_Title_Requests']]);
                    // 'created_at' => self::$now]);

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
            $Title = (isset($reportitem->Item)) ? mb_substr($reportitem->Item, 0, 256) : "";

           // If no Title or Item_ID skip the item..
            if ($Title == "" && !isset($reportitem->Item_ID)) {
                continue;
            }
            $_item_id = (isset($reportitem->Item_ID)) ? $reportitem->Item_ID : array();
            $Item_ID = self::itemIDValues($_item_id);

           // Pick up the optional attributes
            $yop = (isset($reportitem->yop)) ? $reportitem->yop : "";
            $accesstype_id = (isset($reportitem->Access_Type)) ? self::getAccessType($reportitem->Access_Type)
                                                               : 1;
            $accessmethod_id = (isset($reportitem->Access_Method)) ? self::getAccessMethod($reportitem->Access_Method)
                                                                   : 1;
            $datatype = (isset($reportitem->Data_Type)) ? self::getDataType($reportitem->Data_Type)
                                                        : self::getDataType("Unknown");
            $Item_ID['type'] = ($datatype->name == "Journal" || $datatype->name == "Book") ?
                                mb_substr($datatype->name, 0, 1) :
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
                $_parentTitle = (isset($item_parent->Item_Name)) ? mb_substr($item_parent->Item_Name, 0, 256) : "";
                $_parentItemid = (isset($item_parent->Item_ID)) ? $item_parent->Item_ID : array();
                if (sizeof($_parentItemid) > 0) {
                    $_pitem_ID = self::itemIDValues($_parentItemid);

                   // parent datatype
                    $_pdatatype = (isset($item_parent->Data_Type)) ? $item_parent->Data_Type : "Unknown";
                    $parent_datatype = self::getDataType($_pdatatype);
                    $_pitem_ID['type'] = ($parent_datatype->name == "Journal" || $parent_datatype->name == "Book") ?
                                          mb_substr($parent_datatype->name, 0, 1) :
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
            $_item = Item::firstOrCreate(
                ['title_id' => $title->id],
                ['parent_id' => $parent_id],
                ['parent_datatype_id' => $parent_datatype_id]
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
                'plat_id' => $platform_id, 'inst_id' => self::$inst, 'yearmon' => self::$yearmon, 'yop' => $yop,
                'datatype_id' => $datatype->id, 'accesstype_id' => $accesstype_id,'accessmethod_id' => $accessmethod_id,
                'total_item_requests' => $ICounts['Total_Item_Requests'],
                'total_item_investigations' => $ICounts['Total_Item_Investigations'],
                'unique_item_requests' => $ICounts['Unique_Item_Requests'],
                'unique_item_investigations' => $ICounts['Unique_Item_Investigations'],
                'limit_exceeded' => $ICounts['Limit_Exceeded'], 'no_license' => $ICounts['No_License']]);
                // 'created_at' => self::$now]);

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
        $platform_id = 1;   //default is blank
        if ($input_platform != "") {
           // UTF8 Encode name if it isnt already UTF-8
            $cur_encoding = mb_detect_encoding($input_platform);
            if ($cur_encoding == "UTF-8" && mb_check_encoding($input_platform, "UTF-8")) {
                $_plat_name = mb_substr($input_platform, 0, intval(config('ccplus.max_name_length')));
            } else {        // force to utf-8
                $_plat_name = mb_substr(utf8_encode($input_platform), 0, intval(config('ccplus.max_name_length')));
            }
            $platform = Platform::firstOrCreate(['name' => $_plat_name]);
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
        $publisher_id = 1;  //default is blank
        if ($input_publisher != "") {
           // UTF8 Encode name if it isnt already UTF-8
            $cur_encoding = mb_detect_encoding($input_publisher);
            if ($cur_encoding == "UTF-8" && mb_check_encoding($input_publisher, "UTF-8")) {
                $_pub_name = mb_substr($input_publisher, 0, intval(config('ccplus.max_name_length')));
            } else {        // force to utf-8
                $_pub_name = mb_substr(utf8_encode($input_publisher), 0, intval(config('ccplus.max_name_length')));
            }
            $publisher = Publisher::firstOrCreate(['name' => $_pub_name]);
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
        $accesstype_id = 1;     // Controlled
        if ($input_type != "") {
           // UTF8 Encode name if it isnt already UTF-8
            $cur_encoding = mb_detect_encoding($input_type);
            if ($cur_encoding == "UTF-8" && mb_check_encoding($input_type, "UTF-8")) {
                $_type_name = mb_substr($input_type, 0, intval(config('ccplus.max_name_length')));
            } else {        // force to utf-8
                $_type_name = mb_substr(utf8_encode($input_type), 0, intval(config('ccplus.max_name_length')));
            }
            $accesstype = AccessType::firstOrCreate(['name' => $_type_name]);
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
           // UTF8 Encode name if it isnt already UTF-8
            $cur_encoding = mb_detect_encoding($input_method);
            if ($cur_encoding == "UTF-8" && mb_check_encoding($input_method, "UTF-8")) {
                $_method_name = mb_substr($input_method, 0, intval(config('ccplus.max_name_length')));
            } else {        // force to utf-8
                $_method_name = mb_substr(utf8_encode($input_method), 0, intval(config('ccplus.max_name_length')));
            }
            $accessmethod = AccessMethod::firstOrCreate(['name' => $_method_name]);
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
       // UTF8 Encode name if it isnt already UTF-8
        $cur_encoding = mb_detect_encoding($input_type);
        if ($cur_encoding == "UTF-8" && mb_check_encoding($input_type, "UTF-8")) {
            $_type_name = mb_substr($input_type, 0, intval(config('ccplus.max_name_length')));
        } else {        // force to utf-8
            $_type_name = mb_substr(utf8_encode($input_type), 0, intval(config('ccplus.max_name_length')));
        }
        $datatype = DataType::firstOrCreate(['name' => $_type_name]);
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
        $sectiontype_id = 1;    // default is blank
        if ($input_type != "") {
           // UTF8 Encode name if it isnt already UTF-8
            $cur_encoding = mb_detect_encoding($input_type);
            if ($cur_encoding == "UTF-8" && mb_check_encoding($input_type, "UTF-8")) {
                $_type_name = mb_substr($input_type, 0, intval(config('ccplus.max_name_length')));
            } else {        // force to utf-8
                $_type_name = mb_substr(utf8_encode($input_type), 0, intval(config('ccplus.max_name_length')));
            }
            $sectiontype = SectionType::firstOrCreate(['name' => $_type_name]);
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
                $Values['ISBN'] = mb_substr($_id->Value, 0, intval(config('ccplus.max_name_length')));
            }
            if ($_id->Type == "Print_ISSN") {
                $Values['ISSN'] = mb_substr($_id->Value, 0, intval(config('ccplus.max_name_length')));
            }
            if ($_id->Type == "Online_ISSN") {
                $Values['eISSN'] = mb_substr($_id->Value, 0, intval(config('ccplus.max_name_length')));
            }
            if ($_id->Type == "DOI") {
                $Values['DOI'] = mb_substr($_id->Value, 0, 256);
            }
            if ($_id->Type == "Proprietary") {
                $Values['PropID'] = mb_substr($_id->Value, 0, 256);
            }
            if ($_id->Type == "URI") {
                $Values['URI'] = mb_substr($_id->Value, 0, 256);
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
    private static function titleFindOrCreate($_title, $ident, $pub = "", $ver = "")
    {
        if (
            $_title == ""
            && $ident['PropID'] = ""
            && $ident['ISBN'] == ""
            && $ident['ISSN'] == ""
            && $ident['eISSN'] == ""
            && $ident['DOI'] == ""
            && $ident['URI'] == ""
        ) {
            return null;
        }

       // UTF8 Encode title if it isnt already UTF-8
        $cur_encoding = mb_detect_encoding($_title);
        if ($cur_encoding == "UTF-8" && mb_check_encoding($_title, "UTF-8")) {
            $input_title = $_title;
        } else {
            $input_title = utf8_encode($_title);    // force to utf-8
        }

       // Build the query to find an existing title; start by setting up the where clause
        $conditions = array();
        if ($ident['type'] == 'B' && $ident['ISBN'] != '') {
            $conditions[] = array('ISBN', '=', $ident['ISBN']);
        }
        if ($ident['type'] == 'J') {
            if ($ident['ISSN'] != '') {
                $conditions[] = array('ISSN', '=', $ident['ISSN']);
            }
            if ($ident['eISSN'] != '') {
                $conditions[] = array('eISSN', '=', $ident['eISSN']);
            }
        }
        if ($ident['type'] == 'I') {
            if ($ident['ISBN'] != '') {
                $conditions[] = array('ISBN', '=', $ident['ISBN']);
            }
            if ($ident['ISSN'] != '') {
                $conditions[] = array('ISSN', '=', $ident['ISSN']);
            }
            if ($ident['eISSN'] != '') {
                $conditions[] = array('eISSN', '=', $ident['eISSN']);
            }
        }
        if ($ident['PropID'] != '') {
            $conditions[] = array('PropID', '=', $ident['PropID']);
        }
        if ($ident['DOI'] != '') {
            $conditions[] = array('DOI', '=', $ident['DOI']);
        }
        if ($ident['URI'] != '') {
            $conditions[] = array('URI', '=', $ident['URI']);
        }

       // Run the query
        $matches = Title::where('type', '=', $ident['type'])->where(function ($query) use ($conditions, $input_title) {
                              $query->where('Title', '=', $input_title)
                                    ->orWhere($conditions);
        })->get();

       // Loop through all the possibles
        $save_it = false;
        foreach ($matches as $match) {
            $matched = false;
           // If Title matches and other input fields are null, call it a match
            if (
                $input_title != ""
                && $input_title == $match->Title
                && ( ($ident['PropID'] == ""
                && $ident['ISSN'] == ""
                && $ident['eISSN'] == ""
                      && $ident['DOI'] == ""
                      && $ident['URI'] == "")
                     || ($match->PropID == ""
                     && $match->ISSN == ""
                     && $match->eISSN == ""
                     && $match->ISBN == ""
                      && $match->DOI == ""
                      && $match->URI == "")
                   )
            ) {
                 $matched = true;

           // If URI matches and other input fields are null, call it a match
            } else {
                if (
                    $ident['URI'] != ""
                    && $ident['URI'] == $match->URI
                    && ( ($input_title == ""
                    && $ident['PropID'] == ""
                    && $ident['ISSN'] == ""
                          && $ident['eISSN'] == ""
                          && $ident['DOI'] == "")
                         || ($match->title == ""
                         && $match->PropID == ""
                         && $match->ISSN == ""
                         && $match->ISBN == ""
                          && $match->eISSN == ""
                          && $match->DOI == "")
                       )
                ) {
                     $matched = true;
                }
            }

           // Test the remaining identifiers - except URI. If match is found, update fields in the
           // model that we have values for.
            if (
                $matched ||
                ($ident['PropID'] != "" &&
                $match->PropID == $ident['PropID']) ||
                ($ident['DOI'] != "" &&
                $match->DOI == $ident['DOI']) ||
                ($ident['ISSN'] != "" &&
                $match->ISSN == $ident['ISSN']) ||
                ($ident['eISSN'] != "" &&
                $match->eISSN == $ident['eISSN'])
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
        } catch (Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }
}
