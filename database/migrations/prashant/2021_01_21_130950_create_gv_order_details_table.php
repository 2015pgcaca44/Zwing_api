<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGvOrderDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gv_order_details', function (Blueprint $table) {
            $table->bigIncrements('gv_od_id');
            $table->integer('gv_order_id');
            $table->integer('store_id');
            $table->integer('v_id');
            $table->integer('vu_id');
            $table->integer('customer_id');
            $table->integer('gv_group_id');
            $table->integer('gv_id');
            $table->decimal('sale_value',40);
            $table->decimal('gift_value',40);
            $table->decimal('subtotal');
            $table->decimal('total');
            $table->decimal('tax_amount')->nullable();
            $table->string('tax_details')->nullable();
            $table->string('voucher_code');
            $table->string('voucher_sequence');
            $table->integer('session_id')->nullable();
            $table->bigInteger('mobile')->nullable();
            $table->integer('gift_customer_id')->nullable();
            $table->date('date');
            $table->time('time');
            $table->integer('month');
            $table->integer('year');
            $table->enum('status',['process','success','error'])->nullable();
            $table->enum('transaction_type',['sales','return'])->nullable();
            $table->enum('channel_id',['1','2','3'])->default('1');
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
        Schema::dropIfExists('gv_order_details');
    }
}
