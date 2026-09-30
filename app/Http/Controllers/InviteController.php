<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInviteRequest;
use App\Mail\GroupInvitation;
use App\Models\Group;
use App\Models\Invite;
use App\Services\InviteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InviteController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly InviteService $invites)
    {
        //
    }

    public function store(StoreInviteRequest $request, Group $group): RedirectResponse
    {
        $result = $this->invites->create(
            $group,
            $request->user()->driver,
            $request->validated('email')
        );

        if ($result['invite']->email !== null) {
            Mail::to($result['invite']->email)->send(
                new GroupInvitation($group, $result['url'], $result['invite']->expires_at)
            );
        }

        return redirect()
            ->route('groups.members', $group)
            ->with('status', 'Invitation created. Copy the link and share it.')
            ->with('invite_url', Crypt::encryptString($result['url']));
    }

    public function destroy(Group $group, Invite $invite): RedirectResponse
    {
        $this->authorize('manageMembers', $group);
        abort_unless($invite->group_id === $group->id, 404);

        $this->invites->revoke($invite);

        return redirect()
            ->route('groups.members', $group)
            ->with('status', 'Invitation revoked.');
    }

    public function regenerate(Group $group, Invite $invite): RedirectResponse
    {
        $this->authorize('manageMembers', $group);
        abort_unless($invite->group_id === $group->id, 404);
        abort_unless($invite->isPending(), 404);

        $result = $this->invites->rotate($invite);

        return redirect()
            ->route('groups.members', $group)
            ->with('status', 'New invitation link generated.')
            ->with('invite_url', Crypt::encryptString($result['url']));
    }

    public function show(Request $request, string $token): View
    {
        $invite = $this->invites->findByToken($token);
        abort_if($invite === null, 404);

        $state = $this->invites->stateFor($invite, $request->user()?->driver);

        if ($state === 'pending' && $request->user() === null) {
            $request->session()->put('url.intended', route('invites.show', $token));
        }

        return view('invites.show', [
            'invite' => $invite,
            'group' => $invite->group,
            'state' => $state,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $result = $this->invites->accept($token, $request->user());

        return match ($result['status']) {
            'accepted', 'already_accepted' => redirect()
                ->route('groups.show', $result['invite']->group)
                ->with('status', 'You have joined '.$result['invite']->group->name.'.'),
            'invalid' => abort(404),
            'no_driver' => abort(403),
            default => redirect()
                ->route('invites.show', $token)
                ->with('invite_error', $result['status']),
        };
    }
}
