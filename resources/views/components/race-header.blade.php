@props(['race', 'driverCount' => 0])

@php
    $formatLabel = match (($race->format instanceof \App\Enums\RaceFormat ? $race->format->value : $race->format)) {
        'feature' => 'Feature', 'custom' => 'Custom', default => 'Sprint',
    };
@endphp

<div class="overflow-hidden rounded-xl border border-[var(--line)] bg-[var(--panel)]">
    <div class="relative h-32 overflow-hidden bg-[#16100e] sm:h-36">
        <div class="absolute inset-0 carbon"></div>
        <div class="absolute -right-14 -top-24 size-64 rounded-full bg-[var(--red)]/15 blur-3xl"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[var(--panel)] to-transparent"></div>
    </div>
    <div class="relative -mt-12 px-5 pb-5 sm:px-7">
        <div class="flex items-center gap-4">
            <span class="grid size-16 shrink-0 place-items-center rounded-xl border border-[var(--red)]/30 bg-[var(--red)]/10 text-lg font-black text-[var(--red-bright)] ring-4 ring-[var(--panel)]">{{ mb_strtoupper(mb_substr($race->name, 0, 1)) }}</span>
            <div class="min-w-0 pb-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-xl font-black tracking-[-.04em] sm:text-2xl">{{ $race->name }}</h1>
                    <x-race-status-badge :status="$race->status" />
                </div>
                <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[var(--muted)]">
                    <span class="flex items-center gap-1.5"><x-lucide-map-pin class="size-3 text-[var(--red-bright)]" />{{ $race->venue_name }}</span>
                    <span class="flex items-center gap-1.5"><x-lucide-calendar-days class="size-3 text-[var(--red-bright)]" />{{ $race->date->format('d M Y') }}</span>
                    <span class="flex items-center gap-1.5"><x-lucide-clock class="size-3 text-[var(--red-bright)]" />{{ $race->start_time }}</span>
                    <span class="flex items-center gap-1.5"><x-lucide-users class="size-3 text-[var(--red-bright)]" />{{ $driverCount }} {{ $driverCount === 1 ? 'driver' : 'drivers' }}</span>
                    <x-badge class="border-white/10 bg-white/5 text-[var(--muted)]">{{ $formatLabel }}</x-badge>
                </div>
            </div>
        </div>
        @if ($race->rules)
            <p class="mt-4 max-w-2xl text-xs leading-relaxed text-[var(--muted)]">{{ $race->rules }}</p>
        @endif
    </div>
</div>