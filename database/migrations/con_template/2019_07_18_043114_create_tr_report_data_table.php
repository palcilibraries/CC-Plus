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

            $table->bigInteger('jrnl_id')->unsigned();
            $table->bigInteger('book_id')->unsigned();
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('plat_id');
            $table->unsignedInteger('inst_id');
            $table->string('yearmon', 7);
            $table->string('DOI', 128);
            $table->string('PropID', 128);
            $table->string('URI', 128);
            $table->string('data_type', 40);
            $table->string('section_type', 40)->nullable();
            $table->string('YOP', 9);
            $table->string('access_type', 40)->nullable();
            $table->string('access_method', 10)->default('Regular');
            $table->unsignedInteger('total_item_investigations');
            $table->unsignedInteger('total_item_requests');
            $table->unsignedInteger('unique_item_investigations');
            $table->unsignedInteger('unique_item_requests');
            $table->unsignedInteger('unique_title_investigations');
            $table->unsignedInteger('unique_title_requests');
            $table->unsignedInteger('limit_exceeded');
            $table->unsignedInteger('no_license');
            $table->timestamps();

            $table->foreign('jrnl_id')->references('id')->on($global_db . '.journals');
            $table->foreign('book_id')->references('id')->on($global_db . '.books');
            $table->foreign('prov_id')->references('id')->on('providers');
            $table->foreign('plat_id')->references('id')->on($global_db . '.platforms');
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
        Schema::dropIfExists('tr_report_data');
    }
}
