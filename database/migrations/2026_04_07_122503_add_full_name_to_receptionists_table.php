<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('receptionists', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('receptionist_id');
        });

        DB::table('receptionists')->update([
            'full_name' => DB::raw('username'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receptionists', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};
