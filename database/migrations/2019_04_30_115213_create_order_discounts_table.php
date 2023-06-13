<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_discounts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('v_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('order_id')->nullable();
            $table->string('name')->nullable();
            $table->enum('type', ['CO','MD','LP'])->nullable()->comment = 'CO - Coupon, MD - Manual Discount, LP - Loyalty';
            $table->enum('level', ['I','M'])->nullable()->comment = 'I - Item Level, M - Memo Level';
            $table->enum('basis', ['P','A'])->nullable()->comment = 'P - Percentage, A - Amount';
            $table->double('factor', 12, 2)->nullable();
            $table->double('amount', 12, 2)->nullable();
            $table->text('item_list')->nullable();
            $table->text('response')->nullable();
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
        Schema::dropIfExists('order_discounts');
    }
}
