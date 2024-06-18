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
        Schema::create('adm_document_type_adm_employee', function (Blueprint $table)
        {
            $table->id();
            $table->string('value')->unique();

            $table->unsignedBigInteger('adm_employee_id');
            $table->foreign('adm_employee_id')->references('id')->on('adm_employees');

            $table->unsignedBigInteger('adm_document_type_id');
            $table->foreign('adm_document_type_id')->references('id')->on('adm_document_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_document_type_adm_employee');
    }
};
