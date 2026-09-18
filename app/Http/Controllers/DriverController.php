<?php

namespace App\Http\Controllers;

use App\Models\Driver;

class DriverController extends Controller
{
    public function show(Driver $driver)
    {
        $driver->load('profile', 'groups', 'teams', 'entries');

        $stats = [
            'starts' => $driver->entries->whereNotNull('finish_position')->count(),
            'wins' => $driver->entries->where('finish_position', 1)->count(),
            'podiums' => $driver->entries->whereIn('finish_position', [1, 2, 3])->count(),
            'poles' => $driver->entries->where('grid_position', 1)->count(),
        ];

        $recentEntries = $driver->entries()
            ->with('race')
            ->whereNotNull('finish_position')
            ->orderByDesc('race_id')
            ->limit(8)
            ->get();

        return view('drivers.profile', compact('driver', 'stats', 'recentEntries'));
    }
}