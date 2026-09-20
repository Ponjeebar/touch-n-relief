<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('client_name')
                ->constrained()
                ->nullOnDelete();
        });

        foreach (DB::table('users')->select(['id', 'name'])->get() as $user) {
            $name = strtolower(trim((string) $user->name));
            if ($name === '') {
                continue;
            }

            DB::table('transactions')
                ->whereNull('user_id')
                ->whereRaw('LOWER(client_name) = ?', [$name])
                ->update(['user_id' => $user->id]);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
