<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBr2ReportDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('br2_report_data', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();
            
            $table->bigInteger('book_id')->unsigned();
            $table->unsignedInteger('prov_id');
            $table->unsignedInteger('plat_id');
            $table->unsignedInteger('inst_id');
            $table->string('yearmon', 7);
            $table->unsignedInteger('sections');
            $table->timestamps();
            $table->foreign('book_id')->references('id')->on($global_db . '.books');
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
        Schema::dropIfExists('br2_report_data');
    }
}
