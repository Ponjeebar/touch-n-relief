<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_walk_in')) {
            return;
        }

        DB::table('users')
            ->where('is_walk_in', false)
            ->whereRaw('LOWER(username) LIKE ?', ['walkin\_%'])
            ->update(['is_walk_in' => true]);
    }

    public function down(): void
    {
        // Data backfill only.
    }
};
