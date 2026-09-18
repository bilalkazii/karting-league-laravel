<x-app-layout>
    <div class="space-y-6 pb-10">
        <x-group-header :group="$group" :memberCount="$memberCount" />
        <x-group-tabs :group="$group" />

        <div class="grid gap-5 xl:grid-cols-[1.3fr_1fr]">
            <div class="space-y-5">
                @if ($upcomingRace)
                    <x-card class="carbon relative overflow-hidden border-[var(--red)]/30">
                        <div class="absolute -right-10 -top-16 size-56 rounded-full bg-[var(--red)]/10 blur-3xl"></div>
                        <div class="relative p-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge class="border-[var(--red)]/40 bg-[var(--red)]/10 text-[var(--red-bright)]">Next race</x-badge>
                            </div>
                            <h3 class="mt-3 text-2xl font-black tracking-[-.03em]">{{ $upcomingRace->name }}</h3>
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--muted)]">
                                <span class="flex items-center gap-1">
                                    <x-lucide-map-pin class="size-3 text-[var(--red-bright)]" />{{ $upcomingRace->venue_name }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <x-lucide-calendar-days class="size-3 text-[var(--red-bright)]" />{{ $upcomingRace->date?->format('d M Y') }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <x-lucide-users class="size-3 text-[var(--red-bright)]" />{{ $upcomingRace->entries()->count() }} drivers
                                </span>
                            </div>
                            <a href="{{ route('races') }}" class="mt-5 inline-flex h-9 items-center gap-2 rounded-lg bg-[var(--red)] px-3.5 text-xs font-bold text-white hover:bg-[var(--red-bright)]">
                                View race lobby <x-lucide-arrow-up-right class="size-3.5" />
                            </a>
                        </div>
                    </x-card>
                @endif

                <x-card>
                    <div class="border-b border-[var(--line)] px-5 py-4">
                        <h3 class="text-sm font-bold">Admins</h3>
                    </div>
                    <div class="space-y-2 p-5 pt-2">
                        @forelse ($admins as $admin)
                            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                                <x-driver-avatar
                                    :color="$admin->avatar_color"
                                    :text-color="$admin->avatar_text_color"
                                    :initials="$admin->nickname"
                                    size="sm"
                                />
                                <div>
                                    <p class="text-sm font-semibold">{{ $admin->profile?->full_name ?? $admin->nickname }}</p>
                                    <p class="text-[10px] text-[var(--muted)]">{{ $admin->nickname }} · #{{ str_pad((string) $admin->racing_number, 2, '0', STR_PAD_LEFT) }}</p>
                                </div>
                                <x-badge class="ml-auto border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]">admin</x-badge>
                            </div>
                        @empty
                            <p class="px-2 py-2 text-sm text-[var(--muted)]">No admins listed.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>

            <div class="space-y-5">
                @include('groups.partials.availability-display', ['availability' => $currentUserAvailability])

                @if ($currentChampionship)
                    <x-card>
                        <div class="flex items-center justify-between border-b border-[var(--line)] px-5 py-4">
                            <h3 class="text-sm font-bold">Championship</h3>
                            <a href="{{ route('championship') }}" class="text-xs font-semibold text-[var(--red-bright)]">View</a>
                        </div>
                        <div class="p-5">
                            <p class="text-sm font-bold">{{ $currentChampionship->name }}</p>
                            <p class="mt-1 text-xs text-[var(--muted)]">Round {{ $championshipRound }} of {{ $currentChampionship->races_count }}</p>
                        </div>
                    </x-card>
                @endif

                @if ($recentResults->isNotEmpty())
                    <x-card>
                        <div class="flex items-center justify-between border-b border-[var(--line)] px-5 py-4">
                            <h3 class="text-sm font-bold">Recent results</h3>
                            <a href="{{ route('races') }}" class="text-xs font-semibold text-[var(--red-bright)]">View all</a>
                        </div>
                        <div class="space-y-2 p-5 pt-2">
                            @foreach ($recentResults as $result)
                                <div class="flex items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-white/[.03]">
                                    <span class="grid size-9 place-items-center rounded-lg bg-[var(--amber)]/10 text-[var(--amber)]">
                                        <x-lucide-trophy class="size-[15px]" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">{{ $result['name'] }}</p>
                                        <p class="text-[10px] text-[var(--muted)]">{{ $result['date'] }} · {{ $result['venue_name'] }}</p>
                                    </div>
                                    @if ($result['winner'])
                                        <div class="flex items-center gap-1.5 text-xs">
                                            <x-driver-avatar
                                                :color="$result['winner']->avatar_color"
                                                :text-color="$result['winner']->avatar_text_color"
                                                :initials="$result['winner']->nickname"
                                                size="sm"
                                            />
                                            <span class="font-semibold">{{ $result['winner']->nickname }}</span>
                                        </div>
                                    @endif
                                    <x-lucide-chevron-right class="size-3.5 text-[var(--muted)]" />
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif
            </div>
        </div>

        @if ($teams->isNotEmpty())
            <div>
                <h2 class="mb-4 text-xs font-bold uppercase tracking-widest text-[var(--muted)]">Teams</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($teams as $team)
                        <x-team-card :team="$team" />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>