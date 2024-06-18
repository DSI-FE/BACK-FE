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
        Schema::create('adm_functional_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation')->nullable()->unique()->whereNull('deleted_at');
            $table->text('description')->nullable();
            $table->mediumInteger('amount_required')->nullable()->default(1);
            $table->float('salary_min')->nullable()->default(0);
            $table->float('salary_max')->nullable()->default(0);

            $table->boolean('boss')->default(false);
            $table->tinyInteger('boss_hierarchy')->nullable()->default(0);
            $table->boolean('extra_hours')->default(false);
            $table->boolean('original')->default(true)->nullable();
            $table->boolean('user_required')->default(true)->nullable();
            $table->boolean('active')->default(true);

            $table->unsignedBigInteger('adm_organizational_unit_id')->nullable();
            $table->foreign('adm_organizational_unit_id')->references('id')->on('adm_organizational_units');

            $table->unsignedBigInteger('adm_functional_position_id')->nullable();
            $table->foreign('adm_functional_position_id')->references('id')->on('adm_functional_positions');

            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();

            $table->unique(['name', 'adm_organizational_unit_id', 'deleted_at'], 'unique_name_unit_id_delete_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_functional_positions');
    }
};
