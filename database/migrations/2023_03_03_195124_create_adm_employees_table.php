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
        Schema::create('adm_employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lastname');
            $table->string('email')->unique()->whereNull('deleted_at');
            $table->string('email_personal')->nullable()->unique()->whereNull('deleted_at');
            $table->string('phone')->nullable();
            $table->string('phone_personal')->nullable();
            $table->string('photo_name')->nullable();
            $table->string('photo_route')->nullable();
            $table->string('photo_route_sm')->nullable();
            $table->date('birthday')->nullable()->useCurrent();
            $table->boolean('marking_required')->default(true);
            $table->boolean('children')->default(false);
            $table->boolean('external')->default(false);
            $table->boolean('viatic')->default(false);
            $table->boolean('vehicle')->default(false);
            $table->boolean('adhonorem')->default(false);
            $table->boolean('parking')->default(false);
            $table->boolean('disabled')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->boolean('active')->default(true);
            $table->boolean('remote_mark')->default(false);
            $table->text('unsubscribe_justification')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('adm_gender_id')->nullable();
            $table->foreign('adm_gender_id')->references('id')->on('adm_genders');
            $table->unsignedBigInteger('adm_marital_status_id')->nullable();
            $table->foreign('adm_marital_status_id')->references('id')->on('adm_marital_statuses');
            $table->unsignedBigInteger('adm_address_id')->nullable();
            $table->foreign('adm_address_id')->references('id')->on('adm_addresses');
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
        Schema::dropIfExists('adm_employees');
    }
};
