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
        Schema::create('att_day_att_schedule', function (Blueprint $table)
        {
            $table->id();

            $table->unsignedBigInteger('att_day_id');
            $table->foreign('att_day_id')->references('id')->on('att_days');
            
            $table->unsignedBigInteger('att_schedule_id');
            $table->foreign('att_schedule_id')->references('id')->on('att_schedules');

            $table->time('time_start');
            $table->time('time_end');

            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('att_day_att_schedule');
    }
};
