<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('adm_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('principal');
            $table->string('urbanization')->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->text('complement')->nullable();

            $table->unsignedBigInteger('adm_department_id')->nullable();
            $table->foreign('adm_department_id')->references('id')->on('adm_departments');

            $table->unsignedBigInteger('adm_municipality_id')->nullable();
            $table->foreign('adm_municipality_id')->references('id')->on('adm_municipalities');

            $table->unsignedBigInteger('adm_employee_id');
            $table->foreign('adm_employee_id')->references('id')->on('adm_employees');

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
        Schema::dropIfExists('adm_addresses');
    }
};
