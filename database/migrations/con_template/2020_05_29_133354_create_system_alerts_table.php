<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemAlertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->bigIncrements('id');
            $table->string('text')->nullable();
            $table->unsignedInteger('severity_id')->default(0);     // default is Info
            $table->boolean('is_active')->default(1);
            $table->timestamps();

            $table->foreign('severity_id')->references('id')->on($global_db . '.severities');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('system_alerts');
    }
}
