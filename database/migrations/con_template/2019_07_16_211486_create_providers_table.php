<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('providers', function (Blueprint $table) {

            $table->Increments('id');
            $table->string('name')->unique();
            $table->boolean('is_active')->default(1);
            $table->unsignedInteger('inst_id')->default(1); // inst_id=1 is consorta-wide
            $table->string('server_url_r5')->nullable();
            $table->unsignedInteger('day_of_month')->default(15);
            $table->timestamps();

            $table->foreign('inst_id')->references('id')->on('institutions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('providers');
    }
}
