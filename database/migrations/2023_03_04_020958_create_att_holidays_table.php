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
        Schema::create('att_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->whereNull('deleted_at');
            $table->text('description')->nullable();
            $table->tinyInteger('type')->default(0);
            
            $table->date('date_start');
            $table->time('time_start')->nullable();
            $table->date('date_end')->nullable();
            $table->time('time_end')->nullable();
            $table->boolean('permanent')->default(false);
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('att_holidays');
    }
};
