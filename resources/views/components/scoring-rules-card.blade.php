@props(['season'])

@php
    $scoring = $season->scoring;
    $points = $season->scoringPoints->sortBy('position')->values();
    $mode = $scoring->mode instanceof \App\Enums\ScoringMode ? $scoring->mode->value : $scoring->mode;
@endphp

<x-card class="p-5">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h3 class="flex items-center gap-2 text-sm font-bold">
            <x-lucide-trophy class="size-4 text-[var(--red-bright)]" />Scoring rules
        </h3>
        <x-badge :class="$mode === 'custom' ? 'border-[var(--amber)]/30 bg-[var(--amber)]/10 text-[var(--amber)]' : 'border-white/10 bg-white/5 text-[var(--muted)]'">
            {{ $mode === 'custom' ? 'Custom' : 'Automatic' }}
        </x-badge>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <ul class="space-y-2 text-xs text-[var(--muted)]">
                <li class="flex items-center justify-between gap-2">
                    <span>Pole position</span>
                    <span class="font-mono text-white">{{ $scoring->pole_position_points }} pts</span>
                </li>
                <li class="flex items-center justify-between gap-2">
                    <span>Fastest lap</span>
                    <span class="font-mono text-white">{{ $scoring->fastest_lap_points }} pts</span>
                </li>
                <li class="flex items-center justify-between gap-2">
                    <span>Participation</span>
                    <span class="font-mono text-white">{{ $scoring->participation_points }} pts</span>
                </li>
                <li class="flex items-center justify-between gap-2">
                    <span>DNF</span>
                    <span class="font-mono text-white">{{ $scoring->dnf_points }} pts</span>
                </li>
                <li class="flex items-center justify-between gap-2">
                    <span>DNS</span>
                    <span class="font-mono text-white">{{ $scoring->dns_points }} pts</span>
                </li>
                <li class="flex items-center justify-between gap-2">
                    <span>Penalty adjustments</span>
                    <span class="font-mono text-white">{{ $scoring->penalty_adjustment_enabled ? 'On' : 'Off' }}</span>
                </li>
            </ul>
        </div>

        @if ($points->isNotEmpty())
            <div>
                <p class="mb-2 text-[10px] font-bold uppercase tracking-[.18em] text-[var(--muted)]">Points per position</p>
                <div class="grid grid-cols-4 gap-2">
                    @foreach ($points as $point)
                        <div class="rounded-lg border border-[var(--line)] bg-white/[.03] px-2 py-2 text-center">
                            <p class="text-[9px] text-[var(--muted)]">P{{ $point->position }}</p>
                            <p class="font-mono text-sm font-bold text-white">{{ $point->points }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-card>