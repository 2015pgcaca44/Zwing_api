<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVendorUsersAuthTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vender_users_auth', function (Blueprint $table) {
            $table->increments('vu_id');
            $table->bigInteger('mobile')->nullable();
            $table->integer('vendor_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->char('status',2)->default('0');
            $table->char('mobile_active',2)->default('0');
            $table->char('email_active',2)->default('0');
            $table->integer('otp')->nullable();
            $table->string('api_token')->nullable();
            $table->string('device_name')->nullable();
            $table->string('os_name')->nullable();
            $table->string('os_version',100)->nullable();
            $table->string('udid')->nullable();
            $table->string('imei')->nullable();
            $table->string('latitude',100)->nullable();
            $table->string('longitude',100)->nullable();
            $table->string('device_model_number')->nullable();
            $table->rememberToken();
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
        Schema::dropIfExists('vender');
    }
}
