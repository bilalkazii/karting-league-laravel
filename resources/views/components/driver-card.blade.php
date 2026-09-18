@props(['driver', 'groups' => [], 'teams' => []])

<a
    href="{{ route('drivers.show', $driver) }}"
    class="flex h-full flex-col rounded-xl border border-[var(--line)] bg-[var(--panel)] p-5 transition hover:border-[var(--red)]/40 hover:bg-[var(--panel-raised)]"
>
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <x-driver-avatar
                :color="$driver->avatar_color"
                :text-color="$driver->avatar_text_color"
                :initials="$driver->nickname ?? '?'"
                size="md"
            />
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold">{{ $driver->profile?->full_name ?? $driver->nickname }}</p>
                <p class="mt-0.5 text-[10px] text-[var(--muted)]">
                    {{ $driver->nickname ? $driver->nickname.' · ' : '' }}
                    <span class="font-mono">#{{ str_pad((string) ($driver->racing_number ?? ''), 2, '0', STR_PAD_LEFT) }}</span>
                </p>
            </div>
        </div>
        <x-rating-badge :rating="$driver->rating" />
    </div>

    @if ($groups->isNotEmpty() || $teams->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-1.5">
            @foreach ($groups as $group)
                <span class="inline-flex items-center gap-1 rounded bg-white/5 px-2 py-0.5 text-[10px] font-semibold text-[var(--muted)]">
                    <x-lucide-users class="size-2.5" />{{ $group->name }}
                </span>
            @endforeach
            @foreach ($teams as $team)
                <span class="inline-flex items-center gap-1 rounded bg-[var(--red)]/10 px-2 py-0.5 text-[10px] font-semibold text-[var(--red-bright)]">
                    <x-lucide-zap class="size-2.5" />{{ $team->name }}
                </span>
            @endforeach
        </div>
    @else
        <p class="mt-4 text-[10px] text-[var(--muted)]">No shared groups yet.</p>
    @endif
</a>