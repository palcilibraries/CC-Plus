<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateJournalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('Title');
            $table->string('ISSN', 9);
            $table->string('eISSN', 9);
            $table->string('URI', 128);
            $table->string('DOI', 128);
            $table->string('PropID', 128);
            $table->string('YOP', 9);
            $table->timestamps();

            // $table->foreign('owner_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journals');
    }
}
