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
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('allow_deposit_payment')->default(false);
            $table->boolean('allow_cash_balance')->default(false);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->string('payment_plan', 30)->nullable();
            $table->json('payment_methods_snapshot')->nullable();
            $table->json('payment_receipt_snapshot')->nullable();
            $table->timestamp('payment_verified_at')->nullable();
            $table->boolean('payment_legacy')->default(false);
            $table->string('refund_status', 20)->nullable();
            $table->index(['status', 'payment_due_at']);
        });

        DB::table('bookings')
            ->whereIn('status', ['approved', 'completed'])
            ->update([
                'accepted_at' => DB::raw('COALESCE(approved_at, created_at)'),
                'payment_verified_at' => DB::raw('COALESCE(approved_at, created_at)'),
                'payment_legacy' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_due_at']);
            $table->dropColumn([
                'accepted_at', 'payment_due_at', 'payment_plan', 'payment_methods_snapshot',
                'payment_receipt_snapshot', 'payment_verified_at', 'payment_legacy', 'refund_status',
            ]);
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['allow_deposit_payment', 'allow_cash_balance']);
        });
    }
};
