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
        Schema::create('adm_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('employee_id_applicant');
            $table->foreign('employee_id_affected')->references('id')->on('adm_employees');
            $table->unsignedBigInteger('employee_id_affected');
            $table->foreign('employee_id_authorizing')->references('id')->on('adm_employees');
            $table->unsignedBigInteger('employee_id_authorizing');
            $table->foreign('employee_id_applicant')->references('id')->on('adm_employees');
            $table->unsignedBigInteger('adm_request_type_id');
            $table->foreign('adm_request_type_id')->references('id')->on('adm_request_types');
            $table->boolean('status')->default(true);
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
        Schema::dropIfExists('adm_requests');
    }
};
