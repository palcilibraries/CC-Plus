<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInstitutionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->Increments('id');
            $table->string('name')->unique();
            $table->boolean('is_active')->default(1);
            // internal_id can be null, but IF SET,  must be unique - enforced in controller
            $table->string('internal_id')->nullable();
            $table->string('notes')->nullable();
            $table->string('sushiIPRange')->nullable();
            $table->string('shibURL')->nullable();
            $table->unsignedInteger('fte')->nullable();
            $table->unsignedInteger('type_id')->default(1);
            $table->timestamps();

            $table->foreign('type_id')->references('id')->on('institutiontypes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('institutions');
    }
}
