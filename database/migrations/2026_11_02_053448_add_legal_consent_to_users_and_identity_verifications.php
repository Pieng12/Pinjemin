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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('terms_version', 60)->nullable();
            $table->string('privacy_version', 60)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
        });
        Schema::table('identity_verifications', function (Blueprint $table): void {
            $table->string('privacy_version', 60)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table): void {
            $table->dropColumn('privacy_version');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['terms_version', 'privacy_version', 'terms_accepted_at']);
        });
    }
};
