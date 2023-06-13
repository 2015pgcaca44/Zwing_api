<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAisleSectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aisle_sections', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('v_id');
            $table->integer('store_id');
            $table->integer('aisle_id');
            $table->string('code');
            $table->string('barcode');
            $table->enum('status',['0','1']);
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
        Schema::dropIfExists('aisle_sections');
    }
}
