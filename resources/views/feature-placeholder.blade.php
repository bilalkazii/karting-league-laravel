@php
    $config = [
        'groups.index' => ['heading' => 'My groups', 'message' => 'Group list is coming in Phase 5.'],
        'races.index' => ['heading' => 'Races', 'message' => 'Race index is coming in Phase 5.'],
        'championship.index' => ['heading' => 'Championship', 'message' => 'Championship hub is coming in Phase 5.'],
        'chat' => ['heading' => 'Chat', 'message' => 'Group chat and race chat are coming in Phase 5.'],
        'notifications' => ['heading' => 'Notifications', 'message' => 'Notifications are coming in Phase 5.'],
        'settings' => ['heading' => 'Settings', 'message' => 'Account settings are coming in Phase 5.'],
        'race-setup' => ['heading' => 'Race setup', 'message' => 'The race weekend builder is coming in Phase 5.'],
    ];
    $key = request()->route()?->getName();
    $cfg = $config[$key] ?? ['heading' => 'Section', 'message' => 'Coming in Phase 5.'];
@endphp

<x-placeholder :heading="$cfg['heading']" :message="$cfg['message']" />