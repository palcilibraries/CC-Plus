<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAlertSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('alertsettings', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            $table->unsignedInteger('inst_id');
            $table->boolean('is_active');
            $table->unsignedInteger('field_id');
            $table->unsignedInteger('variance');
            $table->unsignedInteger('timespan');
            $table->timestamps();

            $table->foreign('inst_id')->references('id')->on('institutions')->onDelete('cascade');
            $table->foreign('field_id')->references('id')->on($global_db . '.reportfields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('alertsettings');
    }
}
