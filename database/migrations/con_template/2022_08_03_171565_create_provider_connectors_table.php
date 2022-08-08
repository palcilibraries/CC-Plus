<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProviderConnectorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('provider_connectors', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->increments('id');
            $table->integer('provider_id')->unsigned();
            $table->integer('connection_field_id')->unsigned();
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->foreign('connection_field_id')->references('id')->on($global_db . '.connection_fields');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('provider_connectors');
    }
}
