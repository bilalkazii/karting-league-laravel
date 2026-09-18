@props(['group'])

@php
    $current = request()->route()?->getName();

    $tabs = [
        ['label' => 'Overview', 'route' => 'groups.show', 'groupScoped' => true],
        ['label' => 'Members', 'route' => 'groups.members', 'groupScoped' => true],
        ['label' => 'Races', 'route' => 'races', 'groupScoped' => false],
        ['label' => 'Championship', 'route' => 'championship', 'groupScoped' => false],
        ['label' => 'Chat', 'route' => 'chat', 'groupScoped' => false],
        ['label' => 'Settings', 'route' => 'settings', 'groupScoped' => false],
    ];
@endphp

<nav class="flex gap-1 overflow-x-auto border-b border-[var(--line)]" aria-label="Group navigation">
    @foreach ($tabs as $tab)
        @php $active = $current === $tab['route']; @endphp
        <a
            href="{{ route($tab['route'], $tab['groupScoped'] ? $group : []) }}"
            class="-mb-px shrink-0 border-b-2 px-4 py-3 text-xs font-semibold transition {{ $active ? 'border-[var(--red)] text-white' : 'border-transparent text-[var(--muted)] hover:text-white' }}"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>