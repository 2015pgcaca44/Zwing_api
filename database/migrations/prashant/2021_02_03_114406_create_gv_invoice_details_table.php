<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGvInvoiceDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gv_invoice_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('v_id');
            $table->integer('vu_id');
            $table->integer('store_id');
            $table->integer('gv_order_id');
            $table->integer('customer_id');
            $table->decimal('subtotal');
            $table->decimal('total');
            $table->decimal('tax_amount')->nullable();
            $table->decimal('tax_details')->nullable();
            $table->decimal('sale_value',40);
            $table->decimal('gift_value',40);
            $table->integer('gv_group_id');
            $table->integer('gv_id');
            $table->string('voucher_code');
            $table->string('voucher_sequence');
            $table->bigInteger('mobile')->nullable();
            $table->integer('gift_customer_id')->nullable();
            $table->enum('transaction_type',['sales','return']);
            $table->enum('status',['process','success','error'])->nullable();
            $table->enum('channel_id',['1','2','3'])->default('1');
            $table->text('temp_data')->nullable();
            $table->integer('session_id')->nullable();
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
        Schema::dropIfExists('gv_invoice_details');
    }
}
