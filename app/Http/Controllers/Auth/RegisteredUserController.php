<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\User;
use App\Services\InviteService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view. Registration is invitation-only: a valid
     * pending invitation token unlocks the form, otherwise the visitor is told
     * how to get one (public self-registration is disabled).
     */
    public function create(Request $request): View
    {
        $invite = $this->inviteFor((string) $request->query('invite', ''));
        $valid = $invite !== null && $invite->isPending() && ! $invite->isExpired();

        return view('auth.register', [
            'inviteToken' => $valid ? (string) $request->query('invite', '') : null,
            'inviteEmail' => $valid ? $invite->email : null,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $invite = $this->inviteFor((string) $request->input('invite', ''));

        if ($invite === null || ! $invite->isPending() || $invite->isExpired()) {
            throw ValidationException::withMessages([
                'invite' => 'A valid invitation is required to register.',
            ]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if ($invite->email !== null && strtolower((string) $request->input('email')) !== $invite->email) {
            throw ValidationException::withMessages([
                'email' => 'This invitation was issued for a different email address.',
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function inviteFor(string $token): ?Invite
    {
        if ($token === '') {
            return null;
        }

        return app(InviteService::class)->findByToken($token);
    }
}
