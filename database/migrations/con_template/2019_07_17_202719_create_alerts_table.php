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

            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('alertsettings_id')->nullable();
            $table->unsignedInteger('harvest_id')->nullable();
            $table->unsignedInteger('modified_by')->default('1');
            $table->enum('status', array('Active','Silent','Delete'))->default('Active');
            $table->timestamps();
            $table->foreign('alertsettings_id')->references('id')->on('alertsettings')->onDelete('cascade');
            $table->foreign('harvest_id')->references('id')->on('harvestlogs')->onDelete('cascade');
            $table->foreign('modified_by')->references('id')->on('users');

            $table->foreign('prov_id')->references('id')->on($global_db . '.global_providers')->onDelete('cascade');
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
