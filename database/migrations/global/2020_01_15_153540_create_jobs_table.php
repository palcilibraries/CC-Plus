<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('ingest_id');
            $table->unsignedInteger('consortium_id');
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('replace_data')->default(0);
            $table->timestamps();

            $table->unique(['ingest_id', 'consortium_id']);
            $table->foreign('consortium_id')->references('id')->on('consortia');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jobs');
    }
}
