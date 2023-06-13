<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGvCartDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gv_cart_details', function (Blueprint $table) {
            $table->bigIncrements('gv_cart_id');
            $table->integer('store_id');
            $table->integer('v_id');
            $table->integer('vu_id');
            $table->integer('customer_id');
            $table->integer('gv_group_id');
            $table->integer('gv_id');
            $table->string('sale_value',40);
            $table->string('gift_value',40);
            $table->string('voucher_code');
            $table->string('voucher_sequence');
            $table->decimal('subtotal');
            $table->decimal('total');
            $table->decimal('tax_amount')->nullable();
            $table->text('tax_details')->nullable();
            $table->integer('session_id')->nullable();
            $table->bigInteger('mobile')->nullable();
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
        Schema::dropIfExists('gv_cart_details');
    }
}
