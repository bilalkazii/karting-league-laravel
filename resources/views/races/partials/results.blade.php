@php use App\Support\RaceUtils; @endphp
@php
    $entriesByDriver = $race->entries->keyBy('driver_id');
    $finished = collect($results)->filter(fn ($r) => $r['status'] === 'finished')->values();
    $winner = $finished->first(fn ($r) => $r['finish_position'] === 1);
    $podium = $finished->filter(fn ($r) => in_array($r['finish_position'], [1, 2, 3], true))->sortBy('finish_position')->values();
@endphp

<div class="space-y-6">
    @if ($season)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--line)] bg-[var(--panel)] px-5 py-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Part of a season</p>
                <p class="text-sm font-black">{{ $season->name }} · Round {{ $season->pivot->round_number ?? '?' }}</p>
            </div>
            <a href="{{ route('championship.show', $season) }}">
                <x-button variant="secondary" size="sm"><x-lucide-trophy class="size-3.5" />View championship</x-button>
            </a>
        </div>
    @endif

    @if ($winner)
        @php $w = $entriesByDriver->get($winner['driver_id']); @endphp
        <div class="relative overflow-hidden rounded-2xl border border-[var(--line)] bg-[#12191c] p-6 text-center">
            <div class="absolute -top-20 left-1/2 size-64 -translate-x-1/2 rounded-full bg-[var(--red)]/20 blur-3xl"></div>
            <p class="text-[10px] font-black uppercase tracking-[.3em] text-[var(--red-bright)]">Winner</p>
            <div class="relative mt-4 flex justify-center">
                <div class="flex items-center gap-3">
                    <span class="grid size-16 place-items-center rounded-full text-xl font-black"
                          style="background-color: {{ $w->driver->avatar_color }}; color: {{ $w->driver->avatar_text_color }}">
                        {{ mb_substr(($w->driver->nickname ?? '') ?: ($w->driver->profile?->full_name ?? '?'), 0, 2) }}
                    </span>
                    <div class="text-left">
                        <p class="text-2xl font-black tracking-[-.04em]">{{ $w->driver->display_name }}</p>
                        <p class="mt-1 text-xs text-[var(--muted)]">
                            {{ $winner['qualifying_time_ms'] !== null ? 'Qualified ' . RaceUtils::formatLapTime($winner['qualifying_time_ms']) : 'No qualifying time' }}
                            @if ($winner['penalty_total_seconds'])
                                · {{ RaceUtils::formatPenaltySeconds($winner['penalty_total_seconds']) }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($podium->count() >= 2)
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ([2, 1, 3] as $slot)
                @php $p = $podium->firstWhere('finish_position', $slot); @endphp
                @if ($p)
                    @php $d = $entriesByDriver->get($p['driver_id']); @endphp
                    <div @class([
                        'flex items-center gap-3 rounded-xl border p-4',
                        'border-[var(--red)]/40 bg-[var(--red)]/[.06]' => $slot === 1,
                        'border-[var(--line)] bg-[var(--panel)]' => $slot !== 1,
                        'sm:order-1' => $slot === 2, 'sm:order-2' => $slot === 1, 'sm:order-3' => $slot === 3,
                    ])>
                        <span class="grid size-9 place-items-center rounded-lg border border-white/10 bg-white/5 font-mono text-sm font-black text-[var(--red-bright)]">{{ $slot }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $d->driver->display_name }}</p>
                            <p class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Kart {{ $d->kart_number ?: '—' }}</p>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <x-card class="overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5">
            <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Results</h2>
            <div class="flex items-center gap-2">
                <x-race-status-badge :status="$race->status" />
                @if ($season)
                    <x-badge class="border-white/10 bg-white/5 text-[10px] text-[var(--muted)]">Season points applied on completion</x-badge>
                @endif
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[var(--line)] text-[10px] uppercase tracking-widest text-[var(--muted)]">
                        <th class="px-5 py-3">Pos</th>
                        <th class="px-5 py-3">Driver</th>
                        <th class="px-5 py-3">Kart</th>
                        <th class="px-5 py-3">Qualifying</th>
                        <th class="px-5 py-3">Penalties</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--line)]/60">
                    @foreach ($results as $row)
@php
                            $d = $entriesByDriver->get($row['driver_id']);
                            $isWinner = $row['status'] === 'finished' && $row['finish_position'] === 1;
                        @endphp
                        <tr class="{{ $isWinner ? 'bg-[var(--red)]/[.04]' : '' }}">
                            <td class="px-5 py-3">
                                <span class="font-mono text-xs font-bold {{ $isWinner ? 'text-[var(--red-bright)]' : 'text-white' }}">
                                    {{ $row['finish_position'] ?? '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="grid size-5 place-items-center rounded-full text-[8px] font-black"
                                          style="background-color: {{ $d->driver->avatar_color }}; color: {{ $d->driver->avatar_text_color }}">
                                        {{ mb_substr(($d->driver->nickname ?? '') ?: ($d->driver->profile?->full_name ?? '?'), 0, 2) }}
                                    </span>
                                    <span class="font-semibold">{{ $d->driver->display_name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs text-[var(--muted)]">{{ $d->kart_number ?: '—' }}</td>
                            <td class="px-5 py-3 font-mono text-xs">{{ RaceUtils::formatLapTime($row['qualifying_time_ms']) }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-[var(--red-bright)]">
                                {{ $row['penalty_total_seconds'] ? RaceUtils::formatPenaltySeconds($row['penalty_total_seconds']) : '—' }}
                            </td>
                            <td class="px-5 py-3"><x-result-status-badge :status="$row['status']" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</div>