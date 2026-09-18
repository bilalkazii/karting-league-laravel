<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(15);

        $unreadCount = $request->user()->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(Request $request, DatabaseNotification $notification)
    {
        abort_unless(
            $request->user()->notifications()->whereKey($notification->getKey())->exists(),
            404
        );

        $notification->markAsRead();

        return redirect()->route('notifications');
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return redirect()->route('notifications');
    }
}
