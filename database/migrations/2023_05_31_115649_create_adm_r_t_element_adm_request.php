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
        Schema::create('adm_r_t_element_adm_request', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('adm_request_id');
            $table->foreign('adm_request_id')->references('id')->on('adm_requests');
            $table->unsignedBigInteger('adm_request_type_element_id');
            $table->foreign('adm_request_type_element_id')->references('id')->on('adm_request_type_elements');
            $table->boolean('value_boolean')->nullable();
            $table->boolean('value_string')->nullable();
            $table->string('field_name')->nullable();
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_r_t_element_adm_request');
    }
};
