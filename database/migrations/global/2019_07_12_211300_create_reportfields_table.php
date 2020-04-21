qry<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReportFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reportfields', function (Blueprint $table) {
            $table->Increments('id');
            $table->unsignedInteger('report_id');
            $table->string('legend');
            $table->string('joins')->nullable();
            $table->string('qry')->nullable();
            $table->string('qry_as')->nullable();
            $table->string('group_it')->default(0);
            $table->unsignedInteger('report_filter_id')->nullable();
            $table->boolean('active')->default(0);
            $table->boolean('reload')->default(1);
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('reports')->onDelete('cascade');
            $table->foreign('report_filter_id')->references('id')->on('reportfilters');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reportfields');
    }
}
