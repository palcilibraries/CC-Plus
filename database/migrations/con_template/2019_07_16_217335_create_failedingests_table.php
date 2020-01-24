<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFailedIngestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('failedingests', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            $table->unsignedInteger('ingest_id');
            $table->unsignedInteger('error_id');
            $table->string('yearmon', 7);
            $table->string('process_step')->nullable();
            $table->string('detail')->nullable();
            $table->timestamps();

            $table->foreign('ingest_id')->references('id')->on('ingestlogs');
            $table->foreign('error_id')->references('id')->on($global_db . '.ccplus_errors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('failedingests');
    }
}
