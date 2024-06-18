<?php

use App\Models\Attendance\Day;
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
        Schema::create('att_days', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->whereNull('deleted_at');
            $table->tinyInteger('number')->unique()->whereNull('deleted_at');
            $table->boolean('working_day')->default(false);
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();
        });

        Day::create(['name' => 'Lunes', 'number' => 1, 'working_day' => 1]);
        Day::create(['name' => 'Martes', 'number' => 2, 'working_day' => 1]);
        Day::create(['name' => 'Miércoles', 'number' => 3, 'working_day' => 1]);
        Day::create(['name' => 'Jueves', 'number' => 4, 'working_day' => 1]);
        Day::create(['name' => 'Viernes', 'number' => 5, 'working_day' => 1]);
        Day::create(['name' => 'Sábado', 'number' => 6, 'working_day' => 0]);
        Day::create(['name' => 'Domingo', 'number' => 7, 'working_day' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('att_days');
    }
};
