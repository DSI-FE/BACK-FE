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
        Schema::table('adm_employees', function($table) {
            if (!Schema::hasColumn('adm_employees', 'unsubscribe_justification')) {
                $table->text('unsubscribe_justification')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adm_employees', function($table) {
            if (Schema::hasColumn('adm_employees', 'unsubscribe_justification')) {
                $table->dropColumn('unsubscribe_justification');
            }
        });

    }
};
