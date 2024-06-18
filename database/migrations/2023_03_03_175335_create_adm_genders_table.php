<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Administration\Gender;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations. 
     */
    public function up(): void
    {
        Schema::create('adm_genders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->whereNull('deleted_at');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();
        });

        DB::transaction(function() {
            Gender::create(['name' => 'Masculino', 'active' => 1]);
            Gender::create(['name' => 'Femenino', 'active' => 1]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_genders');
    }
};
