@props(['status'])

@php
    $value = $status instanceof \App\Enums\SeasonStatus ? $status->value : $status;
    $map = [
        'draft' => ['Draft', 'border-white/10 bg-white/5 text-[var(--muted)]'],
        'active' => ['Active', 'border-[var(--green)]/30 bg-[var(--green)]/10 text-[var(--green)]'],
        'completed' => ['Completed', 'border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]'],
        'archived' => ['Archived', 'border-white/10 bg-white/5 text-[var(--muted)] line-through'],
    ];
    [$label, $classes] = $map[$value] ?? [$value, 'border-white/10 bg-white/5 text-[var(--muted)]'];
@endphp

<x-badge :class="$classes">{{ $label }}</x-badge>