<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTempIrTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('temp_ir', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->bigInteger('item_id')->unsigned();
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('publisher_id')->nullable();
            $table->unsignedInteger('plat_id')->nullable();
            $table->unsignedInteger('inst_id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('datatype_id')->nullable();
            $table->unsignedInteger('accesstype_id')->nullable();
            $table->unsignedInteger('accessmethod_id')->default(1);
            $table->unsignedInteger('total_item_requests');
            $table->unsignedInteger('unique_item_requests');
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on($global_db . '.items');
            $table->foreign('prov_id')->references('id')->on('providers');
            $table->foreign('plat_id')->references('id')->on($global_db . '.platforms');
            $table->foreign('inst_id')->references('id')->on('institutions');
            $table->foreign('accessmethod_id')->references('id')->on($global_db . '.accessmethods');
            $table->foreign('accesstype_id')->references('id')->on($global_db . '.accesstypes');
            $table->foreign('datatype_id')->references('id')->on($global_db . '.datatypes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('temp_ir');
    }
}
