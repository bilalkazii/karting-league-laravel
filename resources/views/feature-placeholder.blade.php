@php
    $config = [
        'groups.index' => ['heading' => 'My groups', 'message' => 'Group list is coming in Phase 5.'],
        'races.index' => ['heading' => 'Races', 'message' => 'Race index is coming in Phase 5.'],
        'championship.index' => ['heading' => 'Championship', 'message' => 'Championship hub is coming in Phase 5.'],
        'settings' => ['heading' => 'Settings', 'message' => 'Settings beyond profile basics are deferred until preference storage exists. Manage your profile and account on the Account page.'],
        'race-setup' => ['heading' => 'Race setup', 'message' => 'The race weekend builder is coming in Phase 5.'],
    ];
    $key = request()->route()?->getName();
    $cfg = $config[$key] ?? ['heading' => 'Section', 'message' => 'Coming in Phase 5.'];
@endphp

<x-placeholder :heading="$cfg['heading']" :message="$cfg['message']" />