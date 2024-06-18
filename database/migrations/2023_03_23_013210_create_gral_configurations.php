<?php

use App\Models\General\GralConfiguration;
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
        Schema::create('gral_configurations', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('identifier');
            $table->text('value');

            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();
        });

        GralConfiguration::create([
            'name' => 'Minutos de Cortesía',
            'identifier' => 'minutos_cortesia',
            'value' => 10,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gral_configurations');
    }
};
