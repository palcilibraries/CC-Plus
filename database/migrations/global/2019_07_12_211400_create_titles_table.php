<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTitlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('titles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('Title', 256);
            $table->string('type', 1);    // 'J', 'B', or 'I'
            $table->string('ISBN')->nullable();
            $table->string('ISSN')->nullable();
            $table->string('eISSN')->nullable();
            $table->string('DOI', 256)->nullable();
            $table->string('PropID', 256)->nullable();
            $table->string('URI', 256)->nullable();
            $table->string('pub_date', 10)->nullable();
            $table->string('article_version', 10)->nullable();
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
        Schema::dropIfExists('titles');
    }
}
