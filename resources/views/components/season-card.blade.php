@props(['season', 'racesCount' => null, 'managed' => false])

@php
    $count = $racesCount ?? $season->races_count ?? $season->races()->count();
    $start = $season->start_date?->format('d M Y');
    $end = $season->end_date?->format('d M Y');
@endphp

<a
    href="{{ route('championship.show', $season) }}"
    class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--red)]"
>
    <x-card class="relative overflow-hidden transition hover:border-[var(--line)] hover:bg-[var(--panel-raised)]">
        <div class="absolute -right-10 -top-16 size-32 rounded-full bg-[var(--red)]/10 blur-2xl"></div>
        <div class="relative space-y-4 p-5">
            <div class="flex items-start justify-between gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl border border-[var(--red)]/30 bg-[var(--red)]/10 font-black text-[var(--red-bright)]">{{ mb_strtoupper(mb_substr($season->name, 0, 1)) }}</span>
                <x-season-status-badge :status="$season->status" />
            </div>

            <div class="space-y-1.5">
                <h3 class="line-clamp-1 text-sm font-bold">{{ $season->name }}</h3>
                <div class="flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-[var(--muted)]">
                    <span class="flex items-center gap-1">
                        <x-lucide-users class="size-3" />{{ $season->group->name }}
                    </span>
                    @if ($start && $end)
                        <span class="flex items-center gap-1">
                            <x-lucide-calendar-days class="size-3" />{{ $start }} → {{ $end }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-[var(--line)] pt-3">
                <span class="text-[10px] text-[var(--muted)]">{{ $count }} {{ $count === 1 ? 'race' : 'races' }}</span>
                <span class="flex items-center gap-1 text-xs text-[var(--muted)] transition-colors group-hover:text-white">
                    {{ $managed ? 'Manage season' : 'View season' }}
                    <x-lucide-chevron-right class="size-3.5" />
                </span>
            </div>
        </div>
    </x-card>
</a>