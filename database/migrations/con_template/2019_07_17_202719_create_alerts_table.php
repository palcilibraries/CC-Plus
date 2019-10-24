<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAlertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->Increments('id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('alertsettings_id');
            $table->unsignedInteger('failed_id');
            $table->unsignedInteger('modified_by');
            $table->enum('status', array('Active','Silent','Delete'))->nullable();
            $table->timestamps();
            $table->foreign('prov_id')->references('id')->on('providers');
            $table->foreign('alertsettings_id')->references('id')->on('alertsettings');
            $table->foreign('failed_id')->references('id')->on('failedingests');
            $table->foreign('modified_by')->references('id')->on('users');
            ;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alerts');
    }
}
