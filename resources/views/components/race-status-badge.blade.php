@props(['status' => 'draft'])

@php
    $value = $status instanceof \App\Enums\RaceStatus ? $status->value : $status;

    $styles = [
        'draft' => 'border-white/10 bg-white/5 text-[var(--muted)]',
        'lobby' => 'border-[var(--amber)]/30 bg-[var(--amber)]/10 text-[var(--amber)]',
        'qualifying' => 'border-[var(--amber)]/50 bg-[var(--amber)]/15 text-[var(--amber)]',
        'grid' => 'border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]',
        'racing' => 'border-[var(--red)]/50 bg-[var(--red)]/15 text-[var(--red-bright)] animate-pulse',
        'completed' => 'border-[var(--green)]/30 bg-[var(--green)]/10 text-[var(--green)]',
        'cancelled' => 'border-white/10 bg-white/5 text-[var(--muted)] line-through',
    ];
    $labels = [
        'draft' => 'Draft', 'lobby' => 'Lobby', 'qualifying' => 'Qualifying',
        'grid' => 'Grid', 'racing' => 'Racing', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
    ];
@endphp

<x-badge :class="$styles[$value] ?? 'border-white/10 bg-white/5 text-[var(--muted)]'">{{ $labels[$value] ?? ucfirst($value) }}</x-badge>