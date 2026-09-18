@props(['tabs' => [], 'currentTab' => 'overview', 'raceStatus' => 'draft', 'enabledStatuses' => []])

@php
    $statusValue = $raceStatus instanceof \App\Enums\RaceStatus ? $raceStatus->value : $raceStatus;
    $labels = [
        'overview' => 'Overview', 'setup' => 'Setup', 'lobby' => 'Lobby', 'qualifying' => 'Qualifying',
        'grid' => 'Grid', 'control' => 'Control', 'results' => 'Results',
    ];
@endphp

<nav class="flex gap-1 overflow-x-auto border-b border-[var(--line)]" aria-label="Race navigation">
    @foreach ($tabs as $tab)
        @php
            $enabled = in_array($statusValue, $enabledStatuses[$tab] ?? [], true);
            $href = route('races.show', ['race' => request()->route('race'), 'tab' => $tab]);
        @endphp
        @if (! $enabled)
            <span class="shrink-0 cursor-not-allowed px-4 py-3 text-xs font-semibold text-[#3c454c]" aria-disabled="true">{{ $labels[$tab] }}</span>
        @else
            <a href="{{ $href }}" @class([
                'shrink-0 px-4 py-3 text-xs font-semibold transition border-b-2 -mb-px',
                'border-[var(--red)] text-white' => $currentTab === $tab,
                'border-transparent text-[var(--muted)] hover:text-white' => $currentTab !== $tab,
            ])>{{ $labels[$tab] }}</a>
        @endif
    @endforeach
</nav>