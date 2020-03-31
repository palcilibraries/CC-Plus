<?php

use Illuminate\Database\Seeder;

class ReportFieldsTableSeeder extends Seeder
{
    /**
     * Seed the reportfields table in the global database
     *
     * @return void
     */
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        // Make sure we're talking to the global database
        $_db = \Config::get('database.connections.globaldb.database');
        $table = $_db . ".reportfields";

      // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
           // TR Report alertable fields
            DB::table($table)->insert(['id' => 1,'report_id' => 1, 'legend' => 'Total Item Investigations',
                                       'qry' => 'sum(total_item_investigations) as total_item_investigations']);
            DB::table($table)->insert(['id' => 2,'report_id' => 1, 'legend' => 'Total Item Requests',
                                       'qry' => 'sum(total_item_requests) as total_item_requests']);
            DB::table($table)->insert(['id' => 3,'report_id' => 1, 'legend' => 'Unique Item Investigations',
                                       'qry' => 'sum(unique_item_investigations) as unique_item_investigations']);
            DB::table($table)->insert(['id' => 4,'report_id' => 1, 'legend' => 'Unique Item Requests',
                                       'qry' => 'sum(unique_item_requests) as unique_item_requests']);
            DB::table($table)->insert(['id' => 5,'report_id' => 1, 'legend' => 'Unique Title Investigations',
                                       'qry' => 'sum(unique_title_investigations) as unique_title_investigations']);
            DB::table($table)->insert(['id' => 6,'report_id' => 1, 'legend' => 'Unique Title Requests',
                                       'qry' => 'sum(unique_title_requests) as unique_title_requests']);
            DB::table($table)->insert(['id' => 7,'report_id' => 1, 'legend' => 'Limit Exceeded',
                                       'qry' => 'sum(limit_exceeded) as limit_exceeded']);
            DB::table($table)->insert(['id' => 8,'report_id' => 1, 'legend' => 'No License',
                                       'qry' => 'sum(no_license) as no_license']);

           // TR Report info and optional fields
            DB::table($table)->insert(['id' =>  9, 'report_id' => 1, 'legend' => 'Title', 'qry' => 'title_id',
                                       'group_it' => 1]);
            DB::table($table)->insert(['id' => 10, 'report_id' => 1, 'legend' => 'Provider',
                                       'qry' => 'prov_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 11, 'report_id' => 1, 'legend' => 'Publisher',
                                       'qry' => 'publisher_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 12, 'report_id' => 1, 'legend' => 'Platform',
                                       'qry' => 'plat_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 13, 'report_id' => 1, 'legend' => 'Institution',
                                       'qry' => 'inst_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 14, 'report_id' => 1, 'legend' => 'DOI']);
            DB::table($table)->insert(['id' => 15, 'report_id' => 1, 'legend' => 'Proprietary ID']);
            DB::table($table)->insert(['id' => 16, 'report_id' => 1, 'legend' => 'URI']);
            DB::table($table)->insert(['id' => 17, 'report_id' => 1, 'legend' => 'YOP',
                                       'qry' => 'YOP', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 18, 'report_id' => 1, 'legend' => 'ISBN']);
            DB::table($table)->insert(['id' => 19, 'report_id' => 1, 'legend' => 'Print ISSN']);
            DB::table($table)->insert(['id' => 20, 'report_id' => 1, 'legend' => 'Online ISSN']);
            DB::table($table)->insert(['id' => 21, 'report_id' => 1, 'legend' => 'Data Type','rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 22, 'report_id' => 1, 'legend' => 'Section Type', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 23, 'report_id' => 1, 'legend' => 'Access Type', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 24, 'report_id' => 1, 'legend' => 'Access Method', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 25, 'report_id' => 1, 'legend' => 'Reporting Period Total']);

           // DR Report alertable fields
            DB::table($table)->insert(['id' => 26,'report_id' => 2, 'legend' => 'Searches Automated',
                                       'qry' => 'sum(searches_automated) as searches_automated']);
            DB::table($table)->insert(['id' => 27,'report_id' => 2, 'legend' => 'Searches Federated',
                                       'qry' => 'sum(searches_federated) as searches_federated']);
            DB::table($table)->insert(['id' => 28,'report_id' => 2, 'legend' => 'Searches Regular',
                                       'qry' => 'sum(searches_regular) as searches_regular']);
            DB::table($table)->insert(['id' => 29,'report_id' => 2, 'legend' => 'Total Item Investigations',
                                       'qry' => 'sum(total_item_investigations) as total_item_investigations']);
            DB::table($table)->insert(['id' => 30,'report_id' => 2, 'legend' => 'Total Item Requests',
                                       'qry' => 'sum(total_item_requests) as total_item_requests']);
            DB::table($table)->insert(['id' => 31,'report_id' => 2, 'legend' => 'Unique Item Investigations',
                                       'qry' => 'sum(unique_item_investigations) as unique_item_investigations']);
            DB::table($table)->insert(['id' => 32,'report_id' => 2, 'legend' => 'Unique Item Requests',
                                       'qry' => 'sum(unique_item_requests) as unique_item_requests']);
            DB::table($table)->insert(['id' => 33,'report_id' => 2, 'legend' => 'Unique Title Investigations',
                                       'qry' => 'sum(unique_title_investigations) as unique_title_investigations']);
            DB::table($table)->insert(['id' => 34,'report_id' => 2, 'legend' => 'Unique Title Requests',
                                       'qry' => 'sum(unique_title_requests) as unique_title_requests']);
            DB::table($table)->insert(['id' => 35,'report_id' => 2, 'legend' => 'Limit Exceeded',
                                       'qry' => 'sum(limit_exceeded) as limit_exceeded']);
            DB::table($table)->insert(['id' => 36,'report_id' => 2, 'legend' => 'No License',
                                       'qry' => 'sum(no_license) as no_license']);
           // DR Report Info and Optional fields
            DB::table($table)->insert(['id' => 37, 'report_id' => 2, 'legend' => 'Database', 'qry' => 'db_id',
                                       'group_it' => 1]);
            DB::table($table)->insert(['id' => 38, 'report_id' => 2, 'legend' => 'Provider', 'qry' => 'prov_id',
                                       'group_it' => 1]);
            DB::table($table)->insert(['id' => 39, 'report_id' => 2, 'legend' => 'Publisher', 'qry' => 'publisher_id',
                                       'group_it' => 1]);
            DB::table($table)->insert(['id' => 40, 'report_id' => 2, 'legend' => 'Platform', 'qry' => 'plat_id',
                                       'group_it' => 1]);
            DB::table($table)->insert(['id' => 41, 'report_id' => 2, 'legend' => 'Institution', 'qry' => 'inst_id',
                                       'group_it' => 1]);
            DB::table($table)->insert(['id' => 42, 'report_id' => 2, 'legend' => 'Proprietary ID']);
            DB::table($table)->insert(['id' => 43, 'report_id' => 2, 'legend' => 'Data Type', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 44, 'report_id' => 2, 'legend' => 'Access Method', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 45, 'report_id' => 2, 'legend' => 'Reporting Period Total']);

           // PR Report alertable fields
            DB::table($table)->insert(['id' => 46, 'report_id' => 3, 'legend' => 'Searches Platform',
                                       'qry' => 'sum(searches_platform) as searches_platform']);
            DB::table($table)->insert(['id' => 47, 'report_id' => 3, 'legend' => 'Total Item Investigations',
                                       'qry' => 'sum(total_item_investigations) as total_item_investigations']);
            DB::table($table)->insert(['id' => 48, 'report_id' => 3, 'legend' => 'Total Item Requests',
                                       'qry' => 'sum(total_item_requests) as total_item_requests']);
            DB::table($table)->insert(['id' => 49, 'report_id' => 3, 'legend' => 'Unique Item Investigations',
                                       'qry' => 'sum(unique_item_investigations) as unique_item_investigations']);
            DB::table($table)->insert(['id' => 50, 'report_id' => 3, 'legend' => 'Unique Item Requests',
                                       'qry' => 'sum(unique_item_requests) as unique_item_requests']);
            DB::table($table)->insert(['id' => 51, 'report_id' => 3, 'legend' => 'Unique Title Investigations',
                                       'qry' => 'sum(unique_title_investigations) as unique_title_investigations']);
            DB::table($table)->insert(['id' => 52, 'report_id' => 3, 'legend' => 'Unique Title Requests',
                                       'qry' => 'sum(unique_title_requests) as unique_title_requests']);
           // PR Report Info and Optional fields
            DB::table($table)->insert(['id' => 53,'report_id' => 3, 'legend' => 'Platform',
                                       'qry' => 'id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 54,'report_id' => 3, 'legend' => 'Provider',
                                       'qry' => 'prov_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 55,'report_id' => 3, 'legend' => 'Institution',
                                       'qry' => 'inst_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 56,'report_id' => 3, 'legend' => 'Data Type', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 57,'report_id' => 3, 'legend' => 'Access Method', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 58,'report_id' => 3, 'legend' => 'Reporting Period Total']);

           // IR Report alertable fields
            DB::table($table)->insert(['id' => 59, 'report_id' => 4, 'legend' => 'Total Item Requests',
                                       'qry' => 'sum(total_item_requests) as total_item_requests']);
            DB::table($table)->insert(['id' => 60, 'report_id' => 4, 'legend' => 'Unique Item Requests',
                                       'qry' => 'sum(unique_item_requests) as unique_item_requests']);
           // IR Report Info and Optional fields
            DB::table($table)->insert(['id' => 61, 'report_id' => 4, 'legend' => 'Item',
                                       'qry' => 'id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 62, 'report_id' => 4, 'legend' => 'Provider',
                                       'qry' => 'prov_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 63, 'report_id' => 4, 'legend' => 'Publisher',
                                       'qry' => 'publisher_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 64, 'report_id' => 4, 'legend' => 'Platform',
                                       'qry' => 'plat_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 65, 'report_id' => 4, 'legend' => 'Institution',
                                       'qry' => 'inst_id', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 66, 'report_id' => 4, 'legend' => 'Authors']);
            DB::table($table)->insert(['id' => 67, 'report_id' => 4, 'legend' => 'Publication Date']);
            DB::table($table)->insert(['id' => 68, 'report_id' => 4, 'legend' => 'Article Version']);
            DB::table($table)->insert(['id' => 69, 'report_id' => 4, 'legend' => 'DOI']);
            DB::table($table)->insert(['id' => 70, 'report_id' => 4, 'legend' => 'Proprietary ID']);
            DB::table($table)->insert(['id' => 71, 'report_id' => 4, 'legend' => 'ISBN']);
            DB::table($table)->insert(['id' => 72, 'report_id' => 4, 'legend' => 'Print ISSN']);
            DB::table($table)->insert(['id' => 73, 'report_id' => 4, 'legend' => 'Online ISSN']);
            DB::table($table)->insert(['id' => 74, 'report_id' => 4, 'legend' => 'URI']);
            DB::table($table)->insert(['id' => 75, 'report_id' => 4, 'legend' => 'Parent Title']);
            DB::table($table)->insert(['id' => 76, 'report_id' => 4, 'legend' => 'Parent Authors']);
            DB::table($table)->insert(['id' => 77, 'report_id' => 4, 'legend' => 'Parent Publication Date']);
            DB::table($table)->insert(['id' => 78, 'report_id' => 4, 'legend' => 'Parent Article Version']);
            DB::table($table)->insert(['id' => 79, 'report_id' => 4, 'legend' => 'Parent Data Type']);
            DB::table($table)->insert(['id' => 80, 'report_id' => 4, 'legend' => 'Parent DOI']);
            DB::table($table)->insert(['id' => 81, 'report_id' => 4, 'legend' => 'Parent Proprietary ID']);
            DB::table($table)->insert(['id' => 82, 'report_id' => 4, 'legend' => 'Parent ISBN']);
            DB::table($table)->insert(['id' => 83, 'report_id' => 4, 'legend' => 'Parent Print ISSN']);
            DB::table($table)->insert(['id' => 84, 'report_id' => 4, 'legend' => 'Parent Online ISSN']);
            DB::table($table)->insert(['id' => 85, 'report_id' => 4, 'legend' => 'Parent URI']);
            DB::table($table)->insert(['id' => 86, 'report_id' => 4, 'legend' => 'Component Title']);
            DB::table($table)->insert(['id' => 87, 'report_id' => 4, 'legend' => 'Component Authors']);
            DB::table($table)->insert(['id' => 88, 'report_id' => 4, 'legend' => 'Component Publication Date']);
            DB::table($table)->insert(['id' => 89, 'report_id' => 4, 'legend' => 'Component Data Type']);
            DB::table($table)->insert(['id' => 90, 'report_id' => 4, 'legend' => 'Component DOI']);
            DB::table($table)->insert(['id' => 91, 'report_id' => 4, 'legend' => 'Component Proprietary ID']);
            DB::table($table)->insert(['id' => 92, 'report_id' => 4, 'legend' => 'Component ISBN']);
            DB::table($table)->insert(['id' => 93, 'report_id' => 4, 'legend' => 'Component Print ISSN']);
            DB::table($table)->insert(['id' => 94, 'report_id' => 4, 'legend' => 'Component Online ISSN']);
            DB::table($table)->insert(['id' => 95, 'report_id' => 4, 'legend' => 'Component URI']);
            DB::table($table)->insert(['id' => 96, 'report_id' => 4, 'legend' => 'Data Type', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 97, 'report_id' => 4, 'legend' => 'YOP', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 98, 'report_id' => 4, 'legend' => 'Access Type', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 99, 'report_id' => 4, 'legend' => 'Access Method', 'rebuild_it' => 1]);
            DB::table($table)->insert(['id' => 100, 'report_id' => 4, 'legend' => 'Reporting Period Total']);

            Schema::enableForeignKeyConstraints();
        }
    }
}
