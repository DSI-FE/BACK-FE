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
        Schema::table('users', function (Blueprint $table)
        {
            $table->string('lastname',255)->after('name')->nullable();
            $table->string('username',32)->after('lastname')->unique();
            $table->tinyInteger('status')->after('remember_token')->default(1);
            $table->tinyInteger('change_password')->after('password')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('lastname');
            $table->dropColumn('username');
            $table->dropColumn('status');
            $table->dropColumn('change_password');
        });
    }
};