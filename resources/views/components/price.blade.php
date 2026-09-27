@props(['amount', 'suffix' => '/ hari'])

<span {{ $attributes }}>
    Rp {{ number_format((float) $amount, (float) $amount === floor((float) $amount) ? 0 : 2, ',', '.') }}{{ $suffix ? ' '.$suffix : '' }}
</span>
