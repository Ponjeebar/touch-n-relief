<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'gender') && ! Schema::hasColumn('users', 'sex')) {
            DB::statement('ALTER TABLE users CHANGE gender sex VARCHAR(16) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'sex') && ! Schema::hasColumn('users', 'gender')) {
            DB::statement('ALTER TABLE users CHANGE sex gender VARCHAR(16) NULL');
        }
    }
};
