<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateJr1ReportDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jr1_report_data', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();
            
            $table->bigInteger('jrnl_id')->unsigned();
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('plat_id');
            $table->unsignedInteger('inst_id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('RP_HTML');
            $table->unsignedInteger('RP_PDF');
            $table->unsignedInteger('RP_TTL');
            $table->timestamps();
            $table->foreign('jrnl_id')->references('id')->on($global_db . '.journals');
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
        Schema::dropIfExists('jr1_report_data');
    }
}
