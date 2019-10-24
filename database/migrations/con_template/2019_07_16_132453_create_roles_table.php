<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // don't want id to auto-increment... new roles require an id assignment
        Schema::create('roles', function (Blueprint $table) {
            // $table->unsignedInteger('id');
            $table->integer('id')->unsigned();
            $table->primary('id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('roles');
    }
}
