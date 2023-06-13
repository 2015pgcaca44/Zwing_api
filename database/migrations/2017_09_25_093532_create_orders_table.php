<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('od_id');
            $table->string('order_id')->unique();
            $table->integer('o_id')->nullbable();
            $table->integer('v_id')->nullbable();
            $table->integer('store_id')->nullbable();
            $table->integer('user_id')->nullbable();
            $table->integer('amount')->nullbable();
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
        Schema::dropIfExists('orders');
    }
}
