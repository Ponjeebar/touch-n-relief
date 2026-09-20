<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('registrations', 'registered_ip')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->dropColumn('registered_ip');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('registrations', 'registered_ip')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->string('registered_ip', 45)->nullable()->after('contact_number');
            });
        }
    }
};
