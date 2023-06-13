<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGvOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gv_order', function (Blueprint $table) {
            $table->bigIncrements('gv_order_id');
            $table->string('gv_order_doc_no');
            $table->enum('transaction_type',['sales','return']);
            $table->enum('comm_trans',['B2C','B2B'])->default('B2C');
            $table->integer('v_id');
            $table->integer('vu_id');
            $table->integer('customer_id');
            $table->integer('store_id');
            $table->enum('trans_from',['ANDROID','IOS','ANDROID_KIOSK','ANDROID_VENDOR']);
            $table->integer('voucher_qty');
            $table->decimal('subtotal');
            $table->decimal('total');
            $table->decimal('tax_amount')->nullable();
            $table->string('discount',40)->nullable();
            $table->string('round_off',40)->nullable();
            $table->enum('payment_type',['full','partial'])->nullable();
            $table->enum('payment_via',['RAZOR_PAY','EZETAP','CASH','VOUCHER','EZSWYPE'])->nullable();
            $table->enum('status',['process','success','error']);
            $table->date('date');
            $table->time('time');
            $table->integer('month');
            $table->integer('financial_year');
            $table->integer('session_id')->nullable();
            $table->string('customer_gstin',50)->nullable();
            $table->integer('customer_gst_state_id')->nullable();
            $table->string('store_gstin',50)->nullable();
            $table->integer('store_state_id')->nullable();
            $table->string('return_reason_code',50)->nullable();
            $table->enum('is_void',['0','1'])->nullable();
            $table->string('void_by',50)->nullable();
            $table->string('void_reason_code',50)->nullable();
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
        Schema::dropIfExists('gv_order');
    }
}
