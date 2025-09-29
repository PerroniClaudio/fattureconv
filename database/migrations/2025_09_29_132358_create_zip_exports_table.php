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
        Schema::create('zip_exports', function (Blueprint $table) {
            $table->id();
            $table->string('zip_filename');
            $table->string('gcs_path')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, error
            $table->text('error_message')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zip_exports');
    }
};
