<x-app-layout>
    <div class="space-y-8">
        <x-page-header eyebrow="Race weekends" title="Races" description="Lobbies, qualifying, grids, and results for your groups.">
            <a href="{{ route('races.new') }}">
                <x-button>
                    <x-lucide-plus class="size-3.5" />Create race
                </x-button>
            </a>
        </x-page-header>

        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('races') }}" class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <x-lucide-search class="absolute left-3 top-1/2 size-[15px] -translate-y-1/2 text-[var(--muted)]" />
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search races..."
                        class="w-56 rounded-lg border border-[var(--line)] bg-white/[.03] py-2 pl-9 pr-4 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)]"
                        aria-label="Search races"
                    />
                </div>
                <select
                    name="status"
                    onchange="this.form.submit()"
                    class="rounded-lg border border-[var(--line)] bg-[var(--panel-raised)] px-3 py-2 text-sm text-white outline-none focus:border-[var(--red)]"
                    aria-label="Filter by status"
                >
                    <option value="" @selected(! request('status'))>All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
                @if (request('q') || request('status'))
                    <a href="{{ route('races') }}" class="text-xs font-semibold text-[var(--red-bright)] hover:underline">Clear filters</a>
                @endif
            </form>
        </div>

        @if ($races->isEmpty())
            <x-empty-state title="No races found" description="Create your first race to open a lobby and start qualifying.">
                <a href="{{ route('races.new') }}">
                    <x-button>
                        <x-lucide-plus class="size-3.5" />Create race
                    </x-button>
                </a>
            </x-empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($races as $race)
                    <x-race-card :race="$race" :driverCount="$race->entries_count" />
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>