<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendorPurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendor_purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('v_id');
            $table->integer('store_id');
            $table->enum('discrepencies',['0','1']);
            $table->enum('sm_approval_req_sent',['0','1']);
            $table->enum('sm_approved',['0','1']);
            $table->enum('dp_approved',['0','1']);
            $table->string('bill_image');
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
        Schema::dropIfExists('vendor_purchase_orders');
    }
}
