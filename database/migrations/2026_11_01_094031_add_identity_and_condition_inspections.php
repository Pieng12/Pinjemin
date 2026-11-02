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
        Schema::table('items', function (Blueprint $table): void {
            $table->boolean('requires_condition_photos')->default(false);
            $table->boolean('requires_identity_verification')->default(false);
        });
        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 10);
            $table->text('legal_name')->nullable();
            $table->text('document_number')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_mime', 40)->nullable();
            $table->date('document_expires_on')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('consented_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_until')->nullable();
            $table->timestamp('retain_until')->nullable()->index();
            $table->timestamp('hold_review_at')->nullable();
            $table->text('hold_reason')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
            $table->index(['status', 'verified_until']);
        });
        Schema::create('identity_verification_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('identity_verification_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('identity_verification_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
        });
        Schema::create('condition_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('stage', 12);
            $table->unsignedInteger('attempt')->default(1);
            $table->string('status', 20)->default('waiting');
            $table->timestamp('owner_ready_at')->nullable();
            $table->timestamp('renter_ready_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('resolution_note')->nullable();
            $table->string('resolution_outcome', 30)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('events')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'stage', 'attempt']);
            $table->index(['status', 'expires_at']);
        });
        Schema::create('condition_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condition_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 10);
            $table->string('kind', 12)->default('initial');
            $table->string('initial_key')->nullable()->unique();
            $table->string('function_state', 20)->nullable();
            $table->text('accessories')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('condition_note')->nullable();
            $table->json('photos')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('decision', 12)->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('condition_reports');
        Schema::dropIfExists('condition_sessions');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('identity_verification_id');
            $table->dropColumn('returned_at');
        });
        Schema::dropIfExists('identity_verification_events');
        Schema::dropIfExists('identity_verifications');
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn(['requires_condition_photos', 'requires_identity_verification']));
    }
};
