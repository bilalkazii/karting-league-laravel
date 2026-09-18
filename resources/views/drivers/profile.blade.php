<x-app-layout>
    <div class="space-y-8 pb-10">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end">
            <div class="flex items-center gap-4">
                <x-driver-avatar
                    :color="$driver->avatar_color"
                    :text-color="$driver->avatar_text_color"
                    :initials="$driver->nickname ?? '?'"
                    size="xl"
                />
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black tracking-[-.04em] sm:text-3xl">{{ $driver->profile?->full_name ?? $driver->nickname }}</h1>
                        @if ($driver->racing_number)
                            <x-badge class="font-mono">#{{ str_pad((string) $driver->racing_number, 2, '0', STR_PAD_LEFT) }}</x-badge>
                        @endif
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-[var(--muted)]">
                        @if ($driver->nickname)
                            <span>{{ $driver->nickname }}</span>
                        @endif
                        <x-rating-badge :rating="$driver->rating" />
                        @if ($isOwnProfile)
                            <x-availability-badge :availability="$availabilityByGroup->first() ?? 'available'" />
                        @endif
                    </div>
                </div>
            </div>
            @if ($isOwnProfile)
                <div class="sm:ml-auto">
                    <a href="{{ route('drivers.edit', $driver) }}">
                        <x-button variant="secondary">
                            <x-lucide-pencil class="size-3.5" />Edit profile
                        </x-button>
                    </a>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <x-driver-stat-card label="Starts" :value="(string) $stats['starts']">
                <x-lucide-flag class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="Wins" :value="(string) $stats['wins']" accent :detail="$stats['starts'] > 0 ? round(($stats['wins'] / $stats['starts']) * 100).'% win rate' : ''">
                <x-lucide-trophy class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="Podiums" :value="(string) $stats['podiums']" :detail="$stats['starts'] > 0 ? round(($stats['podiums'] / $stats['starts']) * 100).'% podium rate' : ''">
                <x-lucide-medal class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="Poles" :value="(string) $stats['poles']">
                <x-lucide-target class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="DNFs" :value="(string) $stats['dnfs']" :detail="$stats['starts'] > 0 ? round(($stats['dnfs'] / $stats['starts']) * 100).'% DNF rate' : ''">
                <x-lucide-gauge class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="Best qualifying" :value="$stats['bestQualifyingMs'] ? number_format($stats['bestQualifyingMs'] / 1000, 3).'s' : '—'" accent>
                <x-lucide-timer class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="Avg qualifying" :value="$stats['avgQualifyingMs'] ? number_format($stats['avgQualifyingMs'] / 1000, 3).'s' : '—'">
                <x-lucide-bar-chart-3 class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="Clean races" :value="(string) $stats['clean']" :detail="($stats['starts'] > 0 && $stats['clean'] > 0) ? round(($stats['clean'] / $stats['starts']) * 100).'% clean rate' : ''">
                <x-lucide-flame class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="Penalties" :value="(string) $stats['penaltyCalls']">
                <x-lucide-shield-alert class="size-4" />
            </x-driver-stat-card>
            <x-driver-stat-card label="Penalty time" :value="$stats['penaltySeconds'] > 0 ? $stats['penaltySeconds'].'s' : '0s'" :detail="$stats['penaltyCalls'] > 0 ? $stats['penaltyCalls'].' calls' : ''">
                <x-lucide-shield class="size-4" />
            </x-driver-stat-card>
        </div>

        @if ($recentForm->isNotEmpty())
            <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)] p-5">
                <p class="text-xs font-semibold text-[var(--muted)]">Recent form</p>
                <div class="mt-3 flex items-center gap-1.5">
                    @foreach ($recentForm as $form)
                        @php
                            $see = match (true) {
                                $form['finish'] === 1 => ['P1', 'bg-[var(--red)] text-white'],
                                in_array($form['finish'], [2, 3], true) => ["P{$form['finish']}", 'border-[var(--amber)]/40 bg-[var(--amber)]/10 text-[var(--amber)]'],
                                $form['status'] === 'dnf' => ['DNF', 'border-white/15 bg-white/5 text-[var(--muted)]'],
                                $form['status'] === 'dns' => ['DNS', 'border-white/15 bg-white/5 text-[var(--muted)]'],
                                $form['status'] === 'retired' => ['RET', 'border-white/15 bg-white/5 text-[var(--muted)]'],
                                default => ['—', 'border-white/10 bg-white/5 text-[var(--muted)]'],
                            };
                        @endphp
                        <span class="grid size-9 place-items-center rounded-lg border text-[10px] font-bold {{ $see[1] }}" title="{{ $see[0] }}">{{ $see[0] }}</span>
                    @endforeach
                    <span class="ml-2 grow text-[10px] text-[var(--muted)]">← oldest</span>
                </div>
            </div>
        @endif

        <div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
            <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)]">
                <div class="border-b border-[var(--line)] px-5 py-4">
                    <h2 class="text-sm font-bold">Recent results</h2>
                </div>
                @if ($recentEntries->isEmpty())
                    <p class="px-5 py-6 text-sm text-[var(--muted)]">No results yet.</p>
                @else
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[var(--line)] text-[10px] uppercase tracking-wider text-[var(--muted)]">
                                <th class="px-5 py-3">Race</th>
                                <th class="px-5 py-3">Venue</th>
                                <th class="px-5 py-3">Grid</th>
                                <th class="px-5 py-3">Finished</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentEntries as $entry)
                                <tr class="border-b border-[var(--line)]/50 last:border-0">
                                    <td class="px-5 py-3">
                                        <p class="font-medium">{{ $entry->race->name }}</p>
                                        <p class="text-[10px] text-[var(--muted)]">{{ $entry->race->date?->format('d M Y') }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-[var(--muted)]">{{ $entry->race->venue_name }}</td>
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

            <div class="space-y-5">
                <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)]">
                    <div class="border-b border-[var(--line)] px-5 py-4">
                        <h2 class="text-sm font-bold">Groups &amp; teams</h2>
                    </div>
                    @if ($visibleGroups->isEmpty() && $visibleTeams->isEmpty())
                        <p class="px-5 py-6 text-sm text-[var(--muted)]">Nothing to show here yet.</p>
                    @else
                        <div class="space-y-3 p-5">
                            @foreach ($visibleGroups as $group)
                                <a href="{{ route('groups.show', $group) }}" class="flex items-center justify-between gap-3 rounded-lg border border-[var(--line)] bg-white/[.02] px-3 py-2.5 transition hover:bg-white/5">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-lg text-[10px] font-black" style="background-color: {{ $group->logo_color }}; color: {{ $group->logo_text_color }}">{{ $group->logo_initials }}</span>
                                        <span class="truncate text-sm font-semibold">{{ $group->name }}</span>
                                    </div>
                                    <x-availability-badge :availability="$availabilityByGroup[$group->id] ?? 'available'" />
                                </a>
                            @endforeach
                            @foreach ($visibleTeams as $team)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-[var(--line)] bg-white/[.02] px-3 py-2.5">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-lg text-[10px] font-black" style="background-color: {{ $team->logo_color }}; color: {{ $team->logo_text_color }}">{{ $team->logo_initials }}</span>
                                        <span class="truncate text-sm font-semibold">{{ $team->name }}</span>
                                    </div>
                                    <span class="rounded bg-white/5 px-2 py-0.5 text-[10px] text-[var(--muted)]">{{ $team->group?->name }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($championships->isNotEmpty())
                    <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)]">
                        <div class="border-b border-[var(--line)] px-5 py-4">
                            <h2 class="text-sm font-bold">Championships</h2>
                        </div>
                        <div class="space-y-3 p-5">
                            @foreach ($championships as $championship)
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold">{{ $championship['name'] }}</p>
                                        <p class="text-[10px] text-[var(--muted)]">{{ $championship['rounds'] }} {{ $championship['rounds'] === 1 ? 'round' : 'rounds' }} entered</p>
                                    </div>
                                    <x-badge :class="$championship['statusLabel'] === 'Active' ? 'border-[var(--green)]/30 bg-[var(--green)]/10 text-[var(--green)]' : 'border-white/10 bg-white/5 text-[var(--muted)]'">{{ $championship['statusLabel'] }}</x-badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>