<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSavedReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('savedreports', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            $table->string('title');
            $table->integer('user_id')->unsigned();
            $table->string('date_range', 12)->default('Custom');
            $table->string('ym_from', 7)->nullable();
            $table->string('ym_to', 7)->nullable();
            $table->unsignedInteger('master_id');
            $table->unsignedInteger('report_id');
            $table->string('inherited_fields')->nullable();
            $table->string('filters')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('master_id')->references('id')->on($global_db . '.reports');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('savedreports');
    }
}
