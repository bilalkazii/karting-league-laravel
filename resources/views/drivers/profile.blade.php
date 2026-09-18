<x-app-layout>
    <div class="space-y-8">
        <div class="flex flex-col items-start justify-between gap-5 sm:flex-row sm:items-center">
            <div class="flex items-center gap-4">
                <span class="grid size-14 place-items-center rounded-full text-lg font-black" style="background-color: {{ $driver->avatar_color }}; color: {{ $driver->avatar_text_color }}">{{ $driver->nickname }}</span>
                <div>
                    <h1 class="text-3xl font-black tracking-[-.05em]">{{ $driver->profile->full_name }}</h1>
                    <p class="mt-1 text-sm text-[var(--muted)]">{{ $driver->nickname }} · KART {{ str_pad((string) $driver->racing_number, 2, '0', STR_PAD_LEFT) }}</p>
                </div>
            </div>
            <div class="rounded-lg border border-[var(--line)] bg-[var(--panel)] px-4 py-2 text-center">
                <p class="text-[10px] uppercase tracking-wider text-[var(--muted)]">Rating</p>
                <p class="text-xl font-black">{{ $driver->rating }}</p>
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-4">
            <x-stat-card label="Starts" :value="(string) $stats['starts']" detail="races entered">
                <x-lucide-flag class="size-[18px]" />
            </x-stat-card>
            <x-stat-card label="Wins" :value="(string) $stats['wins']" detail="first place">
                <x-lucide-trophy class="size-[18px]" />
            </x-stat-card>
            <x-stat-card label="Podiums" :value="(string) $stats['podiums']" detail="top 3">
                <x-lucide-medal class="size-[18px]" />
            </x-stat-card>
            <x-stat-card label="Poles" :value="(string) $stats['poles']" detail="from qualifying">
                <x-lucide-gauge class="size-[18px]" />
            </x-stat-card>
        </div>

        <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)]">
            <div class="border-b border-[var(--line)] px-5 py-4">
                <h3 class="text-sm font-bold">Recent results</h3>
            </div>
            @if ($recentEntries->isEmpty())
                <p class="px-5 py-6 text-sm text-[var(--muted)]">No results yet.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[var(--line)] text-[10px] uppercase tracking-wider text-[var(--muted)]">
                            <th class="px-5 py-3">Race</th>
                            <th class="px-5 py-3">Grid</th>
                            <th class="px-5 py-3">Finished</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentEntries as $entry)
                            <tr class="border-b border-[var(--line)]/50 last:border-0">
                                <td class="px-5 py-3 font-medium">{{ $entry->race->name }}</td>
                                <td class="px-5 py-3 text-[var(--muted)]">{{ $entry->grid_position ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    {{ $entry->finish_position ?? '—' }}
                                    @if (($entry->penalty_total_seconds ?? 0) > 0)
                                        <span class="ml-2 text-xs text-[var(--amber)]">+{{ $entry->penalty_total_seconds }}s</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>