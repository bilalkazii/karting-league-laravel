@props(['rating' => 0])

@php
    if ($rating >= 1800) {
        $label = 'Elite';
        $color = 'border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]';
    } elseif ($rating >= 1700) {
        $label = 'Expert';
        $color = 'border-[var(--amber)]/30 bg-[var(--amber)]/10 text-[var(--amber)]';
    } elseif ($rating >= 1600) {
        $label = 'Advanced';
        $color = 'border-white/15 bg-white/5 text-white';
    } elseif ($rating >= 1500) {
        $label = 'Intermediate';
        $color = 'border-[var(--green)]/30 bg-[var(--green)]/10 text-[var(--green)]';
    } else {
        $label = 'Rookie';
        $color = 'border-white/10 bg-white/5 text-[var(--muted)]';
    }
@endphp

<x-badge {{ $attributes->merge(['class' => 'border '.$color]) }}>{{ $rating }} {{ $label }}</x-badge>