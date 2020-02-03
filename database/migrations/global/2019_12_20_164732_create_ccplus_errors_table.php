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
            $table->unsignedInteger('id')->unique();
            $table->string('message',60);
            $table->enum('severity', array('Info', 'Debug', 'Warning', 'Error', 'Fatal'))->default('Warning');
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
        Schema::dropIfExists('ccplus_errors');
    }
}
