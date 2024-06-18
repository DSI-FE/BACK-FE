<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Administration\Department;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('adm_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->whereNull('deleted_at');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();
        });

        DB::transaction(function () {
            $departments[] = Department::create(['name' => 'Usulután']);
            $departments[] = Department::create(['name' => 'Santa Ana']);
            $departments[] = Department::create(['name' => 'Cabañas']);
            $departments[] = Department::create(['name' => 'Ahuachapán']);
            $departments[] = Department::create(['name' => 'Chalatenango']);
            $departments[] = Department::create(['name' => 'Cuscatlán']);
            $departments[] = Department::create(['name' => 'La Paz']);
            $departments[] = Department::create(['name' => 'La Libertad']);
            $departments[] = Department::create(['name' => 'Sonsonate']);
            $departments[] = Department::create(['name' => 'San Salvador']);
            $departments[] = Department::create(['name' => 'San Vicente']);
            $departments[] = Department::create(['name' => 'San Miguel']);
            $departments[] = Department::create(['name' => 'Morazán']);
            $departments[] = Department::create(['name' => 'La Unión']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_departments');
    }
};
