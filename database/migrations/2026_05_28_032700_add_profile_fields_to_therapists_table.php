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
            if (! Schema::hasColumn('therapists', 'contact_number')) {
                $table->string('contact_number', 30)->nullable()->after('name');
            }
            if (! Schema::hasColumn('therapists', 'address')) {
                $table->string('address')->nullable()->after('contact_number');
            }
            if (! Schema::hasColumn('therapists', 'email')) {
                $table->string('email')->nullable()->after('address');
            }
            if (! Schema::hasColumn('therapists', 'birthday')) {
                $table->date('birthday')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('therapists')) {
            return;
        }

        Schema::table('therapists', function (Blueprint $table): void {
            $drops = [];
            if (Schema::hasColumn('therapists', 'contact_number')) {
                $drops[] = 'contact_number';
            }
            if (Schema::hasColumn('therapists', 'address')) {
                $drops[] = 'address';
            }
            if (Schema::hasColumn('therapists', 'email')) {
                $drops[] = 'email';
            }
            if (Schema::hasColumn('therapists', 'birthday')) {
                $drops[] = 'birthday';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};

