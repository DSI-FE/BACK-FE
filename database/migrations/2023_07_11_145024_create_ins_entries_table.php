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
        Schema::create('ins_entries', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->text('content')->nullable();

            $table->string('url',511)->nullable();

            $table->boolean('show_in_carousel')->nullable()->default(1);

            $table->tinyInteger('type');    // 1: home | 2: gallery | 3:descargables  | 4: revista
            $table->tinyInteger('subtype')->nullable();

            $table->boolean('internal')->default(1); // about url/link, internal page or external (Like links to Google Drive)

            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();

            $table->boolean('active')->default(1);
            
            $table->unsignedBigInteger('adm_employee_id');
            $table->foreign('adm_employee_id')->references('id')->on('adm_employees');
            
            $table->unsignedBigInteger('gral_file_id')->nullable();
            $table->foreign('gral_file_id')->references('id')->on('gral_files');

            $table->unsignedBigInteger('gral_file_banner_id')->nullable();
            $table->foreign('gral_file_banner_id')->references('id')->on('gral_files');

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
        Schema::dropIfExists('ins_entries');
    }
};
