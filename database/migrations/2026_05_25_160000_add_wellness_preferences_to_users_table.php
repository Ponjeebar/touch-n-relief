<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sex', 16)->nullable()->after('contact_number');
            $table->string('therapist_gender_preference', 24)->nullable()->after('sex');
            $table->boolean('is_pregnant')->nullable()->after('therapist_gender_preference');
            $table->string('pressure_preference', 16)->nullable()->after('is_pregnant');
            $table->timestamp('profile_completed_at')->nullable()->after('pressure_preference');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sex',
                'therapist_gender_preference',
                'is_pregnant',
                'pressure_preference',
                'profile_completed_at',
            ]);
        });
    }
};
