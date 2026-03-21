<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_id');
            $table->string('avatar_url')->nullable();
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });

        if (! Schema::hasColumn('users', 'force_mfa')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('force_mfa')->default(false)->after('remember_token');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');

        if (Schema::hasColumn('users', 'force_mfa')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('force_mfa');
            });
        }
    }
};
