@props(['tip' => 'slobodno'])

@php
    // Single source of truth for occupancy colors — mirrors the Flux badge tones
    // (green = Trajna, amber = Uslovna) used across owner, attendant and manager views.
    $klase = match ($tip) {
        'trajna' => 'bg-green-400/40 dark:bg-green-400/40 border border-green-500/20',
        'uslovna' => 'bg-amber-400/25 dark:bg-amber-400/40 border border-amber-500/25',
        default => 'border border-zinc-300 dark:border-zinc-600',
    };
@endphp

<span {{ $attributes->merge(['class' => 'size-3 rounded-[3px] '.$klase]) }}></span>
