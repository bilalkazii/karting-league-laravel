@props(['member'])

@php
    $role = $member->pivot->role;
    $availability = $member->pivot->availability;

    $roleBadge = match ($role) {
        'admin' => ['style' => 'border-[var(--red)]/30 bg-[var(--red)]/10 text-[var(--red-bright)]', 'icon' => 'shield'],
        'organizer' => ['style' => 'border-[var(--amber)]/30 bg-[var(--amber)]/10 text-[var(--amber)]', 'icon' => 'star'],
        default => ['style' => 'border-white/10 bg-white/5 text-[var(--muted)]', 'icon' => 'wrench'],
    };

    $availabilityLabel = match ($availability) {
        'available' => 'Available',
        'maybe' => 'Maybe',
        default => 'Not available',
    };
    $availabilityColor = match ($availability) {
        'available' => 'text-[var(--green)]',
        'maybe' => 'text-[var(--amber)]',
        default => 'text-[var(--muted)]',
    };
@endphp

<div class="flex items-center gap-3 rounded-xl border border-[var(--line)] bg-[var(--panel)] p-4 transition hover:bg-[var(--panel-raised)]">
    <a href="{{ route('profile.show', $member) }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--red)]">
        <x-driver-avatar
            :color="$member->avatar_color"
            :text-color="$member->avatar_text_color"
            :initials="$member->nickname"
            size="md"
        />
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <p class="truncate text-sm font-semibold">{{ $member->profile?->full_name ?? $member->nickname }}</p>
                <x-badge :class="$roleBadge['style']">
                    <x-dynamic-component :component="'lucide-'.$roleBadge['icon']" class="mr-0.5 inline size-2" />
                    {{ $role }}
                </x-badge>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10px] text-[var(--muted)]">
                <span class="font-mono">#{{ str_pad((string) $member->racing_number, 2, '0', STR_PAD_LEFT) }}</span>
                <span class="{{ $availabilityColor }}">{{ $availabilityLabel }}</span>
            </div>
        </div>
    </a>
    <x-rating-badge :rating="$member->rating" class="hidden sm:flex" />
    {{ $slot }}
</div>