<x-app-layout>
    <div class="space-y-8">
        <x-page-header
            eyebrow="The grid"
            title="Drivers"
            description="Everyone in your paddock — ratings, karts, and the groups they belong to."
        />

        <form method="GET" action="{{ route('drivers') }}" class="relative max-w-sm" role="search">
            <x-lucide-search class="absolute left-3 top-1/2 size-[15px] -translate-y-1/2 text-[var(--muted)]" />
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search drivers..."
                class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] py-2.5 pl-9 pr-4 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                aria-label="Search drivers"
            />
        </form>

        @if ($drivers->isEmpty())
            <x-empty-state
                title="{{ request('q') ? 'No drivers found' : 'No drivers yet' }}"
                description="{{ request('q') ? 'Try a different search term.' : 'Drivers will appear here once they join the paddock.' }}"
            />
        @else
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($drivers as $driver)
                    <x-driver-card
                        :driver="$driver"
                        :groups="$driver->groups"
                        :teams="$driver->teams"
                    />
                @endforeach
            </div>

            @if ($drivers->hasPages())
                <nav class="flex items-center justify-between gap-3 border-t border-[var(--line)] pt-5" aria-label="Pagination">
                    <p class="text-xs text-[var(--muted)]">
                        Showing {{ $drivers->firstItem() }}–{{ $drivers->lastItem() }} of {{ $drivers->total() }}
                    </p>
                    <div class="flex items-center gap-2">
                        @if ($drivers->onFirstPage())
                            <span class="pointer-events-none rounded-lg border border-[var(--line)] px-3 py-1.5 text-xs text-[var(--muted)]">Previous</span>
                        @else
                            <a href="{{ $drivers->previousPageUrl() }}" class="rounded-lg border border-[var(--line)] bg-white/[.02] px-3 py-1.5 text-xs text-white transition hover:bg-white/5">Previous</a>
                        @endif

                        @if ($drivers->hasMorePages())
                            <a href="{{ $drivers->nextPageUrl() }}" class="rounded-lg border border-[var(--line)] bg-white/[.02] px-3 py-1.5 text-xs text-white transition hover:bg-white/5">Next</a>
                        @else
                            <span class="pointer-events-none rounded-lg border border-[var(--line)] px-3 py-1.5 text-xs text-[var(--muted)]">Next</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </div>
</x-app-layout>