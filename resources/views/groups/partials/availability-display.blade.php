@props(['availability' => 'available'])

@php
    if ($availability === 'available') {
        $label = 'Available';
        $color = 'text-[var(--green)]';
    } elseif ($availability === 'maybe') {
        $label = 'Maybe';
        $color = 'text-[var(--amber)]';
    } else {
        $label = 'Not available';
        $color = 'text-[var(--muted)]';
    }
@endphp

<x-card>
    <div class="border-b border-[var(--line)] px-5 py-4">
        <h3 class="flex items-center gap-1.5 text-sm font-bold">
            <x-lucide-calendar-days class="size-3.5 text-[var(--red-bright)]" />Your weekend availability
        </h3>
    </div>
    <div class="p-5">
        <p class="mb-3 text-xs text-[var(--muted)]">Set your status for the next race. This updates local state only.</p>
        <x-availability-selector :value="$availability" />
        <p class="mt-3 text-xs">Status: <span class="{{ $color }}">{{ $label }}</span></p>
    </div>
</x-card>