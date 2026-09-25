<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'gender') && ! Schema::hasColumn('users', 'sex')) {
            Schema::table('users', function ($table): void {
                $table->renameColumn('gender', 'sex');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'sex') && ! Schema::hasColumn('users', 'gender')) {
            Schema::table('users', function ($table): void {
                $table->renameColumn('sex', 'gender');
            });
        }
    }
};
