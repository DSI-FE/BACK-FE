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
        Schema::create('adm_employee_att_permission_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('adm_employee_id');
            $table->foreign('adm_employee_id')->references('id')->on('adm_employees');
            $table->unsignedBigInteger('att_permission_type_id');
            $table->foreign('att_permission_type_id')->references('id')->on('att_permission_types');

            $table->float('used_minutes')->default(0); 
            $table->mediumInteger('used_requests')->default(0);

            $table->tinyInteger('month')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_employee_att_permission_type');
    }
};
