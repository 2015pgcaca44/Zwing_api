<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAisleSectionProductInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aisle_section_product_infos', function (Blueprint $table) {
            $table->increments('id');
			$table->integer('aisle_section_product_id');
            $table->date('manufacturing_date');
            $table->enum('expiring_type', ['EXPIRING_DATE','BEST_BEFORE']);
            $table->date('expiring_date');
            $table->integer('best_before')->nullable();
            $table->integer('remind_before')->nullable();
            $table->integer('rows')->nullable();
            $table->integer('columns')->nullable();
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
        Schema::dropIfExists('aisle_section_product_infos');
    }
}
