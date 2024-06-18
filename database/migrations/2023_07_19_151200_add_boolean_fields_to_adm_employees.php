<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('adm_employees', function ($table) {
            if (!Schema::hasColumn('adm_employees', 'vehicle')) {
                $table->boolean('vehicle')->default(false);
            }
            if (!Schema::hasColumn('adm_employees', 'adhonorem')) {
                $table->boolean('adhonorem')->default(false);
            }
            if (!Schema::hasColumn('adm_employees', 'parking')) {
                $table->boolean('parking')->default(false);
            }
            if (!Schema::hasColumn('adm_employees', 'disabled')) {
                $table->boolean('disabled')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adm_employees', function ($table) {
            if (Schema::hasColumn('adm_employees', 'vehicle')) {
                $table->dropColumn('vehicle');
            }
            if (Schema::hasColumn('adm_employees', 'adhonorem')) {
                $table->dropColumn('adhonorem');
            }
            if (Schema::hasColumn('adm_employees', 'parking')) {
                $table->dropColumn('parking');
            }
            if (Schema::hasColumn('adm_employees', 'disabled')) {
                $table->dropColumn('disabled');
            }
        });
    }
};
