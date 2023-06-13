<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('payment_id');
            $table->integer('store_id')->nullable();
            $table->integer('v_id')->nullable();
            $table->integer('t_order_id')->nullable();
            $table->string('order_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('pay_id')->nullable();
            $table->integer('amount')->nullable();
            $table->string('method')->nullable();
            $table->string('invoice_id')->nullable();
            $table->string('bank')->nullable();
            $table->string('wallet')->nullable();
            $table->string('vpa')->nullable();
            $table->longtext('error_description')->nullable();
            $table->enum('status', ['process', 'success', 'error'])->nullable();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
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
        Schema::dropIfExists('payments');
    }
}
