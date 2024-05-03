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
          $table->string('registry_id')->nullable();
          $table->string('name');
          $table->string('abbrev')->nullable();
          $table->index('name');
          $table->string('content_provider')->nullable();
          $table->boolean('is_active')->default(1);
          $table->boolean('refreshable')->default(1);
          $table->json('master_reports')->default(1);
          $table->json('connectors')->default(1);
          $table->string('server_url_r5')->nullable();
          $table->string('notifications_url')->nullable();
          $table->string('platform_parm')->nullable();
          $table->timestamps();
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
