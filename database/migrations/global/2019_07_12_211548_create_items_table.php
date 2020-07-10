<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('title_id')->unsigned();
            $table->string('authors', 256)->nullable();
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->unsignedInteger('parent_datatype_id')->nullable();
            $table->bigInteger('component_id')->unsigned()->nullable();
            $table->unsignedInteger('component_datatype_id')->nullable();
            $table->timestamps();

            $table->foreign('title_id')->references('id')->on('titles');
            $table->foreign('parent_datatype_id')->references('id')->on('datatypes');
            $table->foreign('component_datatype_id')->references('id')->on('datatypes');

            $table->index('title_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('items');
    }
}
