<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateB2bOrderExtrasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('b2b_order_extra', function (Blueprint $table) {
            $table->increments('ex_id');
            $table->integer('v_id');
            $table->integer('store_id');
            $table->string('order_id');
            $table->string('agent_id')->nullable();
            $table->text('destination_site');
            $table->string('size_matrix');
            $table->text('remarks');
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
        Schema::dropIfExists('b2b_order_extras');
    }
}
