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
        Schema::table('user_payment_methods', function (Blueprint $table): void {
            $table->boolean('is_platform')->default(false)->index();
        });
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->string('transaction_reference', 150)->nullable();
            $table->string('transaction_key', 64)->nullable()->unique();
        });
        Schema::table('bookings', function (Blueprint $table): void {
            $table->json('refund_destination')->nullable();
            $table->foreignId('refund_claimed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('refund_claimed_at')->nullable();
        });
        Schema::table('booking_refunds', function (Blueprint $table): void {
            $table->json('destination_snapshot')->nullable();
        });
        Schema::create('booking_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->json('destination_snapshot');
            $table->json('events')->nullable();
            $table->text('hold_reason')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('transaction_reference', 150)->nullable();
            $table->string('transaction_key', 64)->nullable()->unique();
            $table->string('proof_path')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payouts');
        Schema::table('booking_refunds', fn (Blueprint $table) => $table->dropColumn('destination_snapshot'));
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refund_claimed_by_user_id');
            $table->dropColumn(['refund_destination', 'refund_claimed_at']);
        });
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropUnique(['transaction_key']);
            $table->dropColumn(['transaction_reference', 'transaction_key']);
        });
        Schema::table('user_payment_methods', function (Blueprint $table): void {
            $table->dropIndex(['is_platform']);
            $table->dropColumn('is_platform');
        });
    }
};
