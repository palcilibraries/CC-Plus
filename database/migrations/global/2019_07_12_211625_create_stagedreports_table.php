<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStagedreportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stagedreports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('XML_File');
            $table->string('CSV_File');
            $table->unsignedInteger('report_id');
            $table->string('yearmon', 7);
            $table->string('con_key', 10);
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('inst_id');
            $table->timestamps();
      
            $table->foreign('report_id')->references('id')->on('reports');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stagedreports');
    }
}
