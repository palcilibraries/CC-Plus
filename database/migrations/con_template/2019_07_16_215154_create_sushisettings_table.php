<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSushiSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sushisettings', function (Blueprint $table) {
            $table->Increments('id');
            $table->unsignedInteger('inst_id');
            $table->unsignedInteger('prov_id');
            $table->text('customer_id')->nullable();
            $table->text('requestor_id')->nullable();
            $table->text('API_key')->nullable();
            $table->timestamps();

            $table->foreign('inst_id')->references('id')->on('institutions');
            $table->foreign('prov_id')->references('id')->on('providers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sushisettings');
    }
}
