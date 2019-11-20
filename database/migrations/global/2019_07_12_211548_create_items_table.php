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
            $table->string('Title')->nullable();
            $table->string('authors')->nullable();
            $table->string('pub_date', 10)->nullable();
            $table->string('article_version', 10)->nullable();
            $table->string('DOI', 128)->nullable();
            $table->string('PropID', 128)->nullable();
            $table->string('YOP', 9)->nullable();
            $table->string('ISBN', 9)->nullable();
            $table->string('ISSN', 9)->nullable();
            $table->string('eISSN', 9)->nullable();
            $table->string('URI', 128)->nullable();
            $table->string('parent_title')->nullable();
            $table->string('parent_authors')->nullable();
            $table->string('parent_pub_date', 10)->nullable();
            $table->string('parent_article_version')->nullable();
            $table->string('parent_data_type', 10)->nullable();
            $table->string('parent_DOI', 128)->nullable();
            $table->string('parent_PropID', 128)->nullable();
            $table->string('parent_ISBN', 9)->nullable();
            $table->string('parent_ISSN', 9)->nullable();
            $table->string('parent_eISSN', 9)->nullable();
            $table->string('parent_URI', 128)->nullable();
            $table->string('component_title')->nullable();
            $table->string('component_authors')->nullable();
            $table->string('component_pub_date', 10)->nullable();
            $table->string('component_data_type', 10)->nullable();
            $table->string('component_DOI', 128)->nullable();
            $table->string('component_PropID', 128)->nullable();
            $table->string('component_ISBN', 9)->nullable();
            $table->string('component_ISSN', 9)->nullable();
            $table->string('component_eISSN', 9)->nullable();
            $table->string('component_URI', 128)->nullable();
            $table->timestamps();
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
