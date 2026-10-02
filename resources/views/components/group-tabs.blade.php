@props(['group'])

@php
    $current = request()->route()?->getName();

    $tabs = [
        ['label' => 'Overview', 'route' => 'groups.show', 'groupScoped' => true],
        ['label' => 'Members', 'route' => 'groups.members', 'groupScoped' => true],
        ['label' => 'Teams', 'route' => 'groups.teams', 'groupScoped' => true],
        ['label' => 'Chat', 'route' => 'chat.group', 'groupScoped' => true],
        ['label' => 'Races', 'route' => 'races', 'groupScoped' => false],
        ['label' => 'Championship', 'route' => 'championship', 'groupScoped' => false],
        ['label' => 'Drivers', 'route' => 'drivers', 'groupScoped' => false],
    ];

    // Importing is group management, so the tab is only offered to the same
    // people the import routes authorise. Offering it to everyone would just
    // lead to a 403.
    $canImport = auth()->user()?->can('update', $group) ?? false;

    if ($canImport) {
        $tabs[] = ['label' => 'Import', 'route' => 'groups.imports.index', 'groupScoped' => true];
    }
@endphp

<nav class="flex gap-1 overflow-x-auto border-b border-[var(--line)]" aria-label="Group navigation">
    @foreach ($tabs as $tab)
        @php
            // The index and the review screen are both part of this tab.
            $active = $current === $tab['route']
                || ($tab['route'] === 'groups.imports.index' && str_starts_with((string) $current, 'groups.imports.'));
        @endphp
        <a
            href="{{ route($tab['route'], $tab['groupScoped'] ? $group : []) }}"
            @if ($active) aria-current="page" @endif
            class="-mb-px shrink-0 border-b-2 px-4 py-3 text-xs font-semibold transition {{ $active ? 'border-[var(--red)] text-white' : 'border-transparent text-[var(--muted)] hover:text-white' }}"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>