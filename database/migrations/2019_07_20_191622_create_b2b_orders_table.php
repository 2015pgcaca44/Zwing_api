<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateB2bOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('b2b_orders', function (Blueprint $table) {
            $table->increments('od_id');
            $table->string('order_id')->unique();
            $table->string('custom_order_id')->nullable();
            $table->string('ref_order_id');
            $table->enum('transaction_type',['sales','return']);
            $table->enum('transaction_sub_type',['sales','return','hold','un_hold','lay_by','on_account_sale','lay_by_processed']);
            $table->integer('o_id')->nullable();
            $table->integer('v_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('address_id')->default('0');
            $table->integer('partner_offer_id')->default('0');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount',12,2);
            $table->string('employee_id')->nullable();
            $table->decimal('employee_discount',12,2)->default('0.00');
            $table->string('employee_available_discount')->nullable();
            $table->decimal('bill_buster_discount',12,2)->nullable();
            $table->text('bill_buster_data');
            $table->string('manual_discount');
            $table->integer('md_added_by');
            $table->decimal('tax',12,2);
            $table->decimal('total',12,2);
           $table->enum('status', ['process', 'success', 'error'])->nullable();
           $table->enum('payment_type',['full','partial'])->nullable();
           $table->enum('payment_via',['RAZOR_PAY','EZETAP','CASH','VOUCHER','EZSWYPE','EZSWYPE_INTERNAL'])->nullable();
           $table->enum('is_invoice',['0','1'])->default('0');
           $table->text('error_description');
           $table->enum('trans_from',['ANDROID','IOS','ANDROID_KIOSK','ANDROID_VENDOR'])->default('ANDROID');
           $table->integer('vu_id')->default('0');
           $table->enum('verify_status',['0','1'])->default('0');
           $table->integer('verified_by')->default('0');
           $table->enum('verify_status_guard',['0','1']);
           $table->integer('verified_by_guard')->deault('0');
           $table->string('invoice_name')->nullable();
           $table->integer('transaction_no');
           $table->enum('return_by',['cash','voucher']);
           $table->string('return_code')->nullable();
           $table->text('remark');
           $table->date('date')->nullable();
           $table->time('time')->nullable();
           $table->integer('month')->nullable();
           $table->integer('year')->nullable();
           $table->decimal('lay_by_total',12,2)->nullable();
           $table->text('lay_by_remark');
           $table->dateTime('deleted_at')->nullable();
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
        Schema::dropIfExists('b2b_orders');
    }
}
