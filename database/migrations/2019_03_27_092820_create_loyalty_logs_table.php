<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLoyaltyLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('loyalty_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('v_id')->nullable();
            $table->integer('store_id')->nullable();
            $table->integer('status')->nullable();
            $table->string('mobile', 15)->nullable();
            $table->text('email')->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->enum('type', ['EMR'])->nullable();
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
        Schema::dropIfExists('loyalty_logs');
    }
}
