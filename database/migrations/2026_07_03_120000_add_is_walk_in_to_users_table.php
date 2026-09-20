<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'is_walk_in')) {
                $table->boolean('is_walk_in')->default(false);
                $table->index('is_walk_in');
            }
        });

        if (Schema::hasColumn('users', 'is_walk_in')) {
            DB::table('users')
                ->where('is_walk_in', false)
                ->whereRaw('LOWER(email) LIKE ?', ['%@walkin.local'])
                ->update(['is_walk_in' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_walk_in')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_walk_in']);
            $table->dropColumn('is_walk_in');
        });
    }
};
