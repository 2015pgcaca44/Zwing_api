<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentModeClientMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_mode_client_mappings', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('paymemt_mode_id')->unsigned();
            $table->integer('third_party_client_id')->unsigned();
            $table->string('mop_code');
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
        Schema::dropIfExists('payment_mode_client_mappings');
    }
}
