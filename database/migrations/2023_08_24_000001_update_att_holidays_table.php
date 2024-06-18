<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('att_holidays', function (Blueprint $table)
        {
            $table->unsignedBigInteger('gral_file_id')->after('vacation')->nullable();
            $table->foreign('gral_file_id')->references('id')->on('gral_files');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('att_holidays', function (Blueprint $table)
        {
            $table->dropColumn('gral_file_id');
        });
    }
};
