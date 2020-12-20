<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCCplusErrorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ccplus_errors', function (Blueprint $table) {
            $table->integer('id')->unsigned()->unique();
            $table->string('message',60);
            $table->unsignedInteger('severity_id')->default(0);     // default is Info
            $table->string('explanation')->default('');
            $table->string('suggestion')->default('');
            $table->timestamps();

            $table->foreign('severity_id')->references('id')->on('severities');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ccplus_errors');
    }
}
