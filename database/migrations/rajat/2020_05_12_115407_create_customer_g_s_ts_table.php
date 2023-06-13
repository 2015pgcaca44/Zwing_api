<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomerGSTsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_gstin', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('v_id');
            $table->integer('c_id')->unsigned();
            $table->integer('state_id')->unsigned();
            $table->text('legal_name')->nullable();
            $table->string('gstin');
            $table->integer('created_by')->unsigned();
            $table->enum('deleted_at',['0','1'])->default('0');
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
        Schema::dropIfExists('customer_gstin');
    }
}
