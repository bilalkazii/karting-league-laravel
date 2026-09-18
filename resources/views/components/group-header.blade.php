@props(['group', 'memberCount' => 0])

<div class="overflow-hidden rounded-xl border border-[var(--line)]">
    <div class="relative h-36 sm:h-44" style="background-color: {{ $group->cover_color }}">
        <div class="absolute inset-0 carbon"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-[var(--panel)] to-transparent"></div>
    </div>
    <div class="-mt-10 relative px-5 pb-5 sm:px-7">
        <div class="flex items-end gap-4">
            <span class="grid size-16 place-items-center rounded-xl text-lg font-black ring-4 ring-[var(--panel)] sm:size-20 sm:text-xl" style="background-color: {{ $group->logo_color }}; color: {{ $group->logo_text_color }}">{{ $group->logo_initials }}</span>
            <div class="min-w-0 pb-1">
                <h1 class="truncate text-xl font-black tracking-[-.04em] sm:text-2xl">{{ $group->name }}</h1>
                <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-[var(--muted)]">
                    <span class="flex items-center gap-1">
                        <x-lucide-users class="size-3" />{{ $memberCount }} members
                    </span>
                    <span class="flex items-center gap-1">
                        <x-lucide-calendar-days class="size-3" />Created {{ $group->created_at?->format('Y-m-d') }}
                    </span>
                    <x-badge class="border-white/10 bg-white/5 text-[var(--muted)]">{{ $group->privacy->value }}</x-badge>
                </div>
            </div>
        </div>
        <p class="mt-4 max-w-2xl text-sm text-[var(--muted)]">{{ $group->description }}</p>
    </div>
</div>