<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_verification_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('purpose', 32);
            $table->string('email');
            $table->string('code_hash');
            $table->text('payload')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->timestamps();
            $table->index(['purpose', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_verification_codes');
    }
};
