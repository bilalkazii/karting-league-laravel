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

<nav class="fixed inset-x-0 bottom-0 z-20 flex h-16 items-center justify-around border-t border-[var(--line)] bg-[#0b1013]/95 px-2 backdrop-blur-xl lg:hidden">
    @foreach ($navigation as $item)
        @php
            $active = $current === $item['href'] || str_starts_with($current ?? '', $item['href']);
        @endphp
        <a href="{{ route($item['href']) }}" @class([
            'flex flex-col items-center gap-1 px-3 py-1 text-[10px]',
            $active ? 'text-[var(--red-bright)]' : 'text-[var(--muted)]',
        ])>
            <x-dynamic-component :component="'lucide-'.$item['icon']" class="size-[18px]" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>