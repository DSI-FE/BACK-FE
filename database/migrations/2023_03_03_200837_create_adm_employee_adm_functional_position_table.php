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
        Schema::create('adm_employee_adm_functional_position', function (Blueprint $table)
        {
            $table->id();

            $table->date('date_start')->nullable()->useCurrent();
            $table->date('date_end')->nullable();
            
            $table->boolean('principal')->default(true);
            $table->float('salary', 9, 2)->nullable();
            $table->boolean('active')->default(true);

            $table->unsignedBigInteger('adm_employee_id');
            $table->foreign('adm_employee_id')->references('id')->on('adm_employees');

            $table->unsignedBigInteger('adm_functional_position_id');
            $table->foreign('adm_functional_position_id','adm_employee_adm_functional_position_adm_functional_p_id_foreign')->references('id')->on('adm_functional_positions');

            
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_employee_adm_functional_position');
    }
};
