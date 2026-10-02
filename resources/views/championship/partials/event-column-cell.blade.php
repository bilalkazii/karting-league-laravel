@props(['event', 'driverId'])

@php
    $value = $event['points'][$driverId] ?? null;
@endphp

<td class="px-3 py-3 text-right font-mono text-sm">
    @if ($value === null)
        <span class="text-[var(--muted)]" aria-label="Did not score">&mdash;</span>
    @else
        <span @class([
            'font-bold text-white',
            'text-[var(--red-bright)]' => $value >= \App\Support\StandingsService::DEFAULT_POINTS_BY_POSITION[1],
        ])>{{ $value }}</span>
    @endif
</td>