<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProviderReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('provider_report', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->increments('id');
            $table->integer('provider_id')->unsigned();
            $table->integer('report_id')->unsigned();
            $table->timestamps();
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->foreign('report_id')->references('id')->on($global_db . '.reports');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('provider_report');
    }
}
