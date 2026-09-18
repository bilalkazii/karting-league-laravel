@props(['availability' => 'available'])

@php
    $label = match ($availability) {
        'available' => 'Available',
        'maybe' => 'Maybe',
        default => 'Not available',
    };
    $color = match ($availability) {
        'available' => 'text-[var(--green)]',
        'maybe' => 'text-[var(--amber)]',
        default => 'text-[var(--muted)]',
    };
@endphp

<span class="inline-flex items-center gap-1.5 text-xs">
    <span class="size-1.5 rounded-full {{ $color }}"></span>
    <span class="{{ $color }}">{{ $label }}</span>
</span>