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
            // TR Report fields
             DB::table($table)->insert(['id' => 1, 'report_id' => 1, 'legend' => 'Title', 'group_it' => 1,
                 'qry' => 'TI.Title', 'qry_as' => 'Title', 'active' => 1]);
             DB::table($table)->insert(['id' => 2, 'report_id' => 1, 'legend' => 'Provider', 'group_it' => 1,
                 'joins' => '_conso_.providers as PROV', 'qry' => 'PROV.name', 'qry_as' => 'provider',
                 'active' => 1, 'report_filter_id' => 1]);
             DB::table($table)->insert(['id' => 3, 'report_id' => 1, 'legend' => 'Publisher', 'group_it' => 1,
                 'joins' => '_global_.publishers as PUBL', 'qry' => 'PUBL.name', 'qry_as' => 'publisher']);
             DB::table($table)->insert(['id' => 4, 'report_id' => 1, 'legend' => 'Platform', 'group_it' => 1,
                 'joins' => '_global_.platforms as PLAT', 'qry' => 'PLAT.name', 'qry_as' => 'platform',
                 'active' => 1, 'report_filter_id' => 2]);
             DB::table($table)->insert(['id' => 5, 'report_id' => 1, 'legend' => 'Institution', 'group_it' => 1,
                 'joins' => '_conso_.institutions as INST', 'qry' => 'INST.name', 'qry_as' => 'institution',
                 'active' => 1, 'report_filter_id' => 3]);
             DB::table($table)->insert(['id' => 6, 'report_id' => 1, 'legend' => 'Data Type', 'group_it' => 1,
                 'joins' => '_global_.datatypes as DTYP', 'qry' => 'DTYP.name', 'qry_as' => 'datatype',
                 'report_filter_id' => 4]);
             DB::table($table)->insert(['id' => 7, 'report_id' => 1, 'legend' => 'Section Type', 'group_it' => 1,
                 'joins' => '_global_.sectiontypes as STYP', 'qry' => 'STYP.name', 'qry_as' => 'sectiontype',
                 'report_filter_id' => 7]);
             DB::table($table)->insert(['id' => 8, 'report_id' => 1, 'legend' => 'Access Type', 'group_it' => 1,
                 'joins' => '_global_.accesstypes as ATYP', 'qry' => 'ATYP.name', 'qry_as' => 'accesstype',
                 'report_filter_id' => 5]);
             DB::table($table)->insert(['id' => 9, 'report_id' => 1, 'legend' => 'Access Method', 'group_it' => 1,
                 'joins' => '_global_.accessmethods as AMTH', 'qry' => 'AMTH.name', 'qry_as' => 'accessmethod',
                 'report_filter_id' => 6]);
             DB::table($table)->insert(['id' => 10, 'report_id' => 1, 'legend' => 'Publication Yr', 'qry' => 'YOP',
                 'qry_as' => 'YOP', 'group_it' => 1]);
             DB::table($table)->insert(['id' => 11, 'report_id' => 1, 'legend' => 'ISBN', 'group_it' => 1,
                 'qry' => 'TI.ISBN', 'qry_as' => 'ISBN']);
             DB::table($table)->insert(['id' => 12, 'report_id' => 1, 'legend' => 'Print ISSN', 'group_it' => 1,
                 'qry' => 'TI.ISSN', 'qry_as' => 'ISSN']);
             DB::table($table)->insert(['id' => 13, 'report_id' => 1, 'legend' => 'Online ISSN', 'group_it' => 1,
                 'qry' => 'TI.eISSN', 'qry_as' => 'eISSN']);
             DB::table($table)->insert(['id' => 14, 'report_id' => 1, 'legend' => 'URI', 'group_it' => 1,
                 'qry' => 'TI.URI', 'qry_as' => 'URI']);
             DB::table($table)->insert(['id' => 15, 'report_id' => 1, 'legend' => 'DOI', 'group_it' => 1,
                 'qry' => 'TI.DOI', 'qry_as' => 'DOI']);
             DB::table($table)->insert(['id' => 16, 'report_id' => 1, 'legend' => 'Proprietary ID', 'group_it' => 1,
                 'qry' => 'TI.PropID', 'qry_as' => 'PropID']);

           // TR Report summing-by-yearmon fields
            DB::table($table)->insert(['id' => 17,'report_id' => 1, 'legend' => 'Total Item Investigations',
                // 'qry' => 'sum(total_item_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",total_item_investigations,0))',
                'qry_as' => 'total_item_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 18,'report_id' => 1, 'legend' => 'Total Item Requests',
                // 'qry' => 'sum(total_item_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",total_item_requests,0))',
                'qry_as' => 'total_item_requests', 'active' => 1, 'reload' => 0]);
            DB::table($table)->insert(['id' => 19,'report_id' => 1, 'legend' => 'Unique Item Investigations',
                // 'qry' => 'sum(unique_item_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_item_investigations,0))',
                'qry_as' => 'unique_item_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 20,'report_id' => 1, 'legend' => 'Unique Item Requests',
                // 'qry' => 'sum(unique_item_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_item_requests,0))',
                'qry_as' => 'unique_item_requests', 'reload' => 0]);
            DB::table($table)->insert(['id' => 21,'report_id' => 1, 'legend' => 'Unique Title Investigations',
                // 'qry' => 'sum(unique_title_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_title_investigations,0))',
                'qry_as' => 'unique_title_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 22,'report_id' => 1, 'legend' => 'Unique Title Requests',
                // 'qry' => 'sum(unique_title_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_title_requests,0))',
                'qry_as' => 'unique_title_requests', 'reload' => 0]);
            DB::table($table)->insert(['id' => 23,'report_id' => 1, 'legend' => 'Limit Exceeded',
                // 'qry' => 'sum(limit_exceeded)',
                'qry' => 'sum(IF (yearmon="@YM@",limit_exceeded,0))',
                'qry_as' => 'limit_exceeded', 'reload' => 0]);
            DB::table($table)->insert(['id' => 24,'report_id' => 1, 'legend' => 'No License',
                // 'qry' => 'sum(no_license)',
                'qry' => 'sum(IF (yearmon="@YM@",no_license,0))',
                'qry_as' => 'no_license', 'reload' => 0]);
            // DB::table($table)->insert(['id' => 25, 'report_id' => 1, 'legend' => 'Reporting Period Total',
            //     'reload' => 0]);

           // DR Report Info and Optional fields
            DB::table($table)->insert(['id' => 26, 'report_id' => 2, 'legend' => 'Database',
                'qry' => 'DB.name', 'qry_as' => 'Dbase', 'group_it' => 1, 'active' => 1]);
            DB::table($table)->insert(['id' => 27, 'report_id' => 2, 'legend' => 'Provider', 'group_it' => 1,
                'joins' => '_conso_.providers as PROV', 'qry' => 'PROV.name', 'qry_as' => 'provider',
                'active' => 1, 'report_filter_id' => 1]);
            DB::table($table)->insert(['id' => 28, 'report_id' => 2, 'legend' => 'Publisher', 'group_it' => 1,
                'joins' => '_global_.publishers as PUBL', 'qry' => 'PUBL.name', 'qry_as' => 'publisher']);
            DB::table($table)->insert(['id' => 29, 'report_id' => 2, 'legend' => 'Platform', 'group_it' => 1,
                'joins' => '_global_.platforms as PLAT', 'qry' => 'PLAT.name', 'qry_as' => 'platform',
                'active' => 1, 'report_filter_id' => 2]);
            DB::table($table)->insert(['id' => 30, 'report_id' => 2, 'legend' => 'Institution', 'group_it' => 1,
                'joins' => '_conso_.institutions as INST', 'qry' => 'INST.name', 'qry_as' => 'institution',
                'active' => 1, 'report_filter_id' => 3]);
            DB::table($table)->insert(['id' => 31, 'report_id' => 2, 'legend' => 'Data Type', 'group_it' => 1,
                'joins' => '_global_.datatypes as DTYP', 'qry' => 'DTYP.name', 'qry_as' => 'datatype',
                'report_filter_id' => 4]);
            DB::table($table)->insert(['id' => 32, 'report_id' => 2, 'legend' => 'Access Method', 'group_it' => 1,
                'joins' => '_global_.accessmethods as AMTH', 'qry' => 'AMTH.name', 'qry_as' => 'accessmethod',
                'report_filter_id' => 6]);
            DB::table($table)->insert(['id' => 33, 'report_id' => 2, 'legend' => 'Proprietary ID', 'group_it' => 1,
                'qry' => 'DB.PropID', 'qry_as' => 'PropID']);

           // DR Report summing-by-yearmon fields
            DB::table($table)->insert(['id' => 34,'report_id' => 2, 'legend' => 'Searches Automated',
                // 'qry' => 'sum(searches_automated)',
                'qry' => 'sum(IF (yearmon="@YM@",searches_automated,0))',
                'qry_as' => 'searches_automated', 'reload' => 0]);
            DB::table($table)->insert(['id' => 35,'report_id' => 2, 'legend' => 'Searches Federated',
                // 'qry' => 'sum(searches_federated)',
                'qry' => 'sum(IF (yearmon="@YM@",searches_federated,0))',
                'qry_as' => 'searches_federated', 'reload' => 0]);
            DB::table($table)->insert(['id' => 36,'report_id' => 2, 'legend' => 'Searches Regular',
                // 'qry' => 'sum(searches_regular)',
                'qry' => 'sum(IF (yearmon="@YM@",searches_regular,0))',
                'qry_as' => 'searches_regular', 'active' => 1, 'reload' => 0]);
            DB::table($table)->insert(['id' => 37,'report_id' => 2, 'legend' => 'Total Item Investigations',
                // 'qry' => 'sum(total_item_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",total_item_investigations,0))',
                'qry_as' => 'total_item_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 38,'report_id' => 2, 'legend' => 'Total Item Requests',
                // 'qry' => 'sum(total_item_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",total_item_requests,0))',
                'qry_as' => 'total_item_requests', 'active' => 1, 'reload' => 0]);
            DB::table($table)->insert(['id' => 39,'report_id' => 2, 'legend' => 'Unique Item Investigations',
                // 'qry' => 'sum(unique_item_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_item_investigations,0))',
                'qry_as' => 'unique_item_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 40,'report_id' => 2, 'legend' => 'Unique Item Requests',
                // 'qry' => 'sum(unique_item_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_item_requests,0))',
                'qry_as' => 'unique_item_requests', 'reload' => 0]);
            DB::table($table)->insert(['id' => 41,'report_id' => 2, 'legend' => 'Unique Title Investigations',
                // 'qry' => 'sum(unique_title_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_title_investigations,0))',
                'qry_as' => 'unique_title_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 42,'report_id' => 2, 'legend' => 'Unique Title Requests',
                // 'qry' => 'sum(unique_title_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_title_requests,0))',
                'qry_as' => 'unique_title_requests', 'reload' => 0]);
            DB::table($table)->insert(['id' => 43,'report_id' => 2, 'legend' => 'Limit Exceeded',
                // 'qry' => 'sum(limit_exceeded)',
                'qry' => 'sum(IF (yearmon="@YM@",limit_exceeded,0))',
                'qry_as' => 'limit_exceeded', 'reload' => 0]);
            DB::table($table)->insert(['id' => 44,'report_id' => 2, 'legend' => 'No License',
                // 'qry' => 'sum(no_license)',
                'qry' => 'sum(IF (yearmon="@YM@",no_license,0))',
                'qry_as' => 'no_license', 'reload' => 0]);
            // DB::table($table)->insert(['id' => 45, 'report_id' => 2, 'legend' => 'Reporting Period Total',
            //     'reload' => 0]);

            // PR Report Info and Optional fields
            DB::table($table)->insert(['id' => 46,'report_id' => 3, 'legend' => 'Platform', 'group_it' => 1,
                'joins' => '_global_.platforms as PLAT', 'qry' => 'PLAT.name', 'qry_as' => 'platform',
                'active' => 1]);
            DB::table($table)->insert(['id' => 47,'report_id' => 3, 'legend' => 'Provider', 'group_it' => 1,
                'joins' => '_conso_.providers as PROV', 'qry' => 'PROV.name', 'qry_as' => 'provider',
                'report_filter_id' => 1]);
            DB::table($table)->insert(['id' => 48,'report_id' => 3, 'legend' => 'Institution', 'group_it' => 1,
                'joins' => '_conso_.institutions as INST', 'qry' => 'INST.name', 'qry_as' => 'institution',
                'report_filter_id' => 3]);
            DB::table($table)->insert(['id' => 49,'report_id' => 3, 'legend' => 'Data Type', 'group_it' => 1,
                'joins' => '_global_.datatypes as DTYP', 'qry' => 'DTYP.name', 'qry_as' => 'datatype',
                'report_filter_id' => 4]);
            DB::table($table)->insert(['id' => 50,'report_id' => 3, 'legend' => 'Access Method', 'group_it' => 1,
                'joins' => '_global_.accessmethods as AMTH', 'qry' => 'AMTH.name', 'qry_as' => 'accessmethod',
                'report_filter_id' => 6]);

           // PR Report summing-by-yearmon fields
            DB::table($table)->insert(['id' => 51, 'report_id' => 3, 'legend' => 'Searches Platform',
                // 'qry' => 'sum(searches_platform)',
                'qry' => 'sum(IF (yearmon="@YM@",searches_platform,0))',
                'qry_as' => 'searches_platform', 'active' => 1, 'reload' => 0]);
            DB::table($table)->insert(['id' => 52, 'report_id' => 3, 'legend' => 'Total Item Investigations',
                // 'qry' => 'sum(total_item_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",total_item_investigations,0))',
                'qry_as' => 'total_item_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 53, 'report_id' => 3, 'legend' => 'Total Item Requests',
                // 'qry' => 'sum(total_item_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",total_item_requests,0))',
                'qry_as' => 'total_item_requests', 'active' => 1, 'reload' => 0]);
            DB::table($table)->insert(['id' => 54, 'report_id' => 3, 'legend' => 'Unique Item Investigations',
                // 'qry' => 'sum(unique_item_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_item_investigations,0))',
                'qry_as' => 'unique_item_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 55, 'report_id' => 3, 'legend' => 'Unique Item Requests',
                // 'qry' => 'sum(unique_item_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_item_requests,0))',
                'qry_as' => 'unique_item_requests', 'active' => 1, 'reload' => 0]);
            DB::table($table)->insert(['id' => 56, 'report_id' => 3, 'legend' => 'Unique Title Investigations',
                // 'qry' => 'sum(unique_title_investigations)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_title_investigations,0))',
                'qry_as' => 'unique_title_investigations', 'reload' => 0]);
            DB::table($table)->insert(['id' => 57, 'report_id' => 3, 'legend' => 'Unique Title Requests',
                // 'qry' => 'sum(unique_title_requests)',
                'qry' => 'sum(IF (yearmon="@YM@",unique_title_requests,0))',
                'qry_as' => 'unique_title_requests', 'active' => 1, 'reload' => 0]);
            // DB::table($table)->insert(['id' => 58,'report_id' => 3, 'legend' => 'Reporting Period Total',
            //     'reload' => 0]);

           // IR Report Info and Optional fields
            DB::table($table)->insert(['id' => 59, 'report_id' => 4, 'legend' => 'Item', 'group_it' => 1,
                'joins' => '_global_.titles as TI', 'qry' => 'TI.Title', 'qry_as' => 'Item', 'active' => 1]);
            DB::table($table)->insert(['id' => 60, 'report_id' => 4, 'legend' => 'Provider', 'group_it' => 1,
                'joins' => '_conso_.providers as PROV', 'qry' => 'PROV.name', 'qry_as' => 'provider', 'active' => 1,
                'report_filter_id' => 1]);
            DB::table($table)->insert(['id' => 61, 'report_id' => 4, 'legend' => 'Publisher', 'group_it' => 1,
                'joins' => '_global_.publishers as PUBL', 'qry' => 'PUBL.name', 'qry_as' => 'publisher']);
            DB::table($table)->insert(['id' => 62, 'report_id' => 4, 'legend' => 'Platform', 'group_it' => 1,
                'joins' => '_global_.platforms as PLAT', 'qry' => 'PLAT.name', 'qry_as' => 'platform', 'active' => 1,
                'report_filter_id' => 2]);
            DB::table($table)->insert(['id' => 63, 'report_id' => 4, 'legend' => 'Institution', 'group_it' => 1,
                'joins' => '_conso_.institutions as INST', 'qry' => 'INST.name', 'qry_as' => 'institution',
                'active' => 1, 'report_filter_id' => 3]);
            DB::table($table)->insert(['id' => 64, 'report_id' => 4, 'legend' => 'Authors', 'qry' => 'authors',
                'qry_as' => 'authors', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 65, 'report_id' => 4, 'legend' => 'Publication Date',
                'qry_as' => 'pub_date', 'qry' => 'TI.pub_date']);
            DB::table($table)->insert(['id' => 66, 'report_id' => 4, 'legend' => 'Article Version',
                'qry_as' => 'article_version', 'qry' => 'TI.article_version']);
            DB::table($table)->insert(['id' => 67, 'report_id' => 4, 'legend' => 'DOI', 'group_it' => 1,
                'qry' => 'TI.DOI', 'qry_as' => 'DOI']);
            DB::table($table)->insert(['id' => 68, 'report_id' => 4, 'legend' => 'Proprietary ID', 'group_it' => 1,
                'qry' => 'TI.PropID', 'qry_as' => 'PropID']);
            DB::table($table)->insert(['id' => 69, 'report_id' => 4, 'legend' => 'ISBN', 'group_it' => 1,
                'qry' => 'TI.ISBN', 'qry_as' => 'ISBN']);
            DB::table($table)->insert(['id' => 70, 'report_id' => 4, 'legend' => 'Print ISSN', 'group_it' => 1,
                'qry' => 'TI.ISSN', 'qry_as' => 'ISSN']);
            DB::table($table)->insert(['id' => 71, 'report_id' => 4, 'legend' => 'Online ISSN', 'group_it' => 1,
                'qry' => 'TI.eISSN', 'qry_as' => 'eISSN']);
            DB::table($table)->insert(['id' => 72, 'report_id' => 4, 'legend' => 'URI', 'group_it' => 1,
                'qry' => 'TI.URI', 'qry_as' => 'URI']);
            DB::table($table)->insert(['id' => 73, 'report_id' => 4, 'legend' => 'Parent Title']);
            DB::table($table)->insert(['id' => 74, 'report_id' => 4, 'legend' => 'Parent Authors']);
            DB::table($table)->insert(['id' => 75, 'report_id' => 4, 'legend' => 'Parent Publication Date']);
            DB::table($table)->insert(['id' => 76, 'report_id' => 4, 'legend' => 'Parent Article Version']);
            DB::table($table)->insert(['id' => 77, 'report_id' => 4, 'legend' => 'Parent Data Type']);
            DB::table($table)->insert(['id' => 78, 'report_id' => 4, 'legend' => 'Parent DOI']);
            DB::table($table)->insert(['id' => 79, 'report_id' => 4, 'legend' => 'Parent Proprietary ID']);
            DB::table($table)->insert(['id' => 80, 'report_id' => 4, 'legend' => 'Parent ISBN']);
            DB::table($table)->insert(['id' => 81, 'report_id' => 4, 'legend' => 'Parent Print ISSN']);
            DB::table($table)->insert(['id' => 82, 'report_id' => 4, 'legend' => 'Parent Online ISSN']);
            DB::table($table)->insert(['id' => 83, 'report_id' => 4, 'legend' => 'Parent URI']);
            DB::table($table)->insert(['id' => 84, 'report_id' => 4, 'legend' => 'Component Title']);
            DB::table($table)->insert(['id' => 85, 'report_id' => 4, 'legend' => 'Component Authors']);
            DB::table($table)->insert(['id' => 86, 'report_id' => 4, 'legend' => 'Component Publication Date']);
            DB::table($table)->insert(['id' => 87, 'report_id' => 4, 'legend' => 'Component Data Type']);
            DB::table($table)->insert(['id' => 88, 'report_id' => 4, 'legend' => 'Component DOI']);
            DB::table($table)->insert(['id' => 89, 'report_id' => 4, 'legend' => 'Component Proprietary ID']);
            DB::table($table)->insert(['id' => 90, 'report_id' => 4, 'legend' => 'Component ISBN']);
            DB::table($table)->insert(['id' => 91, 'report_id' => 4, 'legend' => 'Component Print ISSN']);
            DB::table($table)->insert(['id' => 92, 'report_id' => 4, 'legend' => 'Component Online ISSN']);
            DB::table($table)->insert(['id' => 93, 'report_id' => 4, 'legend' => 'Component URI']);
            DB::table($table)->insert(['id' => 94, 'report_id' => 4, 'legend' => 'Data Type', 'qry' => 'datatype_id',
                'qry_as' => 'datatype', 'group_it' => 1, 'report_filter_id' => 4]);
            DB::table($table)->insert(['id' => 95, 'report_id' => 4, 'legend' => 'YOP', 'qry' => 'YOP',
                'qry_as' => 'YOP', 'group_it' => 1]);
            DB::table($table)->insert(['id' => 96, 'report_id' => 4, 'legend' => 'Access Type', 'group_it' => 1,
                'joins' => '_global_.accesstypes as ATYP', 'qry' => 'ATYP.name', 'qry_as' => 'accesstype',
                'report_filter_id' => 5]);
            DB::table($table)->insert(['id' => 97, 'report_id' => 4, 'legend' => 'Access Method', 'group_it' => 1,
                'joins' => '_global_.accessmethods as AMTH', 'qry' => 'AMTH.name', 'qry_as' => 'accessmethod',
                'report_filter_id' => 6]);

           // IR Report summing-by-yearmon fields
            DB::table($table)->insert(['id' => 98, 'report_id' => 4, 'legend' => 'Total Item Requests',
                // 'qry' => 'sum(total_item_requests)',
                'qry' => 'sum(IF (yearmon=""@YM@"",total_item_requests,0))',
                'qry_as' => 'total_item_requests', 'active' => 1, 'reload' => 0]);
            DB::table($table)->insert(['id' => 99, 'report_id' => 4, 'legend' => 'Unique Item Requests',
                // 'qry' => 'sum(unique_item_requests)',
                'qry' => 'sum(IF (yearmon=""@YM@"",unique_item_requests,0))',
                'qry_as' => 'unique_item_requests', 'reload' => 0]);
            // DB::table($table)->insert(['id' => 100, 'report_id' => 4, 'legend' => 'Reporting Period Total',
            //     'reload' => 0]);


            Schema::enableForeignKeyConstraints();
        }
    }
}
