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
        Schema::create('adm_organizational_units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->whereNull('deleted_at');
            $table->string('abbreviation')->nullable()->unique()->whereNull('deleted_at');
            $table->string('code',32)->nullable()->unique()->whereNull('deleted_at');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('adm_organizational_unit_type_id')->nullable();
            $table->foreign('adm_organizational_unit_type_id')->references('id')->on('adm_organizational_unit_types');
            $table->unsignedBigInteger('adm_organizational_unit_id')->nullable();
            $table->foreign('adm_organizational_unit_id')->references('id')->on('adm_organizational_units');
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
        Schema::dropIfExists('adm_organizational_units');
    }
};
