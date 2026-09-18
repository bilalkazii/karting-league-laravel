<x-app-layout>
    <div class="space-y-8">
        <x-page-header eyebrow="The long game" title="Championship" description="Season standings, scoring rules, and championship battles across your groups.">
            <a href="{{ route('seasons.new') }}">
                <x-button>
                    <x-lucide-calendar-plus class="size-3.5" />New season
                </x-button>
            </a>
        </x-page-header>

        @if ($seasons->isEmpty())
            <x-empty-state title="No seasons yet" description="Runs your group follows form as seasons — each one locks in race dates and scoring rules.">
                <a href="{{ route('seasons.new') }}">
                    <x-button>
                        <x-lucide-calendar-plus class="size-3.5" />Create your first season
                    </x-button>
                </a>
            </x-empty-state>
        @else
            <form method="GET" action="{{ route('championship') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative max-w-sm grow">
                    <x-lucide-search class="absolute left-3 top-1/2 size-[15px] -translate-y-1/2 text-[var(--muted)]" />
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search seasons..."
                        class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] py-2.5 pl-9 pr-4 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                        aria-label="Search seasons"
                    />
                </div>
                <select
                    name="status"
                    aria-label="Filter by status"
                    class="rounded-lg border border-[var(--line)] bg-white/[.03] py-2.5 pl-3 pr-8 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                >
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
                @if (request('q') || request('status'))
                    <a href="{{ route('championship') }}" class="text-xs text-[var(--muted)] hover:text-white">
                        <x-lucide-rotate-ccw class="mr-1 inline size-3" />Reset
                    </a>
                @endif
            </form>

            @if ($seasons->isEmpty())
                <x-empty-state title="No seasons found" description="Try a different search or status filter." />
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($seasons as $season)
                        <x-season-card
                            :season="$season"
                            :racesCount="$season->races_count"
                            :managed="$managedSeasonIds->contains($season->id)"
                        />
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-app-layout>