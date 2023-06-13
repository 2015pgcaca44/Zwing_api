<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiscrepencyMemoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discrepency_memos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->integer('v_id');
            $table->integer('store_id');
            $table->integer('product_id');
            $table->string('barcode');
            $table->integer('qty');
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
        Schema::dropIfExists('discrepency_memos');
    }
}
