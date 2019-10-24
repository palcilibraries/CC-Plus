<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTempJr5Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('temp_jr5', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();
            
            $table->bigInteger('jrnl_id')->unsigned();
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('plat_id');
            $table->unsignedInteger('inst_id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('YOP_InPress');
            $table->unsignedInteger('YOP_2018');
            $table->unsignedInteger('YOP_2017');
            $table->unsignedInteger('YOP_2016');
            $table->unsignedInteger('YOP_2015');
            $table->unsignedInteger('YOP_2014');
            $table->unsignedInteger('YOP_2013');
            $table->unsignedInteger('YOP_2012');
            $table->unsignedInteger('YOP_2011');
            $table->unsignedInteger('YOP_2010');
            $table->unsignedInteger('YOP_2009');
            $table->unsignedInteger('YOP_2008');
            $table->unsignedInteger('YOP_2007');
            $table->unsignedInteger('YOP_2006');
            $table->unsignedInteger('YOP_2005');
            $table->unsignedInteger('YOP_2004');
            $table->unsignedInteger('YOP_2003');
            $table->unsignedInteger('YOP_2002');
            $table->unsignedInteger('YOP_2001');
            $table->unsignedInteger('YOP_2000');
            $table->unsignedInteger('YOP_Pre-2000');
            $table->unsignedInteger('YOP_Unknown');
            $table->timestamps();
            $table->foreign('jrnl_id')->references('id')->on($global_db . '.journals');
            $table->foreign('prov_id')->references('id')->on('providers');
            $table->foreign('plat_id')->references('id')->on($global_db . '.platforms');
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
        Schema::dropIfExists('temp_jr5');
    }
}
