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
            $table->unsignedInteger('sushisettings_id');
            $table->unsignedInteger('report_id');
            $table->string('yearmon', 7);
            $table->string('process_step');
            $table->unsignedInteger('retry_count');
            $table->string('detail');
            $table->timestamps();

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
        Schema::dropIfExists('failedingests');
    }
}
