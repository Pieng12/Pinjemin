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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('renter_user_id')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('quantity');
            $table->decimal('daily_price', 12, 2);
            $table->unsignedInteger('rental_days');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 30);
            $table->text('renter_note')->nullable();
            $table->text('owner_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['renter_user_id', 'status', 'created_at']);
            $table->index(['item_id', 'status', 'start_date', 'end_date']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
