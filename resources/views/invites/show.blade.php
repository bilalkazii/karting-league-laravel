<x-invite-layout>
    <div class="rounded-2xl border border-[var(--line)] bg-[var(--panel)] p-6 sm:p-8">
        <div class="flex items-center gap-3">
            <span class="grid size-12 place-items-center rounded-xl text-sm font-black" style="background-color: {{ $group->logo_color }}; color: {{ $group->logo_text_color }}">{{ $group->logo_initials }}</span>
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-[var(--red-bright)]">Group invitation</p>
                <h1 class="truncate text-xl font-black tracking-[-.03em]">{{ $group->name }}</h1>
            </div>
        </div>

        @if ($state === 'pending')
            <p class="mt-5 text-sm text-[var(--muted)]">
                You've been invited to join <strong class="text-white">{{ $group->name }}</strong>.
                @if ($invite->inviter)
                    Invited by {{ $invite->inviter->profile?->full_name ?? $invite->inviter->nickname }}.
                @endif
                This invitation expires {{ $invite->expires_at?->format('d M Y') }}.
            </p>

            @auth
                <form action="{{ route('invites.accept', $token) }}" method="POST" class="mt-6">
                    @csrf
                    <x-button type="submit" class="w-full">
                        <x-lucide-user-plus class="size-4" />Accept invitation
                    </x-button>
                </form>
                <p class="mt-3 text-[10px] text-[var(--muted)]">Accepting adds you to this group as a member.</p>
            @else
                <p class="mt-5 text-sm text-[var(--muted)]">Sign in or create an account to accept.</p>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('login') }}" class="flex-1"><x-button class="w-full">Sign in</x-button></a>
                    <a href="{{ route('register', ['invite' => $token]) }}" class="flex-1"><x-button variant="secondary" class="w-full">Create account</x-button></a>
                </div>
                <p class="mt-3 text-[10px] text-[var(--muted)]">You'll return here after signing in to complete joining.</p>
            @endauth
        @elseif ($state === 'accepted')
            <div class="mt-5 flex items-start gap-2 rounded-lg border border-[var(--green)]/30 bg-[var(--green)]/10 px-4 py-3 text-sm text-[var(--green)]" role="status">
                <x-lucide-check-circle class="mt-0.5 size-4 shrink-0" />
                <p>You're already a member of {{ $group->name }}.</p>
            </div>
            <a href="{{ route('groups.show', $group) }}" class="mt-5 block"><x-button class="w-full">Open group</x-button></a>
        @else
            <div class="mt-5 flex items-start gap-2 rounded-lg border border-[var(--red)]/30 bg-[var(--red)]/10 px-4 py-3 text-sm text-[var(--red-bright)]" role="alert">
                <x-lucide-alert-circle class="mt-0.5 size-4 shrink-0" />
                <p>
                    @if ($state === 'expired')
                        This invitation has expired.
                    @elseif ($state === 'revoked')
                        This invitation was revoked.
                    @elseif ($state === 'used')
                        This invitation has already been used.
                    @else
                        This invitation is not valid.
                    @endif
                </p>
            </div>
            <p class="mt-4 text-xs text-[var(--muted)]">Ask a group admin or organizer for a new invitation link.</p>
        @endif
    </div>
</x-invite-layout>
