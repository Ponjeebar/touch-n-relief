<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('time_slots')) {
            return;
        }

        Schema::table('time_slots', function (Blueprint $table): void {
            if (! Schema::hasColumn('time_slots', 'is_custom')) {
                $table->boolean('is_custom')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('time_slots')) {
            return;
        }

        Schema::table('time_slots', function (Blueprint $table): void {
            if (Schema::hasColumn('time_slots', 'is_custom')) {
                $table->dropColumn('is_custom');
            }
        });
    }
};
