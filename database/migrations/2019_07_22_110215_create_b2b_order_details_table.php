<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateB2bOrderDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('b2b_order_details', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('transaction_type',['sales','return']);
            $table->integer('store_id')->nullable();
            $table->integer('v_id')->nullable();
            $table->integer('order_id')->nullable();
            $table->string('t_order_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->enum('weight_flag',['0','1']);
            $table->string('plu_barcode');
            $table->string('barcode')->nullable();
            $table->text('item_name');
            $table->string('item_id');
            $table->string('qty')->nullable();
            $table->decimal('subtotal',12,2)->nullable();
            $table->decimal('unit_mrp',12,2);
            $table->string('unit_csp')->nullable();
            $table->string('override_unit_price')->nullable();
            $table->string('override_reason')->nullable();
            $table->enum('override_flag',['0','1'])->nullable();
            $table->integer('override_by')->nullable();
            $table->decimal('discount',12,2);
            $table->string('employee_id');
            $table->decimal('employee_discount',12,2);
            $table->decimal('bill_buster_discount',12,2);
            $table->decimal('tax',12,2);
            $table->decimal('total',12,2);
            $table->char('is_catalog',10)->nullable();
            $table->enum('status',['process','success','error'])->nullable();
            $table->string('return_code')->nullable();
            $table->enum('trans_from',['ANDROID','IOS','ANDROID_KIOSK','ANDROID_VENDOR']);
            $table->integer('vu_id')->default('0');
            $table->integer('salesman_id')->nullable();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
            $table->enum('delivery',['Yes','No'])->default('No');
            $table->string('slab');
            $table->text('target_offer');
            $table->text('section_target_offers');
            $table->text('section_offers');
            $table->string('department_id');
            $table->string('subclass_id');
            $table->string('printclass_id');
            $table->text('pdata')->nullable();
            $table->text('tdata')->nullable();
            $table->integer('reason_id')->nullable();
            $table->string('group_id');
            $table->string('division_id');
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
        Schema::dropIfExists('b2b_order_details');
    }
}
