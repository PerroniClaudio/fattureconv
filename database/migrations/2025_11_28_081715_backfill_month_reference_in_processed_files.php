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
        // Backfill month_reference for existing records
        // Set it to created_at - 1 month
        $files = \Illuminate\Support\Facades\DB::table('processed_files')->whereNull('month_reference')->get();
        foreach ($files as $file) {
            $created = \Carbon\Carbon::parse($file->created_at);
            $monthRef = $created->subMonth()->startOfMonth();
            \Illuminate\Support\Facades\DB::table('processed_files')
                ->where('id', $file->id)
                ->update(['month_reference' => $monthRef]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse data update usually, or set to null
        \Illuminate\Support\Facades\DB::table('processed_files')->update(['month_reference' => null]);
    }
};
