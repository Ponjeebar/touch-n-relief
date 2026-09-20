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
            if (! Schema::hasColumn('therapists', 'role')) {
                $table->string('role')->nullable()->after('name');
            }
            if (! Schema::hasColumn('therapists', 'bio')) {
                $table->text('bio')->nullable()->after('role');
            }
            if (! Schema::hasColumn('therapists', 'certifications')) {
                $table->json('certifications')->nullable()->after('specializations');
            }
            if (! Schema::hasColumn('therapists', 'sessions_label')) {
                $table->string('sessions_label', 30)->nullable()->after('certifications');
            }
            if (! Schema::hasColumn('therapists', 'accent_color')) {
                $table->string('accent_color', 20)->nullable()->after('sessions_label');
            }
            if (! Schema::hasColumn('therapists', 'landing_photo')) {
                $table->string('landing_photo')->nullable()->after('photo_url');
            }
            if (! Schema::hasColumn('therapists', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('service_hours_pct');
            }
            if (! Schema::hasColumn('therapists', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('therapists')) {
            return;
        }

        Schema::table('therapists', function (Blueprint $table): void {
            foreach (['role', 'bio', 'certifications', 'sessions_label', 'accent_color', 'landing_photo', 'is_active', 'sort_order'] as $column) {
                if (Schema::hasColumn('therapists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
