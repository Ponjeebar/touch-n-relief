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
        if (! Schema::hasTable('receptionists')) {
            return;
        }

        Schema::table('receptionists', function (Blueprint $table) {
            if (! Schema::hasColumn('receptionists', 'address')) {
                $table->string('address')->nullable()->after('email');
            }
            if (! Schema::hasColumn('receptionists', 'phone_number')) {
                $table->string('phone_number', 30)->nullable()->after('address');
            }
            if (! Schema::hasColumn('receptionists', 'birthday')) {
                $table->date('birthday')->nullable()->after('phone_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('receptionists')) {
            return;
        }

        Schema::table('receptionists', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('receptionists', 'address')) {
                $drops[] = 'address';
            }
            if (Schema::hasColumn('receptionists', 'phone_number')) {
                $drops[] = 'phone_number';
            }
            if (Schema::hasColumn('receptionists', 'birthday')) {
                $drops[] = 'birthday';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};

