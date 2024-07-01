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

            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            $table->string('name');
            $table->boolean('is_active')->default(1);
            $table->boolean('allow_inst_specific')->default(0);
            $table->unsignedInteger('global_id')->nullable();
            $table->unsignedInteger('inst_id')->default(1); // inst_id=1 is consorta-wide
            $table->unsignedInteger('day_of_month')->default(15);
            $table->timestamps();

            $table->foreign('inst_id')->references('id')->on('institutions');
            $table->foreign('global_id')->references('id')->on($global_db . '.global_providers');
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
