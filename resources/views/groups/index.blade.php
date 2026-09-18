<x-app-layout>
    <div class="space-y-8">
        <x-page-header eyebrow="The paddock" title="My groups" description="Your karting crews, race weekends, and championship battles.">
            <x-button variant="secondary" type="button">
                <x-lucide-user-plus class="size-3.5" />Join group
            </x-button>
            <a href="{{ route('groups.new') }}">
                <x-button>
                    <x-lucide-plus class="size-3.5" />Create group
                </x-button>
            </a>
        </x-page-header>

        @if ($groups->isEmpty())
            <x-empty-state title="No groups found" description="Create your first group to start organizing races with friends.">
                <a href="{{ route('groups.new') }}">
                    <x-button>
                        <x-lucide-plus class="size-3.5" />Create group
                    </x-button>
                </a>
            </x-empty-state>
        @else
            <div x-data="{ search: '' }" class="space-y-6">
                <div class="relative max-w-sm">
                    <x-lucide-search class="absolute left-3 top-1/2 size-[15px] -translate-y-1/2 text-[var(--muted)]" />
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Search groups..."
                        class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] py-2.5 pl-9 pr-4 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                        aria-label="Search groups"
                    />
                </div>

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($groups as $group)
                        <div
                            data-group-card
                            x-show="search === '' || {{ json_encode(strtolower($group->name)) }}.includes(search.toLowerCase())"
                        >
                            <x-group-card :group="$group" :memberCount="$memberCounts[$group->id] ?? 0" />
                        </div>
                    @endforeach
                </div>

                <div
                    x-cloak
                    x-show="search !== '' && [...document.querySelectorAll('[data-group-card]')].every(el => el.style.display === 'none')"
                >
                    <x-empty-state title="No groups found" description="Try a different search term." />
                </div>
            </div>
        @endif
    </div>
</x-app-layout>