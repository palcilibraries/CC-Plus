<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFailedHarvestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('failedharvests', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            $table->unsignedInteger('harvest_id');
            $table->unsignedInteger('error_id');
            $table->string('process_step')->nullable();
            $table->string('detail')->nullable();
            $table->timestamps();

            $table->foreign('harvest_id')->references('id')->on('harvestlogs');
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
        Schema::dropIfExists('failedharvests');
    }
}
