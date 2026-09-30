@php use App\Support\RaceUtils; @endphp
@php
    $entriesByDriver = $race->entries->keyBy('driver_id');
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Qualifying</h2>
        <x-badge class="border-[var(--amber)]/30 bg-[var(--amber)]/10 font-mono text-[10px] text-[var(--amber)]">
            {{ $poleMs !== null ? 'Pole ' . RaceUtils::formatLapTime($poleMs) : 'No times yet' }}
        </x-badge>
    </div>

    <div class="overflow-hidden rounded-xl border border-[var(--line)] bg-[var(--panel)]">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-[var(--line)] text-[10px] uppercase tracking-widest text-[var(--muted)]">
                    <th class="px-4 py-3">Pos</th>
                    <th class="px-4 py-3">Driver</th>
                    <th class="px-4 py-3">Kart</th>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Δ Pole</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--line)]/60">
                @foreach ($qualifyingList as $row)
@php
                        $entry = $entriesByDriver->get($row['driver_id']);
                        $valid = RaceUtils::isValidQualifyingTime($row);
                        $diff = $entry ? RaceUtils::poleDiffMs($row['qualifying_time_ms'], $poleMs) : null;
                    @endphp
                    <tr class="{{ $valid ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3 font-mono text-xs text-[var(--muted)]">{{ $loop->index + 1 }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="size-5 rounded-full text-center text-[8px] font-black leading-5"
                                      style="background-color: {{ $entry->driver->avatar_color }}; color: {{ $entry->driver->avatar_text_color }}">
                                    {{ mb_substr(($entry->driver->nickname ?? '') ?: ($entry->driver->profile?->full_name ?? '?'), 0, 2) }}
                                </span>
                                <span class="font-semibold">{{ $entry->driver->display_name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row['kart_number'] ?: '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs font-bold {{ $valid ? 'text-white' : 'text-[var(--muted)]' }}">
                            @if (! $valid && $row['qualifying_time_ms'] !== null)
                                Invalid
                            @else
                                {{ RaceUtils::formatLapTime($row['qualifying_time_ms']) }}
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-[var(--muted)]">
                            {{ $valid && $diff !== null ? '+'.RaceUtils::formatStopwatchMs($diff) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($race->status->value === 'qualifying')
                                <div class="flex justify-end gap-1.5">
                                    <form method="POST" action="{{ route('races.qualifying.record', $race) }}">
                                        @csrf
                                        <input type="hidden" name="driver_id" value="{{ $row['driver_id'] }}" />
                                        <input type="hidden" name="action" value="{{ $valid ? 'invalidate' : 'restore' }}" />
                                        <x-button type="submit" variant="ghost" size="sm" class="text-[var(--muted)]">
                                            {{ $valid ? 'Invalidate' : 'Restore' }}
                                        </x-button>
                                    </form>
                                    <form method="POST" action="{{ route('races.qualifying.record', $race) }}">
                                        @csrf
                                        <input type="hidden" name="driver_id" value="{{ $row['driver_id'] }}" />
                                        <input type="hidden" name="action" value="clear" />
                                        <x-button type="submit" variant="ghost" size="sm" class="text-[var(--muted)]" :disabled="$row['qualifying_time_ms'] === null">
                                            Clear
                                        </x-button>
                                    </form>
                                </div>
                            @else
                                <span class="text-[10px] uppercase tracking-widest text-[var(--muted)]">Locked</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($race->status->value === 'qualifying')
        <details class="rounded-xl border border-[var(--line)] bg-[var(--panel)] p-4">
            <summary class="cursor-pointer text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Record a lap time</summary>
            <div class="mt-3">
                @include('races.partials.qualifying-clock')
            </div>
        </details>
    @endif
</div>