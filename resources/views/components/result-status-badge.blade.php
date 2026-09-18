@props(['status' => 'invited'])

@php
    $value = $status instanceof \App\Enums\RaceDriverStatus ? $status->value : $status;
    $styles = [
        'finished' => 'border-[var(--green)]/30 bg-[var(--green)]/10 text-[var(--green)]',
        'dnf' => 'border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]',
        'dns' => 'border-white/10 bg-white/5 text-[var(--muted)]',
        'retired' => 'border-[var(--amber)]/30 bg-[var(--amber)]/10 text-[var(--amber)]',
        'withdrawn' => 'border-white/10 bg-white/5 text-[var(--muted)] line-through',
        'racing' => 'border-[var(--red)]/50 bg-[var(--red)]/15 text-[var(--red-bright)] animate-pulse',
    ];
    $labels = [
        'finished' => 'Finished', 'dnf' => 'DNF', 'dns' => 'DNS', 'retired' => 'Retired',
        'withdrawn' => 'Withdrawn', 'racing' => 'In progress', 'ready' => 'Ready',
        'confirmed' => 'Confirmed', 'invited' => 'Invited', 'declined' => 'Declined',
    ];
@endphp

<x-badge :class="$styles[$value] ?? 'border-white/10 bg-white/5 text-[var(--muted)]'">{{ $labels[$value] ?? ucfirst($value) }}</x-badge>