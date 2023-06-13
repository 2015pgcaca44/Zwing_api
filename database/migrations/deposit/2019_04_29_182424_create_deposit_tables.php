<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDepositTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('v_id');
            $table->integer('store_id')->nullable();
            $table->integer('c_id')->nullable();
            $table->string('amount');
            $table->enum('type', ['ORDER','ADHOC'] );
            $table->string('ref_id');
            //$table->enum('for', ['CUSTOMER'] );
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
        Schema::dropIfExists('customer_auth');
    }
}
