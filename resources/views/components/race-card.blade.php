@props(['race', 'driverCount' => 0])

@php
    $cancelled = ($race->status instanceof \App\Enums\RaceStatus ? $race->status->value : $race->status) === 'cancelled';
    $href = route('races.show', $race);
    $formatLabel = match (($race->format instanceof \App\Enums\RaceFormat ? $race->format->value : $race->format)) {
        'feature' => 'Feature', 'custom' => 'Custom', default => 'Sprint',
    };
@endphp

<div class="group relative block overflow-hidden rounded-xl border border-[var(--line)] bg-[var(--panel)] p-5 transition hover:border-[var(--red)]/40 hover:bg-[#11171b] {{ $cancelled ? 'opacity-60' : '' }}">
    @if (! $cancelled)
        <a href="{{ $href }}" class="absolute inset-0 z-10" aria-label="Open {{ $race->name }}"></a>
    @endif
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <x-badge class="border-white/10 bg-white/5 text-lg font-bold text-white">{{ mb_strtoupper(mb_substr($race->name, 0, 1)) }}</x-badge>
            <div>
                <p class="text-sm font-bold">{{ $race->name }}</p>
                <p class="text-[10px] uppercase tracking-widest text-[var(--muted)]">{{ $formatLabel }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-race-status-badge :status="$race->status" />
            @if (! $cancelled)
                <x-lucide-chevron-right class="size-[15px] text-[var(--muted)] transition group-hover:text-[var(--red-bright)]" />
            @endif
        </div>
    </div>
    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-[11px] text-[var(--muted)]">
        <span class="flex items-center gap-1.5"><x-lucide-map-pin class="size-3 text-[var(--red-bright)]" />{{ $race->venue_name }}</span>
        <span class="flex items-center gap-1.5"><x-lucide-calendar-days class="size-3 text-[var(--red-bright)]" />{{ $race->date->format('d M Y') }} at {{ $race->start_time }}</span>
        <span class="flex items-center gap-1.5"><x-lucide-users class="size-3 text-[var(--red-bright)]" />{{ $driverCount }} {{ $driverCount === 1 ? 'driver' : 'drivers' }}</span>
    </div>
</div>