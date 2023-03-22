<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlobalProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('global_providers', function (Blueprint $table) {
          $table->Increments('id');
          $table->string('name');
          $table->index('name');
          $table->boolean('is_active')->default(1);
          $table->json('master_reports')->default(1);
          $table->json('connectors')->default(1);
          $table->string('server_url_r5')->nullable();
          $table->string('extra_pattern')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('global_providers');
    }
}
