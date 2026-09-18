@props(['team'])

<x-card class="p-5">
    <div class="flex items-center gap-3">
        <span class="grid size-10 place-items-center rounded-lg text-xs font-black" style="background-color: {{ $team->logo_color }}; color: {{ $team->logo_text_color }}">{{ $team->logo_initials }}</span>
        <div>
            <h3 class="text-sm font-bold">{{ $team->name }}</h3>
            <p class="text-[10px] text-[var(--muted)]">{{ $team->members->count() }} drivers</p>
        </div>
    </div>
    <div class="mt-4 flex items-center">
        @foreach ($team->members as $member)
            <x-driver-avatar
                :color="$member->avatar_color"
                :text-color="$member->avatar_text_color"
                :initials="$member->nickname"
                size="sm"
                @class(['-ml-2 ring-2 ring-[var(--panel)]' => ! $loop->first])
            />
        @endforeach
    </div>
    <div class="mt-3 space-y-1">
        @foreach ($team->members as $member)
            <div class="flex items-center gap-2 text-xs text-[var(--muted)]">
                <x-lucide-users class="size-[11px]" />
                {{ $member->profile?->full_name ?? $member->nickname }}
                <span class="font-mono text-[10px]">#{{ str_pad((string) $member->racing_number, 2, '0', STR_PAD_LEFT) }}</span>
            </div>
        @endforeach
    </div>
</x-card>