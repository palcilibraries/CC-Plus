<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHarvestLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('harvestlogs', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            // Status should be: 'Success', 'Fail', 'New', 'Queued', 'Active', 'Harvested', 'Pending', 'Stopped', or 'ReQueued'
            $table->string('status', 10);
            $table->unsignedInteger('sushisettings_id');
            $table->unsignedInteger('report_id');
            $table->string('yearmon', 7);
            $table->string('source', 1)->nullable();    // source = "C" for consortium, "I" for institution
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('error_id')->nullable();
            $table->string('rawfile')->nullable();
            $table->timestamps();

            $table->unique(['sushisettings_id', 'report_id', 'yearmon']);
            $table->foreign('sushisettings_id')->references('id')->on('sushisettings')->onDelete('cascade');
            $table->foreign('report_id')->references('id')->on($global_db . '.reports')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('harvestlogs');
    }
}
