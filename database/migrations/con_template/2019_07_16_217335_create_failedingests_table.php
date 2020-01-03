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
            $table->unsignedInteger('error_id');
            $table->string('yearmon', 7);
            $table->string('process_step')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('detail')->nullable();
            $table->timestamps();

            $table->foreign('sushisettings_id')->references('id')->on('sushisettings');
            $table->foreign('report_id')->references('id')->on($global_db . '.reports');
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
