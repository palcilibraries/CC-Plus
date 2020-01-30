<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIngestLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ingestlogs', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            // Status should be: 'Success', 'Fail', 'New', 'Queued', 'Active', 'Pending', 'Stopped', or 'Retrying'
            $table->string('status', 8);
            $table->unsignedInteger('sushisettings_id');
            $table->unsignedInteger('report_id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique(['sushisettings_id', 'report_id', 'yearmon']);
            $table->foreign('sushisettings_id')->references('id')->on('sushisettings');
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
        Schema::dropIfExists('ingestlogs');
    }
}
