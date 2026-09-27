<?php

namespace App\Policies;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\PaymentPlan;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $this->isRenter($user, $booking) || $this->isOwner($user, $booking) || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function viewAsRenter(User $user, Booking $booking): bool
    {
        return $this->isRenter($user, $booking);
    }

    public function viewAsOwner(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->isRenter($user, $booking) && $booking->canBeCancelledByRenter();
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking) && $booking->status === BookingStatus::Pending;
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $this->approve($user, $booking);
    }

    public function quoteDelivery(User $user, Booking $booking): bool
    {
        return $this->approve($user, $booking) && $booking->fulfillment_method === Booking::Delivery
            && ! ($booking->fulfillment_snapshot['free_delivery'] ?? false);
    }

    public function confirmDelivery(User $user, Booking $booking): bool
    {
        return $this->isRenter($user, $booking) && $booking->status === BookingStatus::AwaitingRenterConfirmation;
    }

    public function submitPayment(User $user, Booking $booking): bool
    {
        return $this->isRenter($user, $booking)
            && in_array($booking->effectiveStatus(), [BookingStatus::AwaitingPayment, BookingStatus::PartiallyPaid], true);
    }

    public function reviewPayment(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking) && $booking->status === BookingStatus::PaymentReview;
    }

    public function cashAndHandOver(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking)
            && $booking->status === BookingStatus::PartiallyPaid
            && $booking->payment_plan === PaymentPlan::DepositCash;
    }

    public function recordRefund(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking)
            && $booking->status === BookingStatus::Cancelled
            && $booking->refund_status === 'pending';
    }

    public function cancelAsOwner(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking) && $booking->canBeCancelledByRenter();
    }

    public function handOver(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking) && $booking->status === BookingStatus::Approved && ! $booking->handed_over_at;
    }

    public function complete(User $user, Booking $booking): bool
    {
        return $this->isOwner($user, $booking) && $booking->status === BookingStatus::Approved && (bool) $booking->handed_over_at;
    }

    public function downloadInvoice(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking) && $booking->invoice_snapshot !== null;
    }

    public function downloadPaymentReceipt(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking) && $booking->payment_receipt_snapshot !== null;
    }

    public function viewPaymentFiles(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }

    private function isRenter(User $user, Booking $booking): bool
    {
        return $booking->renter_user_id === $user->id;
    }

    private function isOwner(User $user, Booking $booking): bool
    {
        return $booking->item?->user_id === $user->id;
    }
}
