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
        Schema::create('att_permission_types', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique()->whereNull('deleted_at');
            $table->text('description');
            $table->string('internal_code')->unique()->whereNull('deleted_at');
            $table->tinyInteger('type')->default(1); // clasificacion 1 o 2
            $table->boolean('leave_pay')->default(true); // goce_sueldo

            $table->mediumInteger('max_hours_per_request')->default(2880)->nullable();
            $table->mediumInteger('max_days_per_request')->default(30)->nullable();

            $table->mediumInteger('max_hours_per_year')->default(2880)->nullable(); // maximo_dias
            $table->mediumInteger('max_hours_per_month')->default(2880)->nullable();
            $table->mediumInteger('max_requests_per_year')->default(2880)->nullable();
            $table->mediumInteger('max_requests_per_month')->default(2880)->nullable();

            $table->boolean('show_in_dashboard')->default(false);
            $table->tinyInteger('dashboard_herarchy')->default(0)->nullable();

            $table->boolean('show_accumulated_hours_per_year')->default(true);
            $table->boolean('show_accumulated_hours_per_month')->default(true);
            $table->boolean('show_accumulated_requests_per_year')->default(true);
            $table->boolean('show_accumulated_requests_per_month')->default(true);

            $table->mediumInteger('permission_days')->default(null)->nullable();

            $table->boolean('non_business_days')->default(true);    // dias_no_habiles
            $table->boolean('static')->default(false);

            $table->tinyInteger('gender')->default(3);
            $table->boolean('childs')->default(false);

            $table->boolean('active')->default(true);   // bloquear_solicitudes
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
        Schema::dropIfExists('att_permission_types');
    }
};
