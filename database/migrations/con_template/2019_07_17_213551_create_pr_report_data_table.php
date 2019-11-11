<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePrReportDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pr_report_data', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->unsignedInteger('plat_id');
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('inst_id');
            $table->string('yearmon', 7);
            $table->string('data_type', 128);
            $table->string('access_method', 10)->default('Regular');
            $table->unsignedInteger('searches_platform');
            $table->unsignedInteger('total_item_investigations');
            $table->unsignedInteger('total_item_requests');
            $table->unsignedInteger('unique_item_investigations');
            $table->unsignedInteger('unique_item_requests');
            $table->unsignedInteger('unique_title_investigations');
            $table->unsignedInteger('unique_title_requests');
            $table->timestamps();

            $table->foreign('plat_id')->references('id')->on($global_db . '.platforms');
            $table->foreign('prov_id')->references('id')->on('providers');
            $table->foreign('inst_id')->references('id')->on('institutions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pr_report_data');
    }
}
