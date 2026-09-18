<x-app-layout>
    @php
        $status = $race->status->value;
        $canManage = auth()->user() && auth()->user()->can('manage', $race);
        $base = ['race' => $race, 'tab' => $currentTab];
    @endphp

    <div class="space-y-6">
        <x-race-header :race="$race" :driverCount="$entries ? count($entries) : 0" />

        <x-race-tabs
            :tabs="$tabs"
            :currentTab="$currentTab"
            :raceStatus="$race->status"
            :enabledStatuses="$enabledStatuses"
        />

        <div class="flex flex-wrap items-center gap-3 pb-2">
            @if ($canManage)
                @if ($status === 'draft')
                    <form method="POST" action="{{ route('races.lobby.open', $race) }}">
                        @csrf
                        <x-button><x-lucide-users class="size-3.5" />Open lobby</x-button>
                    </form>
                @elseif ($status === 'lobby')
                    <form method="POST" action="{{ route('races.qualifying.start', $race) }}">
                        @csrf
                        <x-button><x-lucide-timer class="size-3.5" />Start qualifying</x-button>
                    </form>
                @elseif ($status === 'qualifying')
                    <form method="POST" action="{{ route('races.lock-grid', $race) }}">
                        @csrf
                        <x-button><x-lucide-grid-3x3 class="size-3.5" />Lock grid</x-button>
                    </form>
                @elseif ($status === 'grid')
                    <form method="POST" action="{{ route('races.start', $race) }}">
                        @csrf
                        <x-button><x-lucide-flag class="size-3.5" />Start race</x-button>
                    </form>
                @elseif ($status === 'racing')
                    <form method="POST" action="{{ route('races.complete', $race) }}">
                        @csrf
                        <x-button><x-lucide-flag class="size-3.5" />Complete race</x-button>
                    </form>
                @endif
                @if (in_array($status, ['draft', 'lobby', 'qualifying', 'grid', 'racing'], true))
                    <form method="POST" action="{{ route('races.cancel', $race) }}" onsubmit="return confirm('Cancel this race?')">
                        @csrf
                        <x-button type="submit" variant="ghost" class="text-[var(--red-bright)]">
                            <x-lucide-octagon-x class="size-3.5" />Cancel race
                        </x-button>
                    </form>
                @endif
                <a href="{{ route('races.edit', $race) }}">
                    <x-button type="button" variant="ghost"><x-lucide-pencil class="size-3.5" />Edit</x-button>
                </a>
            @endif
            <a href="{{ route('chat.race', $race) }}">
                <x-button type="button" variant="ghost"><x-lucide-message-square class="size-3.5" />Race chat</x-button>
            </a>
            <x-badge class="border-white/10 bg-white/5 font-mono text-[10px] text-[var(--muted)]">
                {{ $race->date->format('d M Y') }} · {{ $race->start_time }}
            </x-badge>
        </div>

        @switch($currentTab)
            @case('setup')
                @include('races.partials.setup')
                @break

            @case('lobby')
                @include('races.partials.lobby')
                @break

            @case('qualifying')
                @include('races.partials.qualifying')
                @break

            @case('grid')
                @include('races.partials.grid')
                @break

            @case('control')
                @include('races.partials.control')
                @break

            @case('results')
                @include('races.partials.results')
                @break

            @default
                @include('races.partials.overview')
        @endswitch
    </div>
</x-app-layout>