<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('c_id')->nullable();
            $table->text('name')->nullable();
            $table->text('address_nickname')->nullable();
            $table->string('mobile',15)->nullable();
            $table->string('pincode',20)->nullable();
            $table->text('address1')->nullable();
            $table->text('address2')->nullable();
            $table->text('landmark')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->integer('deleted_status')->default(0);
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
        Schema::dropIfExists('addresses');
    }
}
