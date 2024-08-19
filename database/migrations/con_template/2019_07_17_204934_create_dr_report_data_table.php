<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDrReportDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dr_report_data', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->unsignedInteger('db_id');
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('publisher_id')->nullable();
            $table->unsignedInteger('plat_id')->nullable();
            $table->unsignedInteger('inst_id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('datatype_id')->nullable();
            $table->unsignedInteger('accessmethod_id')->nullable();
            $table->unsignedInteger('searches_automated');
            $table->unsignedInteger('searches_federated');
            $table->unsignedInteger('searches_regular');
            $table->unsignedInteger('total_item_investigations');
            $table->unsignedInteger('total_item_requests');
            $table->unsignedInteger('unique_item_investigations');
            $table->unsignedInteger('unique_item_requests');
            $table->unsignedInteger('unique_title_investigations');
            $table->unsignedInteger('unique_title_requests');
            $table->unsignedInteger('limit_exceeded');
            $table->unsignedInteger('no_license');

            $table->index('yearmon');
            $table->foreign('db_id')->references('id')->on($global_db . '.databases');
            $table->foreign('prov_id')->references('id')->on($global_db . '.global_providers');
            $table->foreign('publisher_id')->references('id')->on($global_db . '.publishers');
            $table->foreign('plat_id')->references('id')->on($global_db . '.platforms');
            $table->foreign('inst_id')->references('id')->on('institutions');
            $table->foreign('datatype_id')->references('id')->on($global_db . '.datatypes');
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
        Schema::dropIfExists('dr_report_data');
    }
}
