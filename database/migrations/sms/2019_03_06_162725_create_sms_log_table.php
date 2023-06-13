<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('sms_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('v_id');
            $table->integer('store_id');
            $table->text('request');
            $table->text('response');
            $table->enum('for', ['OTP','VOUCHER','BILL_RECEIPT'])->nullable();
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
        Schema::dropIfExists('sms_logs');
    }
}
