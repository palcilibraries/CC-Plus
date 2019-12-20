<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSushierrorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sushierrors', function (Blueprint $table) {
            $table->unsignedInteger('id')->unique();
            $table->string('message');
            $table->enum('severity', array('Info', 'Debug', 'Warning', 'Error', 'Fatal'))->default('Warning');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sushierrors');
    }
}
