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
        Schema::create('adm_employee_att_schedule', function (Blueprint $table)
        {
            $table->id();

            $table->unsignedBigInteger('adm_employee_id');
            $table->foreign('adm_employee_id')->references('id')->on('adm_employees');

            $table->unsignedBigInteger('att_schedule_id');
            $table->foreign('att_schedule_id')->references('id')->on('att_schedules');

            $table->date('date_start')->nullable()->useCurrent();
            $table->date('date_end')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_employee_att_schedule');
    }
};
