@php use App\Support\RaceUtils; @endphp
@php
    $order = $race->entries->sortBy(function ($e) {
        return $e->finish_position ?? 999;
    });
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <x-card class="p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Running order</h2>
                <x-badge class="border-[var(--green)]/30 bg-[var(--green)]/10 font-mono text-[10px] text-[var(--green)]">
                    {{ $race->entries->whereIn('status', ['finished', 'dnf', 'dns', 'retired', 'withdrawn'])->count() }}
                    classified
                </x-badge>
            </div>

            <ol class="mt-4 space-y-2">
                @forelse ($order as $entry)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-[var(--line)] bg-white/[.02] px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg border border-[var(--line)] bg-white/[.03] font-mono text-xs text-[var(--muted)]">
                                {{ $entry->finish_position ?? '—' }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $entry->driver->display_name }}</p>
                                <p class="text-[10px] uppercase tracking-widest text-[var(--muted)]">
                                    Kart <span class="font-mono">{{ $entry->kart_number ?: '—' }}</span>
                                    @if ($entry->penalty_total_seconds)
                                        · {{ RaceUtils::formatPenaltySeconds($entry->penalty_total_seconds) }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                            <x-result-status-badge :status="$entry->status" />
                            @unless ($entry->status?->value ?? $entry->getRawOriginal('status') === 'finished')
                                <form method="POST" action="{{ route('races.driver-status', ['race' => $race, 'driver' => $entry->driver_id]) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="finished" />
                                    <x-button type="submit" variant="secondary" size="sm" class="text-[var(--green)]">
                                        <x-lucide-flag class="size-3.5" />Finish
                                    </x-button>
                                </form>
                                <form method="POST" action="{{ route('races.driver-status', ['race' => $race, 'driver' => $entry->driver_id]) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="dnf" />
                                    <x-button type="submit" variant="ghost" size="sm" class="text-[var(--red-bright)]">DNF</x-button>
                                </form>
                                <form method="POST" action="{{ route('races.driver-status', ['race' => $race, 'driver' => $entry->driver_id]) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="dns" />
                                    <x-button type="submit" variant="ghost" size="sm" class="text-[var(--muted)]">DNS</x-button>
                                </form>
                            @endunless
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed border-[var(--line)] p-4 text-center text-xs text-[var(--muted)]">
                        The field is empty.
                    </li>
                @endforelse
            </ol>
        </x-card>
    </div>

    <div class="space-y-4">
        <x-card class="p-5">
            <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Issue penalty</h2>
            <form method="POST" action="{{ route('races.penalties.store', $race) }}" class="mt-4 space-y-3">
                @csrf
                <div class="space-y-1.5">
                    <label for="pen-driver" class="text-[10px] font-semibold uppercase tracking-widest text-[var(--muted)]">Driver</label>
                    <select id="pen-driver" name="driver_id" required class="w-full rounded-lg border border-[var(--line)] bg-[var(--panel-raised)] px-3 py-2 text-sm text-white outline-none focus:border-[var(--red)]">
                        @foreach ($race->entries as $entry)
                            <option value="{{ $entry->driver_id }}">{{ $entry->driver->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label for="pen-sec" class="text-[10px] font-semibold uppercase tracking-widest text-[var(--muted)]">Seconds</label>
                    <input id="pen-sec" type="number" name="seconds" min="1" max="99" value="5" required
                           class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2 font-mono text-sm text-white outline-none focus:border-[var(--red)]" />
                </div>
                <div class="space-y-1.5">
                    <label for="pen-reason" class="text-[10px] font-semibold uppercase tracking-widest text-[var(--muted)]">Reason</label>
                    <input id="pen-reason" type="text" name="reason" maxlength="255" placeholder="Jump start, track limits…" required
                           class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)]" />
                </div>
                <x-button type="submit" class="w-full"><x-lucide-alert-triangle class="size-3.5" />Issue penalty</x-button>
            </form>
        </x-card>

        <x-card class="p-5">
            <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Active penalties</h2>
            @if ($activePenalties->isEmpty())
                <p class="mt-3 text-xs text-[var(--muted)]">None.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($activePenalties as $penalty)
                        <li class="flex items-center justify-between gap-2 rounded-lg border border-[var(--line)] bg-white/[.02] px-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold">{{ $penalty->driver->display_name }}</p>
                                <p class="truncate text-[10px] text-[var(--muted)]">{{ $penalty->reason }}</p>
                            </div>
                            <span class="flex shrink-0 items-center gap-1.5">
                                <x-badge class="border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]">{{ RaceUtils::formatPenaltySeconds($penalty->seconds) }}</x-badge>
                                <form method="POST" action="{{ route('races.penalties.cancel', ['race' => $race, 'penalty' => $penalty]) }}">
                                    @csrf
                                    <x-button type="submit" variant="ghost" size="sm" class="text-[var(--muted)]" title="Cancel penalty"><x-lucide-x class="size-3.5" /></x-button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>