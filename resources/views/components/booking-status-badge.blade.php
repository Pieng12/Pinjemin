@props(['status', 'booking' => null])

@php($bookingStatus = $booking ? $booking->effectiveStatus() : ($status instanceof \App\BookingStatus ? $status : \App\BookingStatus::from($status)))

<span {{ $attributes->merge(['class' => 'badge '.$bookingStatus->badgeClasses()]) }}>
    {{ $bookingStatus->label() }}
</span>
@if($booking?->returnNotice())
    <span class="mt-1 block text-xs font-semibold {{ $booking->end_date->toDateString() < now(\App\Models\Booking::RentalTimezone)->toDateString() ? 'text-red-600' : 'text-amber-700' }}">{{ $booking->returnNotice() }}</span>
@endif
