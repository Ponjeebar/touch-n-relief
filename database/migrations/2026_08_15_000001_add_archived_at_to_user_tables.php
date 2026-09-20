<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'archived_at')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable()->after('updated_at');
                $table->index('archived_at');
            });
        }

        if (Schema::hasTable('receptionists') && ! Schema::hasColumn('receptionists', 'archived_at')) {
            Schema::table('receptionists', function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable()->after('updated_at');
                $table->index('archived_at');
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'archived_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable()->after('updated_at');
                $table->index('archived_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'archived_at')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            });
        }

        if (Schema::hasTable('receptionists') && Schema::hasColumn('receptionists', 'archived_at')) {
            Schema::table('receptionists', function (Blueprint $table): void {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'archived_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['archived_at']);
                $table->dropColumn('archived_at');
            });
        }
    }
};
