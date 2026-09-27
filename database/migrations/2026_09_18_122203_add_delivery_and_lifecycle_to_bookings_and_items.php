<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('delivery_enabled')->default(false);
            $table->boolean('free_delivery')->default(false);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('fulfillment_method', 20)->default('pickup');
            $table->json('fulfillment_snapshot')->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('quote_expires_at')->nullable();
            $table->timestamp('renter_confirmed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->string('cancelled_by', 20)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('invoice_snapshot')->nullable();
            $table->index(['status', 'quote_expires_at']);
        });

        DB::table('bookings')->join('items', 'items.id', '=', 'bookings.item_id')
            ->join('users as owners', 'owners.id', '=', 'items.user_id')
            ->join('users as renters', 'renters.id', '=', 'bookings.renter_user_id')
            ->select('bookings.*', 'items.name as item_name', 'items.city as pickup_city', 'items.address as pickup_address',
                'owners.name as owner_name', 'owners.email as owner_email', 'owners.phone as owner_phone',
                'renters.name as renter_name', 'renters.email as renter_email', 'renters.phone as renter_phone')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $snapshot = [
                        'item_name' => $row->item_name,
                        'owner' => ['name' => $row->owner_name, 'email' => $row->owner_email, 'phone' => $row->owner_phone],
                        'renter' => ['name' => $row->renter_name, 'email' => $row->renter_email, 'phone' => $row->renter_phone],
                        'pickup' => ['city' => $row->pickup_city, 'address' => $row->pickup_address],
                        'recipient' => null, 'free_delivery' => false, 'legacy' => true,
                    ];
                    $invoice = null;
                    if ($row->approved_at || in_array($row->status, ['approved', 'completed'], true)) {
                        $invoice = $snapshot + ['booking' => [
                            'booking_code' => $row->booking_code, 'start_date' => $row->start_date,
                            'end_date' => $row->end_date, 'quantity' => $row->quantity,
                            'daily_price' => $row->daily_price, 'rental_days' => $row->rental_days,
                            'subtotal' => $row->subtotal, 'deposit_amount' => $row->deposit_amount,
                            'delivery_fee' => '0.00', 'total_amount' => $row->total_amount,
                            'fulfillment_method' => 'pickup', 'approved_at' => $row->approved_at,
                        ]];
                    }
                    DB::table('bookings')->where('id', $row->id)->update([
                        'fulfillment_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                        'invoice_snapshot' => $invoice ? json_encode($invoice, JSON_THROW_ON_ERROR) : null,
                    ]);
                }
            }, 'bookings.id', 'id');
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'quote_expires_at']);
            $table->dropColumn(['fulfillment_method', 'fulfillment_snapshot', 'delivery_fee', 'quoted_at',
                'quote_expires_at', 'renter_confirmed_at', 'expired_at', 'handed_over_at',
                'cancelled_by', 'cancellation_reason', 'invoice_snapshot']);
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['delivery_enabled', 'free_delivery']);
        });
    }
};
