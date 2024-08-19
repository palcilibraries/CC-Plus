<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTrReportDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tr_report_data', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->bigInteger('title_id')->unsigned();             // Required
            $table->unsignedInteger('prov_id');                     // Required
            $table->unsignedInteger('publisher_id')->default(1);    // _blank_
            $table->unsignedInteger('plat_id')->default(1);         // _blank_
            $table->unsignedInteger('inst_id');                     // Required
            $table->string('yearmon', 7);                           // Required
            $table->unsignedInteger('datatype_id')->default(8);     // Unknown
            $table->unsignedInteger('sectiontype_id')->default(1);  // _blank_
            $table->string('yop', 9)->default('');
            $table->unsignedInteger('accesstype_id')->default(1);   // Controlled
            $table->unsignedInteger('accessmethod_id')->default(1); // Regular
            $table->unsignedInteger('total_item_investigations')->default(0);
            $table->unsignedInteger('total_item_requests')->default(0);
            $table->unsignedInteger('unique_item_investigations')->default(0);
            $table->unsignedInteger('unique_item_requests')->default(0);
            $table->unsignedInteger('unique_title_investigations')->default(0);
            $table->unsignedInteger('unique_title_requests')->default(0);
            $table->unsignedInteger('limit_exceeded')->default(0);
            $table->unsignedInteger('no_license')->default(0);

            $table->index(['yearmon']);
            $table->foreign('title_id')->references('id')->on($global_db . '.titles');
            $table->foreign('prov_id')->references('id')->on($global_db . '.global_providers');
            $table->foreign('publisher_id')->references('id')->on($global_db . '.publishers');
            $table->foreign('plat_id')->references('id')->on($global_db . '.platforms');
            $table->foreign('inst_id')->references('id')->on('institutions');
            $table->foreign('datatype_id')->references('id')->on($global_db . '.datatypes');
            $table->foreign('sectiontype_id')->references('id')->on($global_db . '.sectiontypes');
            $table->foreign('accesstype_id')->references('id')->on($global_db . '.accesstypes');
            $table->foreign('accessmethod_id')->references('id')->on($global_db . '.accessmethods');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tr_report_data');
    }
}
