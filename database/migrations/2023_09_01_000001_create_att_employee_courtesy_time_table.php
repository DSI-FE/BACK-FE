<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. 
     */
    public function up(): void
    {
        Schema::create('att_employee_courtesy_time', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('adm_employee_id');
            $table->foreign('adm_employee_id')->references('id')->on('adm_employees');
            $table->tinyInteger('month')->default(0);
            $table->float('available_minutes')->default(0);
            $table->float('used_minutes')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('att_employee_courtesy_time');
    }
};
