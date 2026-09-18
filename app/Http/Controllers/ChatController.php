<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\Group;
use App\Models\Race;
use App\Services\ChatService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChatController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ChatService $chats) {}

    public function index(): View
    {
        $driver = auth()->user()?->driver;

        $rooms = $driver
            ? $this->chats->groupRoomsFor($driver)
            : [];

        return view('chat.index', [
            'rooms' => $rooms,
            'totalUnread' => $driver ? $this->chats->unreadForDriver($driver) : 0,
        ]);
    }

    public function group(Group $group): View
    {
        $this->authorize('view', $group);

        $driver = auth()->user()->driver;
        $driver?->chatLastReads()->updateOrCreate(
            ['group_id' => $group->id],
            ['last_read_at' => now()]
        );

        return view('chat.show', [
            'roomType' => 'group',
            'roomTitle' => $group->name,
            'roomSubtitle' => 'Group chat · '.$group->memberDrivers()->count().' member'.($group->memberDrivers()->count() === 1 ? '' : 's'),
            'backUrl' => route('groups.show', $group),
            'backLabel' => 'Back to group',
            'sendUrl' => route('chat.group.send', $group),
            'messages' => $this->chats->threadMessages($group),
            'canSend' => $driver !== null,
        ]);
    }

    public function race(Race $race): View
    {
        $this->authorize('view', $race);

        $driver = auth()->user()->driver;
        $driver?->chatLastReads()->updateOrCreate(
            ['race_id' => $race->id],
            ['last_read_at' => now()]
        );

        return view('chat.show', [
            'roomType' => 'race',
            'roomTitle' => $race->name,
            'roomSubtitle' => 'Race chat · '.$race->venue_name.' · '.$race->date?->format('d M Y').' '.$race->start_time,
            'backUrl' => route('races.show', $race),
            'backLabel' => 'Back to race',
            'sendUrl' => route('chat.race.send', $race),
            'messages' => $this->chats->threadMessages($race),
            'canSend' => $driver !== null,
        ]);
    }

    public function storeGroup(StoreChatMessageRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('view', $group);

        $this->chats->storeMessage($request->user()->driver, $request->input('body'), $group);

        return redirect()->route('chat.group', $group);
    }

    public function storeRace(StoreChatMessageRequest $request, Race $race): RedirectResponse
    {
        $this->authorize('view', $race);

        $this->chats->storeMessage($request->user()->driver, $request->input('body'), null, $race);

        return redirect()->route('chat.race', $race);
    }

    public function destroy(ChatMessage $message): RedirectResponse
    {
        abort_unless($message->sender_id === auth()->user()?->driver?->id, 403);

        $message->delete();

        return back();
    }
}
