@php use App\Support\RaceUtils; @endphp
<div class="grid gap-4 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <x-card class="p-5">
            <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Overview</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Group</dt>
                    <dd class="mt-1 font-semibold">{{ $race->group->name }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Organizer</dt>
                    <dd class="mt-1 font-semibold">{{ $race->organizer?->display_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Format</dt>
                    <dd class="mt-1 font-semibold">{{ ucfirst($race->format->value) }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Qualifying laps</dt>
                    <dd class="mt-1 font-semibold">{{ $race->qualifying_lap_count }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Field size</dt>
                    <dd class="mt-1 font-semibold">{{ $race->entries->count() }} drivers</dd>
                </div>
                @if ($season)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Season</dt>
                        <dd class="mt-1 font-semibold">{{ $season->name }}
                            <span class="text-[var(--muted)]">· R{{ $season->pivot->round_number ?? '?' }}</span>
                        </dd>
                    </div>
                @endif
            </dl>
        </x-card>

        <x-card class="p-5">
            <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Session log</h2>
            <div class="mt-3">
                <x-race-event-list :events="$events" :limit="10" />
            </div>
        </x-card>
    </div>

    <div class="space-y-4">
        @if ($race->rules)
            <x-card class="p-5">
                <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Rules</h2>
                <p class="mt-3 whitespace-pre-wrap text-xs leading-relaxed text-[var(--muted)]">{{ $race->rules }}</p>
            </x-card>
        @endif

        <x-card class="p-5">
            <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Active penalties</h2>
            @if ($activePenalties->isEmpty())
                <p class="mt-3 text-xs text-[var(--muted)]">No active penalties.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($activePenalties as $penalty)
                        <li class="flex items-center justify-between gap-3 text-xs">
                            <span class="truncate font-semibold">{{ $penalty->driver?->display_name ?? '—' }}</span>
                            <span class="flex shrink-0 items-center gap-1.5">
                                <x-badge class="border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]">{{ RaceUtils::formatPenaltySeconds($penalty->seconds) }}</x-badge>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @if ($currentTab === 'overview' && ($currentDriver ?? null))
            <x-card class="p-5">
                <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Your entry</h2>
                @if ($currentEntry)
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <x-badge class="border-white/10 bg-white/5 font-mono text-xs font-bold">{{ $currentEntry->kart_number ?: '—' }}</x-badge>
                            <div>
                                <p class="text-xs font-semibold">{{ $currentDriver->display_name }}</p>
                                <p class="text-[10px] text-[var(--muted)]">{{ ucfirst($currentEntry->status->value) }}</p>
                            </div>
                        </div>
                        <x-race-status-badge :status="$race->status" />
                    </div>
                @else
                    <p class="mt-3 text-xs text-[var(--muted)]">You are not in the field for this race.</p>
                @endif
            </x-card>
        @endif
    </div>
</div>