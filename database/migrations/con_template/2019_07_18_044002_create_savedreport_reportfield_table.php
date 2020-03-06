<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSavedReportReportfieldTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('savedreport_reportfield', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            $table->integer('report_id')->unsigned();
            $table->integer('reportfield_id')->unsigned();
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('savedreports')->onDelete('cascade');
            $table->foreign('reportfield_id')->references('id')->on($global_db . '.reportfields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('savedreport_reportfield');
    }
}
