<?php

use App\Models\Administration\MaritalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('adm_marital_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->whereNull('deleted_at');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();
        });

        DB::transaction(function () {
            MaritalStatus::create(
                [
                    'name' => 'Soltero(a)',
                    'active' => true
                ],
                [
                    'name' => 'Casado(a)',
                    'active' => true
                ],
                [
                    'name' => 'Divorciado(a)',
                    'active' => true
                ],
                [
                    'name' => 'Viudo(a)',
                    'active' => true
                ],
                [
                    'name' => 'Unión Libre',
                    'active' => true
                ],
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_marital_statuses');
    }
};
