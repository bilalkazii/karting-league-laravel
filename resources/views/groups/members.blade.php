<x-app-layout>
    <div class="space-y-6 pb-10">
        <x-group-header :group="$group" :memberCount="$memberCount" />
        <x-group-tabs :group="$group" />

        <div x-data="{ search: '', roleFilter: 'all' }" class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-[var(--muted)]">{{ $memberCount }} drivers in this group</p>
                <x-button size="sm" type="button">
                    <x-lucide-user-plus class="size-[13px]" />Invite driver
                </x-button>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative max-w-sm flex-1">
                    <x-lucide-search class="absolute left-3 top-1/2 size-[15px] -translate-y-1/2 text-[var(--muted)]" />
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Search members..."
                        class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] py-2.5 pl-9 pr-4 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                        aria-label="Search members"
                    />
                </div>
                <div class="flex gap-1" role="radiogroup" aria-label="Filter by role">
                    @foreach ([
                        ['value' => 'all', 'label' => 'All'],
                        ['value' => 'admin', 'label' => 'Admins'],
                        ['value' => 'organizer', 'label' => 'Organizers'],
                        ['value' => 'member', 'label' => 'Members'],
                    ] as $filter)
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="roleFilter === '{{ $filter['value'] }}'"
                            @click="roleFilter = '{{ $filter['value'] }}'"
                            :class="roleFilter === '{{ $filter['value'] }}' ? 'bg-[var(--red)]/10 text-[var(--red-bright)] ring-1 ring-[var(--red)]/20' : 'text-[var(--muted)] hover:bg-white/5'"
                            class="rounded-lg px-3 py-1.5 text-[10px] font-semibold transition"
                        >{{ $filter['label'] }}</button>
                    @endforeach
                </div>
            </div>

            @if ($members->isEmpty())
                <x-empty-state title="No members found" description="Try a different search or filter." />
            @else
                <div class="space-y-2">
                    @foreach ($members as $member)
                        <div
                            data-member-row
                            x-show="(roleFilter === 'all' || roleFilter === '{{ $member->pivot->role }}')
                                && (search === ''
                                    || {{ json_encode(strtolower($member->profile?->full_name ?? $member->nickname)) }}.includes(search.toLowerCase())
                                    || {{ json_encode(strtolower($member->nickname)) }}.includes(search.toLowerCase()))"
                        >
                            <x-member-card :member="$member">
                                @if ($isOrganizer && $member->id !== auth()->user()?->driver?->id)
                                    <div class="flex items-center gap-1">
                                        <button type="button" class="grid size-8 place-items-center rounded-lg border border-[var(--line)] text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-[var(--red-bright)]" title="Remove {{ $member->profile?->full_name ?? $member->nickname }}" aria-label="Remove driver {{ $member->id }}">
                                            <x-lucide-user-x class="size-3.5" />
                                        </button>
                                        <button type="button" class="grid size-8 place-items-center rounded-lg border border-[var(--line)] text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-[var(--red-bright)]" title="Block {{ $member->profile?->full_name ?? $member->nickname }}" aria-label="Block driver {{ $member->id }}">
                                            <x-lucide-shield-off class="size-3.5" />
                                        </button>
                                    </div>
                                @endif
                            </x-member-card>
                        </div>
                    @endforeach
                </div>

                <div
                    x-cloak
                    x-show="(search !== '' || roleFilter !== 'all') && [...document.querySelectorAll('[data-member-row]')].every(el => el.style.display === 'none')"
                >
                    <x-empty-state title="No members found" description="Try a different search or filter." />
                </div>
            @endif

            <p class="text-[10px] text-[var(--muted)]">Remove and block actions are visible to admins and organizers. These updates affect local state only until backend integration.</p>
        </div>
    </div>
</x-app-layout>