<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateAdviceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('advice', function (Blueprint $table) {
            $table->dropColumn('supplier_name');
            $table->integer('supplier_id')->unsigned()->nullable()->after('status');
            $table->enum('advice_type', ['ADHOC', 'NORMAL'])->nullable()->after('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
