<?php

use App\Models\Administration\DocumentType;
use Illuminate\Support\Facades\DB;
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
        Schema::create('adm_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->whereNull('deleted_at');
            $table->string('format');
            $table->boolean('active');
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();
        });

        DB::transaction(function () {
            DocumentType::create(
                [
                    'name' => 'DUI',
                    'format' => '00000000-0',
                    'active' => true,
                ],
                [
                    'name' => 'NIT',
                    'format' => '0000-000000-000-0',
                    'active' => true,
                ],
                [
                    'name' => 'NUP',
                    'format' => '000000000000',
                    'active' => true,
                ],
                [
                    'name' => 'ISSS',
                    'format' => '000000000',
                    'active' => true,
                ],
                [
                    'name' => 'Licencia de conducir',
                    'format' => '0000-000000-000-0',
                    'active' => true,
                ],
                [
                    'name' => 'Hacienda ID',
                    'format' => '00000000000000',
                    'active' => true,
                ],
                [
                    'name' => 'Código de empleado',
                    'format' => '0000000',
                    'active' => true,
                ]
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_document_types');
    }
};
