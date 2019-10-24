<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTempPr1Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('temp_pr1', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();
            
            $table->unsignedInteger('plat_id');
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('inst_id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('search_reg');
            $table->unsignedInteger('search_fed');
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('views');
            $table->timestamps();
            $table->foreign('plat_id')->references('id')->on($global_db . '.platforms');
            $table->foreign('prov_id')->references('id')->on('providers');
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
        Schema::dropIfExists('temp_pr1');
    }
}
