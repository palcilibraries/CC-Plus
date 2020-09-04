<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReportFiltersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reportfilters', function (Blueprint $table) {
            $table->Increments('id');
            $table->string('attrib');
            $table->string('model')->nullable();
            $table->string('table_name')->nullable();
            $table->string('report_column');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reportfilters');
    }
}
