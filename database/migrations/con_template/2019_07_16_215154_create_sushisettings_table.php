<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSushiSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sushisettings', function (Blueprint $table) {
            $table->Increments('id');
            $table->unsignedInteger('inst_id');
            $table->unsignedInteger('prov_id');
            $table->text('customer_id')->nullable();
            $table->text('requestor_id')->nullable();
            $table->text('api_key')->nullable();
            $table->text('extra_args')->nullable();
            $table->text('support_email')->nullable();
            $table->string('last_harvest', 7)->nullable();   // YYYY-MM , last successful
            // Status should be: 'Enabled', 'Disabled', 'Suspended', or 'Incomplete'
            $table->string('status', 10);
            $table->timestamps();

            $table->foreign('inst_id')->references('id')->on('institutions')->onDelete('cascade');
            $table->foreign('prov_id')->references('id')->on('providers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sushisettings');
    }
}
