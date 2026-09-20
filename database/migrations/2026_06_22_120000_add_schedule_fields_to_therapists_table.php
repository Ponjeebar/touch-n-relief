<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('therapists')) {
            return;
        }

        Schema::table('therapists', function (Blueprint $table): void {
            if (! Schema::hasColumn('therapists', 'working_days')) {
                $table->json('working_days')->nullable()->after('status');
            }
            if (! Schema::hasColumn('therapists', 'day_off_until')) {
                $table->date('day_off_until')->nullable()->after('working_days');
            }
            if (! Schema::hasColumn('therapists', 'work_on_off_day')) {
                $table->boolean('work_on_off_day')->default(false)->after('day_off_until');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('therapists')) {
            return;
        }

        Schema::table('therapists', function (Blueprint $table): void {
            foreach (['work_on_off_day', 'day_off_until', 'working_days'] as $column) {
                if (Schema::hasColumn('therapists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
