<?php

namespace App\Http\Controllers;

use App\Enums\DriverProfileVisibility;
use App\Enums\NotificationType;
use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $notificationEnabled = [];
        foreach (NotificationType::cases() as $type) {
            $notificationEnabled[$type->value] = $user->notificationEnabled($type);
        }

        return view('settings.index', [
            'notificationTypes' => NotificationType::cases(),
            'notificationEnabled' => $notificationEnabled,
            'visibilityOptions' => DriverProfileVisibility::cases(),
            'visibility' => $user->driverProfileVisibility(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        foreach (NotificationType::cases() as $type) {
            $enabled = filter_var(
                $data['notifications'][$type->value] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
            $user->setPreference($type->preferenceKey(), $enabled ? '1' : '0');
        }

        $user->setPreference(
            DriverProfileVisibility::preferenceKey(),
            $data['driver_profile_visibility'],
        );

        return redirect()
            ->route('settings')
            ->with('status', 'Settings saved.');
    }
}
