<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGvInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gv_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('v_id');
            $table->integer('vu_id');
            $table->integer('store_id');
            $table->string('invoice_id');
            $table->string('custom_order_id')->nullable();
            $table->string('ref_order_id');
            $table->integer('customer_id');
            $table->string('customer_first_name',100)->nullable();
            $table->string('customer_last_name',100)->nullable();
            $table->string('customer_number',15);
            $table->string('customer_email',20)->nullable();
            $table->string('customer_address',50)->nullable();
            $table->string('customer_pincode',50)->nullable();
            $table->string('customer_gender',10)->nullable();
            $table->string('customer_phone_code',50)->nullable();
            $table->date('customer_dob',50)->nullable();
            $table->enum('transaction_type',['sales','return']);
            $table->enum('comm_trans',['B2C','B2B'])->default('B2C');
            $table->string('customer_gstin',50)->nullable();
            $table->integer('customer_gst_state_id')->nullable();
            $table->string('store_gstin',50)->nullable();
            $table->integer('store_state_id')->nullable();
            $table->string('store_short_code',50)->nullable();
            $table->integer('voucher_qty');
            $table->decimal('subtotal');
            $table->decimal('total');
            $table->decimal('tax_amount')->nullable();
            $table->decimal('tax_details')->nullable();
            $table->date('date');
            $table->time('time');
            $table->integer('month');
            $table->integer('year');
            $table->integer('financial_year');
            $table->enum('trans_from',['ANDROID','IOS','ANDROID_KIOSK','ANDROID_VENDOR']);
            $table->string('invoice_sequence',50)->nullable();
            $table->string('terminal_name',100)->nullable();
            $table->string('terminal_id',11)->nullable();
            $table->integer('session_id');
            $table->enum('channel_id',['1','2','3'])->default('1');
            $table->enum('sync_status',['0','1','2'])->default('0')->comment('0 => Sync not initiated, 1 => Sync, 2 => Not Sync');
            $table->softDeletes('deleted_at', 0);
            $table->integer('deleted_by')->default('0');
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
        Schema::dropIfExists('gv_invoices');
    }
}
