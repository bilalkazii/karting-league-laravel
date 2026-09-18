@props(['events' => [], 'limit' => null])

@php
    $list = collect($events);
    if ($limit) {
        $list = $list->take((int) $limit);
    }
    $typeLabels = [
        'qualifying_start' => 'Qualifying session started',
        'qualifying_stop' => 'Qualifying lap recorded',
        'qualifying_invalid' => 'Qualifying lap invalidated',
        'qualifying_restore' => 'Qualifying lap restored',
        'qualifying_reset' => 'Qualifying time cleared',
        'qualifying_corrected' => 'Qualifying time manually corrected',
        'qualifying_replace' => 'Official qualifying time replaced',
        'qualifying_promoted' => 'Qualifying time promoted',
        'ready' => 'Marked ready',
        'not_ready' => 'Marked not ready',
        'race_start' => 'Race started',
        'race_finish' => 'Finished the race',
        'dnf' => 'Did not finish',
        'dns' => 'Did not start',
        'retired' => 'Retired',
        'withdrawn' => 'Withdrawn',
        'penalty' => 'Penalty issued',
        'penalty_cancelled' => 'Penalty cancelled',
        'grid_change' => 'Grid position changed',
        'grid_penalty' => 'Grid penalty applied',
        'complete' => 'Race completed',
        'note' => 'Note recorded',
        'status_change' => 'Status changed',
    ];
@endphp

@if ($list->isEmpty())
    <p class="text-xs text-[var(--muted)]">No events recorded yet in this session.</p>
@else
    <ol class="space-y-0">
        @foreach ($list as $event)
            @php
                $typeValue = $event->type instanceof \App\Enums\RaceEventType ? $event->type->value : $event->type;
                $driverName = $event->driver?->display_name ?? ($event->driver ? $event->driver->profile?->full_name : null);
                $occurred = $event->occurred_at?->format('H:i:s') ?? '';
            @endphp
            <li class="relative flex items-baseline gap-3 py-2 {{ ! $loop->last ? 'border-b border-[var(--line)]/60' : '' }}">
                <span class="size-1.5 shrink-0 rounded-full bg-[var(--red)]/70"></span>
                <p class="min-w-0 flex-1 truncate text-xs">
                    <span class="font-semibold text-white">{{ $driverName ?? 'Session' }}</span>
                    <span class="text-[var(--muted)]"> · {{ $typeLabels[$typeValue] ?? ucfirst($typeValue) }}</span>
                </p>
                <time class="shrink-0 font-mono text-[10px] text-[var(--muted)]">{{ $occurred }}</time>
            </li>
        @endforeach
    </ol>
@endif