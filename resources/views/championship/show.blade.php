<x-app-layout>
    <div class="space-y-8">
        @if (session('status'))
            <div class="flex items-center gap-2 rounded-lg border border-[var(--green)]/30 bg-[var(--green)]/10 px-4 py-3 text-sm text-[var(--green)]" role="status">
                <x-lucide-check-circle class="size-4" />{{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-[var(--line)] bg-[var(--panel)]">
            <div class="relative h-32 overflow-hidden bg-[#16100e] sm:h-36">
                <div class="absolute inset-0 carbon"></div>
                <div class="absolute -right-14 -top-24 size-64 rounded-full bg-[var(--red)]/15 blur-3xl"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-[var(--panel)] to-transparent"></div>
            </div>

            <div class="relative -mt-12 px-5 pb-5 sm:px-7">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="grid size-16 shrink-0 place-items-center rounded-xl border border-[var(--red)]/30 bg-[var(--red)]/10 text-lg font-black text-[var(--red-bright)] ring-4 ring-[var(--panel)]">{{ mb_strtoupper(mb_substr($season->name, 0, 1)) }}</span>
                        <div class="min-w-0 pb-0.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="truncate text-xl font-black tracking-[-.04em] sm:text-2xl">{{ $season->name }}</h1>
                                <x-season-status-badge :status="$season->status" />
                            </div>
                            <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[var(--muted)]">
                                <span class="flex items-center gap-1.5">
                                    <x-lucide-users class="size-3 text-[var(--red-bright)]" />{{ $season->group->name }}
                                </span>
                                @if ($season->start_date && $season->end_date)
                                    <span class="flex items-center gap-1.5">
                                        <x-lucide-calendar-days class="size-3 text-[var(--red-bright)]" />{{ $season->start_date->format('d M Y') }} → {{ $season->end_date->format('d M Y') }}
                                    </span>
                                @endif
                                <span class="flex items-center gap-1.5">
                                    <span class="font-mono font-semibold text-white">{{ $completedRaces }}</span>
                                    of {{ $season->races->count() }} races run
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($canManage)
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('seasons.edit', $season) }}">
                    <x-button type="button" variant="secondary" size="sm">
                        <x-lucide-pencil class="size-3.5" />Edit season
                    </x-button>
                </a>
                @if ($canDelete)
                    <form method="POST" action="{{ route('seasons.destroy', $season) }}" onsubmit="return confirm('Delete {{ addslashes($season->name) }}? This season has no races, records, or awards.')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="secondary" size="sm">
                            <x-lucide-trash-2 class="size-3.5" />Delete season
                        </x-button>
                    </form>
                @endif
            </div>
        @endif

        @if ($upcomingRace)
            <x-card class="flex flex-col gap-4 overflow-hidden p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl border border-[var(--green)]/25 bg-[var(--green)]/10 text-[var(--green)]">
                        <x-lucide-flag class="size-5" />
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--green)]">Next race</p>
                        <h3 class="text-sm font-bold">{{ $upcomingRace->name }}</h3>
                        <p class="text-xs text-[var(--muted)]">
                            {{ $upcomingRace->date?->format('D, d M Y') }} · {{ $upcomingRace->venue_name }}
                            {{ $upcomingRace->start_time ? '· '.$upcomingRace->start_time : '' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <x-badge class="border-white/10 bg-white/5 text-[var(--muted)]">{{ ucfirst($upcomingRace->status->value) }}</x-badge>
                </div>
            </x-card>
        @endif

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xs font-bold uppercase tracking-[.18em] text-[var(--muted)]">Standings</h2>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-[var(--muted)]">
                    <span>{{ $completedRaces }} {{ \Illuminate\Support\Str::plural('race', $completedRaces) }} scored</span>
                    @if ($progress['remaining'] > 0)
                        <span class="text-[var(--red-bright)]">{{ $progress['remaining'] }} remaining</span>
                    @endif
                    @if ($progress['final_race_date'])
                        <span>
                            Final {{ $progress['final_race_date']->format('d M Y') }}
                            <span class="opacity-70">&middot; {{ $progress['final_race']?->name }}</span>
                        </span>
                    @endif
                </div>
            </div>

            @if (empty($standings))
                <x-empty-state title="No standings yet" description="Standings appear once races in this season are completed." />
            @else
                {{-- Desktop: full scoreboard with one column per event. --}}
                <div class="hidden overflow-x-auto rounded-xl border border-[var(--line)] bg-[var(--panel)] md:block">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Championship standings for {{ $season->name }}</caption>
                        <thead>
                            <tr class="border-b border-[var(--line)] text-[10px] uppercase tracking-widest text-[var(--muted)]">
                                <th scope="col" class="w-14 px-4 py-3">Pos</th>
                                <th scope="col" class="px-4 py-3">Driver</th>
                                @foreach ($events as $event)
                                    @include('championship.partials.event-column-header', ['event' => $event])
                                @endforeach
                                <th scope="col" class="border-l border-[var(--line)] px-4 py-3 text-right">Total</th>
                                <th scope="col" class="px-4 py-3 text-right">Gap</th>
                                <th scope="col" class="px-4 py-3 text-right">Wins</th>
                                <th scope="col" class="px-4 py-3 text-right">Podiums</th>
                                <th scope="col" class="px-4 py-3 text-right">Poles</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--line)]/60">
                            @foreach ($standings as $row)
                                @php $driver = $standingsDrivers[$row['driver_id']] ?? null; @endphp
                                <tr @class(['bg-[var(--red)]/[.05]' => $row['position'] === 1])>
                                    <td class="px-4 py-3">
                                        <span class="grid size-7 place-items-center rounded-lg border {{ $row['position'] === 1 ? 'border-[var(--red)]/40 bg-[var(--red)]/10 font-black text-[var(--red-bright)]' : 'border-[var(--line)] bg-white/[.03] font-mono text-xs' }}">{{ $row['position'] }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($driver)
                                            <a href="{{ route('drivers.show', $driver) }}" class="flex min-w-0 items-center gap-2.5 hover:text-white">
                                                <x-driver-avatar size="sm" :color="$driver->avatar_color" :textColor="$driver->avatar_text_color" :initials="$driver->nickname ?: mb_strtoupper(mb_substr($driver->profile?->full_name ?? '?', 0, 2))" />
                                                <span class="min-w-0">
                                                    <span class="block truncate font-semibold">{{ $driver->profile?->full_name ?? 'Driver #'.$driver->id }}</span>
                                                    <span class="block truncate text-[10px] font-normal text-[var(--muted)]">
                                                        {{ $driver->nickname }}
                                                        @if ($row['tied'])
                                                            <span class="ml-1 rounded-full border border-white/10 bg-white/5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-widest">Tied</span>
                                                        @endif
                                                    </span>
                                                </span>
                                            </a>
                                        @else
                                            <span class="text-[var(--muted)]">Driver #{{ $row['driver_id'] }}</span>
                                        @endif
                                    </td>
                                    @foreach ($events as $event)
                                        @include('championship.partials.event-column-cell', ['event' => $event, 'driverId' => $row['driver_id']])
                                    @endforeach
                                    <td class="border-l border-[var(--line)] px-4 py-3 text-right font-mono text-base font-black text-white">{{ $row['points'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-[var(--muted)]">{{ $row['position'] === 1 ? '—' : '+'.$row['points_gap'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">{{ $row['wins'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">{{ $row['podiums'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">{{ $row['poles'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile: stacked cards, same per-event data. --}}
                <ul class="space-y-3 md:hidden">
                    @foreach ($standings as $row)
                        @php $driver = $standingsDrivers[$row['driver_id']] ?? null; @endphp
                        <li @class([
                            'rounded-xl border bg-[var(--panel)] p-4',
                            'border-[var(--red)]/35' => $row['position'] === 1,
                            'border-[var(--line)]' => $row['position'] !== 1,
                        ])>
                            <div class="flex items-center gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg border {{ $row['position'] === 1 ? 'border-[var(--red)]/40 bg-[var(--red)]/10 font-black text-[var(--red-bright)]' : 'border-[var(--line)] bg-white/[.03] font-mono text-xs' }}">{{ $row['position'] }}</span>
                                @if ($driver)
                                    <a href="{{ route('drivers.show', $driver) }}" class="flex min-w-0 flex-1 items-center gap-2.5">
                                        <x-driver-avatar size="sm" :color="$driver->avatar_color" :textColor="$driver->avatar_text_color" :initials="$driver->nickname ?: mb_strtoupper(mb_substr($driver->profile?->full_name ?? '?', 0, 2))" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold">{{ $driver->profile?->full_name ?? 'Driver #'.$driver->id }}</span>
                                            @if ($driver->nickname || $row['tied'])
                                                <span class="block truncate text-[10px] font-normal text-[var(--muted)]">
                                                    {{ $driver->nickname }}
                                                    @if ($row['tied'])
                                                        <span class="ml-1 rounded-full border border-white/10 bg-white/5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-widest">Tied</span>
                                                    @endif
                                                </span>
                                            @endif
                                        </span>
                                    </a>
                                @else
                                    <span class="min-w-0 flex-1 truncate text-[var(--muted)]">Driver #{{ $row['driver_id'] }}</span>
                                @endif
                                <span class="shrink-0 text-right">
                                    <span class="block font-mono text-lg font-black text-white">{{ $row['points'] }}</span>
                                    <span class="block font-mono text-[9px] text-[var(--muted)]">pts</span>
                                </span>
                            </div>

                            <dl @class([
                                'mt-3 grid gap-2 border-t border-[var(--line)] pt-3',
                                'grid-cols-2' => count($events) <= 2,
                                'grid-cols-3' => count($events) === 3,
                                'grid-cols-4' => count($events) >= 4,
                            ])>
                                @foreach ($events as $event)
                                    <div>
                                        <dt class="truncate text-[9px] uppercase tracking-widest text-[var(--muted)]">{{ $event['name'] }}</dt>
                                        <dd class="font-mono text-sm font-bold text-white">{{ $event['points'][$row['driver_id']] ?? '—' }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-[var(--muted)]">
                                <span>Wins <span class="font-mono text-white">{{ $row['wins'] }}</span></span>
                                <span>Podiums <span class="font-mono text-white">{{ $row['podiums'] }}</span></span>
                                <span>Poles <span class="font-mono text-white">{{ $row['poles'] }}</span></span>
                                @if ($row['position'] !== 1)
                                    <span>Gap <span class="font-mono text-white">+{{ $row['points_gap'] }}</span></span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Constructors' standings: a team's points are the sum of its members'
             individual season points, recomputed from the same results. --}}
        @if (! empty($teamStandings))
            <section class="space-y-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-xs font-bold uppercase tracking-[.18em] text-[var(--muted)]">Team standings</h2>
                    <p class="text-[10px] text-[var(--muted)]">Sum of each team's members' season points</p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-[var(--line)] bg-[var(--panel)]">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Team standings for {{ $season->name }}</caption>
                        <thead>
                            <tr class="border-b border-[var(--line)] text-[10px] uppercase tracking-widest text-[var(--muted)]">
                                <th scope="col" class="w-14 px-4 py-3">Pos</th>
                                <th scope="col" class="px-4 py-3">Team</th>
                                <th scope="col" class="px-4 py-3">Drivers</th>
                                <th scope="col" class="border-l border-[var(--line)] px-4 py-3 text-right">Points</th>
                                <th scope="col" class="px-4 py-3 text-right">Gap</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--line)]/60">
                            @foreach ($teamStandings as $team)
                                <tr @class(['bg-[var(--red)]/[.05]' => $team['position'] === 1])>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'grid size-7 place-items-center rounded-lg border',
                                            'border-[var(--red)]/40 bg-[var(--red)]/10 font-black text-[var(--red-bright)]' => $team['position'] === 1,
                                            'border-[var(--line)] bg-white/[.03] font-mono text-xs' => $team['position'] !== 1,
                                        ])>{{ $team['position'] }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="grid size-8 shrink-0 place-items-center rounded-lg text-[10px] font-black"
                                                  style="background-color: {{ $team['logo_color'] ?: '#c43c2d' }}; color: {{ $team['logo_text_color'] ?: '#ffffff' }}">{{ $team['logo_initials'] ?: 'NA' }}</span>
                                            <span class="font-semibold">{{ $team['name'] }}</span>
                                            @if ($team['tied'])
                                                <span class="rounded-full border border-white/10 bg-white/5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-widest text-[var(--muted)]">Tied</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-[var(--muted)]">
                                        @foreach ($team['members'] as $member)
                                            <span class="mr-2 whitespace-nowrap">{{ $member['name'] }} <span class="font-mono text-white">{{ $member['points'] }}</span></span>
                                        @endforeach
                                    </td>
                                    <td class="border-l border-[var(--line)] px-4 py-3 text-right font-mono text-base font-black text-white">{{ $team['points'] }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs text-[var(--muted)]">{{ $team['position'] === 1 ? '—' : '+'.$team['points_gap'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="space-y-4" id="season-races">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xs font-bold uppercase tracking-[.18em] text-[var(--muted)]">Rounds</h2>
                @if ($canManage)
                    @if ($eligibleRaces->isEmpty())
                        <span class="text-[10px] text-[var(--muted)]">No unassigned races in this group.</span>
                    @else
                        <form method="POST" action="{{ route('seasons.races.store', $season) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <label for="season-race" class="sr-only">Add race to season</label>
                            <select id="season-race" name="race_id" required class="rounded-lg border border-[var(--line)] bg-[var(--panel-raised)] px-3 py-2 text-xs text-white outline-none focus:border-[var(--red)]">
                                <option value="" disabled selected>Choose a race…</option>
                                @foreach ($eligibleRaces as $eligible)
                                    <option value="{{ $eligible->id }}">{{ $eligible->name }} · {{ $eligible->date?->format('d M Y') }}</option>
                                @endforeach
                            </select>
                            <x-button type="submit" size="sm"><x-lucide-plus class="size-3.5" />Add race to season</x-button>
                        </form>
                    @endif
                @endif
            </div>

            @error('race_id')
                <p class="text-[10px] text-[var(--red-bright)]">{{ $message }}</p>
            @enderror

            @if ($rounds->isEmpty())
                <x-empty-state title="No races in this season" description="Races get attached to a season once scheduling opens." />
            @else
                <div class="space-y-3">
                    @foreach ($rounds as $round)
                        <x-card class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg border border-[var(--line)] bg-white/[.03] font-mono text-xs font-bold text-[var(--muted)]">R{{ $round['round'] }}</span>
                            <div class="min-w-0 grow">
                                <h3 class="text-sm font-bold">{{ $round['race']->name }}</h3>
                                <p class="text-xs text-[var(--muted)]">
                                    {{ $round['race']->date?->format('d M Y') }} · {{ $round['race']->venue_name }}
                                </p>
                            </div>

                            @php
                                $statusValue = $round['race']->status instanceof \App\Enums\RaceStatus ? $round['race']->status->value : $round['race']->status;
                                $statusStyles = [
                                    'completed' => 'border-[var(--green)]/30 bg-[var(--green)]/10 text-[var(--green)]',
                                    'qualifying' => 'border-[var(--amber)]/30 bg-[var(--amber)]/10 text-[var(--amber)]',
                                    'grid' => 'border-[var(--amber)]/30 bg-[var(--amber)]/10 text-[var(--amber)]',
                                    'racing' => 'border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]',
                                    'cancelled' => 'border-white/10 bg-white/5 text-[var(--muted)] line-through',
                                ];
                                $statusStyle = $statusStyles[$statusValue] ?? 'border-white/10 bg-white/5 text-[var(--muted)]';
                            @endphp

                            <x-badge :class="$statusStyle">{{ $statusValue }}</x-badge>

                            <div class="flex items-center justify-between gap-3 sm:w-44 sm:justify-end">
                                @if ($round['winner'])
                                    <div class="flex items-center gap-2">
                                        @php
                                            $winner = $round['winner'];
                                        @endphp
                                        <x-driver-avatar
                                            size="sm"
                                            :color="$winner->avatar_color"
                                            :textColor="$winner->avatar_text_color"
                                            :initials="strtoupper(substr($winner->profile?->full_name ?? $winner->nickname, 0, 1))"
                                        />
                                        <div class="leading-tight">
                                            <p class="text-[9px] uppercase tracking-widest text-[var(--muted)]">Winner</p>
                                            <p class="text-xs font-semibold">{{ $winner->display_name }}</p>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-[10px] text-[var(--muted)]">{{ $round['entriesCount'] }} {{ $round['entriesCount'] === 1 ? 'driver' : 'drivers' }}</span>
                                @endif
                                <x-lucide-chevron-right class="size-4 text-[var(--muted)]" />
                            </div>
                        </x-card>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card class="p-5">
                <h3 class="mb-4 flex items-center gap-2 text-sm font-bold">
                    <x-lucide-users class="size-4 text-[var(--red-bright)]" />Participating drivers
                </h3>
                @if ($participatingDrivers->isEmpty())
                    <p class="text-xs text-[var(--muted)]">No drivers have entered a round yet.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($participatingDrivers as $driver)
                            <a href="{{ route('drivers.show', $driver) }}" class="flex items-center gap-2 rounded-full border border-[var(--line)] bg-white/[.03] py-1 pl-1 pr-3 transition hover:border-[var(--line)] hover:bg-[var(--panel-raised)]">
                                <x-driver-avatar
                                    size="sm"
                                    :color="$driver->avatar_color"
                                    :textColor="$driver->avatar_text_color"
                                    :initials="strtoupper(substr($driver->profile?->full_name ?? $driver->nickname, 0, 1))"
                                />
                                <span class="text-xs font-semibold">{{ $driver->display_name }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-card>

            @if ($season->scoring)
                <x-scoring-rules-card :season="$season" />
            @endif
        </div>

        @if ($season->records->isNotEmpty())
            <section class="space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-[.18em] text-[var(--muted)]">Season records</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($season->records as $record)
                        <x-card class="flex items-center gap-3 p-4">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg border border-[var(--amber)]/25 bg-[var(--amber)]/10 text-[var(--amber)]">
                                <x-lucide-medal class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold">{{ $record->label }}</p>
                                <p class="font-mono text-sm font-bold text-white">{{ $record->value }}</p>
                                @if ($record->driver)
                                    <p class="truncate text-[10px] text-[var(--muted)]">{{ $record->driver->display_name }}</p>
                                @endif
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($season->awards->isNotEmpty())
            <section class="space-y-4">
                <h2 class="text-xs font-bold uppercase tracking-[.18em] text-[var(--muted)]">Awards</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($season->awards as $award)
                        <x-card class="p-4">
                            <div class="flex items-start justify-between gap-2">
                                <span class="grid size-9 place-items-center rounded-lg border border-[var(--red)]/25 bg-[var(--red)]/10 text-[var(--red-bright)]">
                                    <x-lucide-trophy class="size-4" />
                                </span>
                                @if ($award->value)
                                    <x-badge class="border-white/10 bg-white/5 text-[var(--muted)]">{{ $award->value }}</x-badge>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-bold">{{ $award->label }}</p>
                            <p class="mt-1 line-clamp-2 text-xs text-[var(--muted)]">{{ $award->description }}</p>
                            <p class="mt-2 text-xs font-semibold text-white">
                                {{ $award->driver?->display_name ?? $award->team?->name ?? '—' }}
                            </p>
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>