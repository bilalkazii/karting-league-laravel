@props(['group', 'memberCount' => 0])

<a
    href="{{ route('groups.show', $group) }}"
    class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--red)]"
>
    <x-card class="overflow-hidden transition hover:border-[var(--line)] hover:bg-[var(--panel-raised)]">
        <div class="relative h-28 sm:h-32" style="background-color: {{ $group->cover_color }}">
            <div class="absolute inset-0 carbon"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
            <div class="absolute bottom-3 left-4 flex items-center gap-2.5">
                <span class="grid size-10 place-items-center rounded-lg text-xs font-black" style="background-color: {{ $group->logo_color }}; color: {{ $group->logo_text_color }}">{{ $group->logo_initials }}</span>
                <div>
                    <h3 class="text-sm font-bold leading-tight">{{ $group->name }}</h3>
                    <p class="text-[10px] text-white/60">{{ $group->privacy->value === 'private' ? 'Private group' : 'Public' }}</p>
                </div>
            </div>
        </div>
        <div class="space-y-3 p-4">
            <p class="line-clamp-2 text-xs text-[var(--muted)]">{{ $group->description }}</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-[var(--muted)]">
                <span class="flex items-center gap-1">
                    <x-lucide-users class="size-3" />{{ $memberCount }} members
                </span>
            </div>
            <div class="flex items-center justify-between pt-1 text-xs text-[var(--muted)] transition-colors group-hover:text-white">
                <span>View group</span>
                <x-lucide-chevron-right class="size-3.5" />
            </div>
        </div>
    </x-card>
</a>