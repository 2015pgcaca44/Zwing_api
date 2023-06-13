<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlanogramsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('planograms', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_id');
            $table->integer('v_id');
            $table->integer('store_id');
            $table->string('barcode');
            $table->integer('column');
            $table->integer('row');
            $table->integer('face');
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
        Schema::dropIfExists('planograms');
    }
}
