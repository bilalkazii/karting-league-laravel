@php use App\Support\RaceUtils; @endphp
@php
    $entriesByDriver = $race->entries->keyBy('driver_id');
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Grid</h2>
        <x-badge class="border-white/10 bg-white/5 text-[10px] text-[var(--muted)]">
            Sorted by adjusted qualifying time · {{ $gridRows ? count($gridRows) : 0 }} rows
        </x-badge>
    </div>

    <div class="overflow-hidden rounded-xl border border-[var(--line)] bg-[var(--panel)]">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-[var(--line)] text-[10px] uppercase tracking-widest text-[var(--muted)]">
                    <th class="px-4 py-3">Pos</th>
                    <th class="px-4 py-3">Q. position</th>
                    <th class="px-4 py-3">Driver</th>
                    <th class="px-4 py-3">Kart</th>
                    <th class="px-4 py-3">Qualifying</th>
                    <th class="px-4 py-3">Grid penalty</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--line)]/60">
                @foreach ($gridRows as $row)
@php
                        $entry = $entriesByDriver->get($row['driver_id']);
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <span class="grid size-7 place-items-center rounded-lg border {{ $row['grid_position'] === 1 ? 'border-[var(--red)]/40 bg-[var(--red)]/10 font-black text-[var(--red-bright)]' : 'border-[var(--line)] bg-white/[.03] font-mono text-xs' }}">
                                {{ $row['grid_position'] ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-[var(--muted)]">{{ $row['original_position'] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="grid size-5 place-items-center rounded-full text-[8px] font-black"
                                      style="background-color: {{ $entry->driver->avatar_color }}; color: {{ $entry->driver->avatar_text_color }}">
                                    {{ mb_substr(($entry->driver->nickname ?? '') ?: ($entry->driver->profile?->full_name ?? '?'), 0, 2) }}
                                </span>
                                <span class="font-semibold">{{ $entry->driver->display_name }}</span>
                                @if ($row['ready'])
                                    <x-badge class="border-[var(--green)]/30 bg-[var(--green)]/10 text-[var(--green)]">Ready</x-badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('races.entries.update', ['race' => $race, 'driver' => $row['driver_id']]) }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="number" name="kart_number" min="1" max="99" value="{{ $row['kart_number'] }}"
                                       class="w-16 rounded-lg border border-[var(--line)] bg-white/[.03] px-2 py-1.5 text-center font-mono text-xs text-white outline-none focus:border-[var(--red)]" />
                                <x-button type="submit" variant="ghost" size="sm" class="text-[var(--muted)]">Set</x-button>
                            </form>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ RaceUtils::formatLapTime($row['qualifying_time_ms']) }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('races.entries.update', ['race' => $race, 'driver' => $row['driver_id']]) }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="hidden" name="kart_number" value="{{ $row['kart_number'] }}" />
                                <input type="text" name="grid_penalty" value="{{ $row['grid_penalty_seconds'] }}"
                                       inputmode="numeric" pattern="[0-9]+"
                                       class="w-16 rounded-lg border border-[var(--line)] bg-white/[.03] px-2 py-1.5 text-center font-mono text-xs text-white outline-none focus:border-[var(--red)]" />
                                <span class="text-[10px] text-[var(--muted)]">s</span>
                                <x-button type="submit" variant="ghost" size="sm" class="text-[var(--muted)]">Apply</x-button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>