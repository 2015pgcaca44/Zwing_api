<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGvPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gv_payments', function (Blueprint $table) {
            $table->bigIncrements('gv_payments_id');
            $table->integer('v_id');
            $table->integer('vu_id');
            $table->integer('customer_id');
            $table->integer('store_id');
            $table->string('gv_order_doc_no');
            $table->integer('gv_order_id');
            $table->string('invoice_id')->nullable();
            $table->integer('session_id')->nullable();
            $table->string('terminal_id')->nullable();
            $table->decimal('amount',40);
            $table->enum('method',['card','cash','upi'])->nullable();
            $table->string('cash_collected',40)->nullable();
            $table->string('cash_return',40)->nullable();
            $table->string('payment_invoice_id')->nullable();
            $table->text('error_description')->nullable();
            $table->enum('status',['process','success','error']);
            $table->enum('payment_type',['full','partial']);
            $table->enum('payment_gateway_type',['RAZOR_PAY','EZETAP','CASH','VOUCHER','EZSWYPE','EZSWYPE_INTERNAL','PINELAB_INTERNAL','LOYALTY','PAYTM','PAYTM_OFFLINE','CARD_OFFLINE','GOOGLE_TEZ_OFFLINE','GOOGLE_TEZ','MSWIPE_INTERNAL']);
            $table->string('payment_gateway_device_type')->nullable();
            $table->text('gateway_response')->nullable();
            $table->string('ref_txn_id',40)->nullable();
            $table->enum('trans_type',['sales','return'])->nullable();
            $table->date('date');
            $table->time('time');
            $table->integer('month');
            $table->integer('year');
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
        Schema::dropIfExists('gv_payments');
    }
}
