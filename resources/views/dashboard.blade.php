<x-app-layout>
    @php
        $activity = [
            ['name' => 'Umar joined the group', 'time' => '2 hours ago', 'initials' => 'UM', 'color' => 'bg-[#8c6a52]'],
            ['name' => 'Saturday GP was created', 'time' => 'Yesterday', 'initials' => 'SG', 'color' => 'bg-[#435e6e]'],
            ['name' => 'Ahmad set a new personal best', 'time' => '2 days ago', 'initials' => 'AH', 'color' => 'bg-[#645c86]'],
        ];
    @endphp

    <div class="space-y-8">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.2em] text-[var(--red-bright)]">{{ now()->format('l, d F Y') }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-[-.05em] sm:text-4xl">Good evening, {{ $driver?->profile?->full_name ?? 'there' }}<span class="text-[var(--red)]">.</span></h1>
                <p class="mt-2 text-sm text-[var(--muted)]">Your next race weekend is getting close.</p>
            </div>
            @if ($driver && $driver->groups->count() > 0)
                <a href="{{ route('races.new') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[var(--red)] px-4 text-xs font-bold text-white hover:bg-[var(--red-bright)]">
                    <x-lucide-plus class="size-4" />Create a race
                </a>
            @endif
        </div>

        @if ($upcomingRace)
            <div class="carbon relative overflow-hidden rounded-xl border border-[var(--red)]/40 bg-[var(--panel)]">
                <div class="absolute -right-12 -top-20 size-72 rounded-full bg-[var(--red)]/10 blur-3xl"></div>
                <div class="relative grid gap-7 p-5 sm:grid-cols-[1fr_auto] sm:p-7">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full border border-[var(--red)]/40 bg-[var(--red)]/10 px-2.5 py-0.5 text-xs font-semibold text-[var(--red-bright)]">Next race</span>
                            <span class="text-xs text-[var(--muted)]">{{ $upcomingRace->group->name }} · Round {{ $upcomingRace->seasons->first()?->pivot?->round_number ?? '—' }}</span>
                        </div>
                        <h2 class="mt-4 text-3xl font-black tracking-[-.04em] sm:text-4xl">{{ $upcomingRace->name }}</h2>
                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-[var(--muted)]">
                            <span class="flex items-center gap-1.5"><x-lucide-map-pin class="size-3.5 text-[var(--red-bright)]" />{{ $upcomingRace->venue_name }}</span>
                            <span class="flex items-center gap-1.5"><x-lucide-calendar-days class="size-3.5 text-[var(--red-bright)]" />{{ $upcomingRace->date?->format('d M Y') }}</span>
                            <span class="flex items-center gap-1.5"><x-lucide-users class="size-3.5 text-[var(--red-bright)]" />{{ $upcomingRace->entries()->count() }} drivers</span>
                        </div>
                        <a href="{{ route('races.show', $upcomingRace) }}" class="mt-7 inline-flex h-10 items-center gap-2 rounded-lg bg-[var(--red)] px-4 text-xs font-bold text-white hover:bg-[var(--red-bright)]">
                            View race <x-lucide-arrow-up-right class="size-[15px]" />
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-3">
            <x-stat-card :label="'Races completed'" :value="(string) $stats['started']" detail="in your career" />
            <x-stat-card :label="'Championship points'" :value="(string) $stats['points']" detail="current season" :accent="true" />
            <x-stat-card :label="'Best qualifying'" :value="$stats['bestQuali'] ? number_format($stats['bestQuali'] / 1000, 3).'s' : '—'" detail="personal best" />
        </div>

        <div class="grid gap-5 xl:grid-cols-[1.35fr_1fr]">
            <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)]">
                <div class="flex items-center justify-between border-b border-[var(--line)] px-5 py-4">
                    <h3 class="text-sm font-bold">Recent activity</h3>
                    <a href="{{ route('chat') }}" class="text-xs font-semibold text-[var(--red-bright)]">View all</a>
                </div>
                <div class="space-y-1 p-2">
                    @foreach ($activity as $item)
                        <div class="flex items-center gap-3 rounded-lg px-2 py-3 hover:bg-white/[.03]">
                            <span class="grid size-9 place-items-center rounded-full text-[9px] font-bold {{ $item['color'] }}">{{ $item['initials'] }}</span>
                            <div>
                                <p class="text-sm font-medium">{{ $item['name'] }}</p>
                                <p class="mt-0.5 text-xs text-[var(--muted)]">{{ $item['time'] }}</p>
                            </div>
                            <x-lucide-chevron-right class="ml-auto size-[15px] text-[var(--muted)]" />
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)]">
                <div class="flex items-center justify-between border-b border-[var(--line)] px-5 py-4">
                    <h3 class="text-sm font-bold">Your groups</h3>
                    <a href="{{ route('groups') }}" class="text-xs font-semibold text-[var(--red-bright)]">View all</a>
                </div>
                <div class="space-y-1 p-2">
                    @forelse ($groups as $group)
                        <a href="{{ route('groups.show', $group) }}" class="flex items-center gap-3 rounded-lg border border-transparent p-2 transition hover:border-[var(--line)] hover:bg-white/[.03]">
                            <span class="grid size-9 place-items-center rounded-lg text-[10px] font-black" style="background-color: {{ $group->logo_color }}; color: {{ $group->logo_text_color }}">{{ $group->logo_initials }}</span>
                            <div>
                                <p class="text-sm font-semibold">{{ $group->name }}</p>
                                <p class="text-[11px] text-[var(--muted)]">{{ $memberCounts[$group->id] ?? 0 }} members</p>
                            </div>
                            <x-lucide-chevron-right class="ml-auto size-[15px] text-[var(--muted)]" />
                        </a>
                    @empty
                        <p class="px-3 py-4 text-sm text-[var(--muted)]">No groups yet. Start one to race together.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>