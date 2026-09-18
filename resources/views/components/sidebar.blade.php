@props(['driver' => null, 'groups' => []])

@php
    $navigation = [
        ['label' => 'Dashboard', 'href' => 'dashboard', 'icon' => 'home'],
        ['label' => 'My groups', 'href' => 'groups', 'icon' => 'users'],
        ['label' => 'Races', 'href' => 'races', 'icon' => 'flag'],
        ['label' => 'Championship', 'href' => 'championship', 'icon' => 'trophy'],
        ['label' => 'Drivers', 'href' => 'drivers', 'icon' => 'gauge'],
    ];
    $current = request()->route()?->getName();
@endphp

<div x-data="{ open: false }">
    <button
        x-cloak
        x-show="open"
        @open-sidebar.window="open = true"
        x-on:click="open = false"
        class="fixed inset-0 z-40 bg-black/60 lg:hidden"
        aria-label="Close navigation overlay"
    ></button>

    <aside
        @open-sidebar.window="open = true"
        @keydown.escape.window="open = false"
        class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-[var(--line)] bg-[#0b1013]/95 px-4 py-6 backdrop-blur-xl transition-transform duration-300 lg:translate-x-0"
        :class="open ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
    >
        <div class="flex items-center justify-between px-2">
            <x-brand />
            <button class="text-[var(--muted)] lg:hidden" x-on:click="open = false" aria-label="Close navigation">
                <x-lucide-x class="size-5" />
            </button>
        </div>

        <div class="mt-11 px-2 text-[10px] font-bold uppercase tracking-[.2em] text-[#66727a]">Your paddock</div>

        <nav class="mt-3 space-y-1">
            @foreach ($navigation as $item)
                @php
                    $active = $current === $item['href'] || str_starts_with($current ?? '', $item['href']);
                @endphp
                <a
                    href="{{ route($item['href']) }}"
                    x-on:click="open = false"
                    @class([
                        'flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition',
                        $active ? 'bg-[var(--red)]/10 text-white ring-1 ring-[var(--red)]/20' : 'text-[var(--muted)] hover:bg-white/5 hover:text-white',
                    ])
                >
                    <x-dynamic-component :component="'lucide-'.$item['icon']" :class="'size-[18px] '.($active ? 'text-[var(--red-bright)]' : '')" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="mt-auto space-y-1">
            <a x-on:click="open = false" href="{{ $driver ? route('drivers.show', $driver) : route('profile') }}" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm text-[var(--muted)] hover:bg-white/5 hover:text-white">
                <x-lucide-circle-user-round class="size-[18px]" />Profile
            </a>
            <a x-on:click="open = false" href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm text-[var(--muted)] hover:bg-white/5 hover:text-white">
                <x-lucide-settings class="size-[18px]" />Account
            </a>
            @if ($driver)
                <div class="mt-5 flex items-center gap-3 border-t border-[var(--line)] px-3 pt-5">
                    <div class="grid size-8 place-items-center rounded-full text-xs font-bold" style="background-color: {{ $driver->avatar_color }}; color: {{ $driver->avatar_text_color }}">
                        {{ $driver->nickname }}
                    </div>
                    <div>
                        <p class="text-xs font-semibold">{{ $driver->profile?->full_name ?? $driver->nickname }}</p>
                        <p class="text-[10px] text-[var(--muted)]">KART {{ str_pad((string) ($driver->racing_number ?? ''), 2, '0', STR_PAD_LEFT) }}</p>
                    </div>
                    <x-lucide-chevron-right class="ml-auto size-3.5 text-[var(--muted)]" />
                </div>
            @endif
        </div>
    </aside>
</div>