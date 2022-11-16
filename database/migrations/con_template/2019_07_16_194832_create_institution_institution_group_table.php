<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInstitutionInstitutiongroupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('institution_institution_group', function (Blueprint $table) {
              $table->Increments('id');
              $table->integer('institution_id')->unsigned();
              $table->integer('institution_group_id')->unsigned();
              $table->timestamps();

              $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
              $table->foreign('institution_group_id')->references('id')->on('institutiongroups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('institution_institution_group');
    }
}
