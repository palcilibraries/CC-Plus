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
            $table->string('Name',256)->nullable();
            $table->string('authors',256)->nullable();
            $table->string('pub_date', 10)->nullable();
            $table->string('article_version', 10)->nullable();
            $table->string('DOI', 256)->nullable();
            $table->string('PropID', 256)->nullable();
            $table->string('ISBN', 9)->nullable();
            $table->string('ISSN', 9)->nullable();
            $table->string('eISSN', 9)->nullable();
            $table->string('URI', 256)->nullable();
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->unsignedInteger('parent_datatype_id')->nullable();
            $table->bigInteger('component_id')->unsigned()->nullable();
            $table->unsignedInteger('component_datatype_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_datatype_id')->references('id')->on('datatypes');
            $table->foreign('component_datatype_id')->references('id')->on('datatypes');
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
