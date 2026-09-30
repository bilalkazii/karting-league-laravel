<x-app-layout>
    <div class="space-y-6 pb-10">
        <x-group-header :group="$group" :memberCount="$memberCount" />
        <x-group-tabs :group="$group" />

        @if (session('status'))
            <div class="flex items-center gap-2 rounded-lg border border-[var(--green)]/30 bg-[var(--green)]/10 px-4 py-3 text-sm text-[var(--green)]" role="status">
                <x-lucide-check-circle class="size-4" />{{ session('status') }}
            </div>
        @endif

        <div x-data="{ search: '', roleFilter: 'all', addOpen: false, inviteOpen: false, driverId: '' }" class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-[var(--muted)]">{{ $memberCount }} drivers in this group</p>
                @if ($isOrganizer)
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="inviteOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-[var(--line)] bg-[var(--panel-raised)] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#1c272d]"
                        >
                            <x-lucide-link class="size-[13px]" />Invite driver
                        </button>
                        <button
                            type="button"
                            @click="addOpen = true"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-[var(--red)] px-4 py-2 text-xs font-bold text-white shadow-lg shadow-[var(--red)]/20 transition hover:bg-[var(--red-bright)]"
                        >
                            <x-lucide-user-plus class="size-[13px]" />Add member
                        </button>
                    </div>
                @endif
            </div>

            @if ($inviteUrl)
                @php $inviteCode = \Illuminate\Support\Str::afterLast($inviteUrl, '/'); @endphp
                <div x-data="{ copiedUrl: false, copiedCode: false }" class="rounded-xl border border-[var(--red)]/30 bg-[var(--red)]/5 p-4" role="status">
                    <div class="flex items-center gap-2 text-xs font-bold text-[var(--red-bright)]">
                        <x-lucide-link class="size-4" />Invitation created — copy and share it
                    </div>
                    <p class="mt-1 text-[10px] text-[var(--muted)]">Shown once for security (only a hash is stored). Use “Regenerate link” on a pending invite to issue a new one.</p>
                    <div class="mt-3 flex items-center gap-2 rounded-lg border border-[var(--line)] bg-white/[.03] p-3">
                        <x-lucide-link-2 class="size-[15px] shrink-0 text-[var(--muted)]" />
                        <p class="min-w-0 flex-1 truncate font-mono text-xs">{{ $inviteUrl }}</p>
                        <button type="button" @click="navigator.clipboard.writeText(@js($inviteUrl)).then(() => { copiedUrl = true; setTimeout(() => copiedUrl = false, 2000) })" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--line)] px-3 py-1.5 text-[10px] font-semibold text-[var(--muted)] transition hover:text-white">
                            <x-lucide-copy x-show="!copiedUrl" class="size-3.5" />
                            <x-lucide-check x-show="copiedUrl" x-cloak class="size-3.5 text-[var(--green)]" />
                            <span x-text="copiedUrl ? 'Copied' : 'Copy link'"></span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-center gap-2 rounded-lg border border-[var(--line)] bg-white/[.03] p-3">
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider text-[var(--muted)]">Code</span>
                        <p class="min-w-0 flex-1 truncate font-mono text-xs font-bold tracking-tight">{{ $inviteCode }}</p>
                        <button type="button" @click="navigator.clipboard.writeText(@js($inviteCode)).then(() => { copiedCode = true; setTimeout(() => copiedCode = false, 2000) })" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--line)] px-3 py-1.5 text-[10px] font-semibold text-[var(--muted)] transition hover:text-white">
                            <x-lucide-copy x-show="!copiedCode" class="size-3.5" />
                            <x-lucide-check x-show="copiedCode" x-cloak class="size-3.5 text-[var(--green)]" />
                            <span x-text="copiedCode ? 'Copied' : 'Copy code'"></span>
                        </button>
                    </div>
                </div>
            @endif

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative max-w-sm flex-1">
                    <x-lucide-search class="absolute left-3 top-1/2 size-[15px] -translate-y-1/2 text-[var(--muted)]" />
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Search members..."
                        class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] py-2.5 pl-9 pr-4 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                        aria-label="Search members"
                    />
                </div>
                <div class="flex gap-1" role="radiogroup" aria-label="Filter by role">
                    @foreach ([
                        ['value' => 'all', 'label' => 'All'],
                        ['value' => 'admin', 'label' => 'Admins'],
                        ['value' => 'organizer', 'label' => 'Organizers'],
                        ['value' => 'member', 'label' => 'Members'],
                    ] as $filter)
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="roleFilter === '{{ $filter['value'] }}'"
                            @click="roleFilter = '{{ $filter['value'] }}'"
                            :class="roleFilter === '{{ $filter['value'] }}' ? 'bg-[var(--red)]/10 text-[var(--red-bright)] ring-1 ring-[var(--red)]/20' : 'text-[var(--muted)] hover:bg-white/5'"
                            class="rounded-lg px-3 py-1.5 text-[10px] font-semibold transition"
                        >{{ $filter['label'] }}</button>
                    @endforeach
                </div>
            </div>

            @if ($members->isEmpty())
                <x-empty-state title="No members found" description="Try a different search or filter." />
            @else
                <div class="space-y-2">
                    @foreach ($members as $member)
                        @php
                            $isSelf = $member->id === auth()->user()?->driver?->id;
                            $showAdmin = ! $isSelf && $isOrganizer;
                            $canManageThis = $showAdmin && ($canManageRoles || $member->pivot->role !== 'admin');
                        @endphp
                        <div
                            data-member-row
                            x-show="(roleFilter === 'all' || roleFilter === '{{ $member->pivot->role }}')
                                && (search === ''
                                    || {{ json_encode(strtolower($member->profile?->full_name ?? $member->nickname)) }}.includes(search.toLowerCase())
                                    || {{ json_encode(strtolower($member->nickname)) }}.includes(search.toLowerCase()))"
                        >
                            <x-member-card :member="$member">
                                @if ($showAdmin)
                                    <div class="flex items-center gap-1">
                                        @if ($canManageThis)
                                            <form action="{{ route('groups.members.role', [$group, $member]) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <select
                                                    name="role"
                                                    aria-label="Role for {{ $member->profile?->full_name ?? $member->nickname }}"
                                                    onchange="this.form.submit()"
                                                    class="rounded-lg border border-[var(--line)] bg-white/[.03] px-2 py-1.5 text-[10px] font-semibold text-[var(--muted)] outline-none transition hover:border-[var(--red)]/40 hover:text-white"
                                                >
                                                    @foreach (['admin', 'organizer', 'member'] as $role)
                                                        <option value="{{ $role }}" @selected($member->pivot->role === $role)>{{ ucfirst($role) }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @endif
                                        @if ($canManageThis)
                                            <form action="{{ route('groups.members.destroy', [$group, $member]) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="grid size-8 place-items-center rounded-lg border border-[var(--line)] text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-[var(--red-bright)]" title="Remove {{ $member->profile?->full_name ?? $member->nickname }}" aria-label="Remove driver {{ $member->id }}">
                                                    <x-lucide-user-x class="size-3.5" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </x-member-card>
                        </div>
                    @endforeach
                </div>

                <div
                    x-cloak
                    x-show="(search !== '' || roleFilter !== 'all') && [...document.querySelectorAll('[data-member-row]')].every(el => el.style.display === 'none')"
                >
                    <x-empty-state title="No members found" description="Try a different search or filter." />
                </div>
            @endif

            @if ($isOrganizer)
                <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)]">
                    <div class="flex items-center justify-between border-b border-[var(--line)] px-5 py-3">
                        <p class="text-xs font-bold">Pending invitations</p>
                        <span class="text-[10px] text-[var(--muted)]">{{ $pendingInvites->count() }}</span>
                    </div>
                    @if ($pendingInvites->isEmpty())
                        <p class="px-5 py-4 text-xs text-[var(--muted)]">No pending invitations. Use “Invite driver” to create a shareable link.</p>
                    @else
                        <div class="divide-y divide-[var(--line)]">
                            @foreach ($pendingInvites as $pending)
                                <div class="flex items-center justify-between gap-3 px-5 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold">{{ $pending->email ?? 'Shareable link' }}</p>
                                        <p class="text-[10px] text-[var(--muted)]">
                                            Expires {{ $pending->expires_at?->format('d M Y') }}
                                            @if ($pending->inviter)
                                                · by {{ $pending->inviter->profile?->full_name ?? $pending->inviter->nickname }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <form action="{{ route('groups.invites.regenerate', [$group, $pending]) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="grid size-8 place-items-center rounded-lg border border-[var(--line)] text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-white" title="Regenerate link" aria-label="Regenerate invitation link">
                                                <x-lucide-refresh-cw class="size-3.5" />
                                            </button>
                                        </form>
                                        <form action="{{ route('groups.invites.destroy', [$group, $pending]) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="grid size-8 place-items-center rounded-lg border border-[var(--line)] text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-[var(--red-bright)]" title="Revoke invitation" aria-label="Revoke invitation">
                                                <x-lucide-x class="size-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="text-[10px] text-[var(--muted)]">
                <p>Organizers can add and remove members. Changing roles is restricted to group admins, and a group must always keep at least one admin.</p>
            </div>
        </div>

        @if ($isOrganizer)
            <div
                x-cloak
                x-show="inviteOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"
                @keydown.escape.window="inviteOpen = false"
                role="dialog"
                aria-modal="true"
                aria-label="Invite driver"
            >
                <form action="{{ route('groups.invites.store', $group) }}" method="POST" class="w-full max-w-md rounded-2xl border border-[var(--line)] bg-[#0c0e11] p-6" @click.outside="inviteOpen = false">
                    @csrf
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold">Invite driver</h2>
                        <button type="button" @click="inviteOpen = false" class="grid size-8 place-items-center rounded-lg text-[var(--muted)] hover:bg-white/5 hover:text-white" aria-label="Close">
                            <x-lucide-x class="size-4" />
                        </button>
                    </div>
                    <div class="mt-4 space-y-4">
                        <div class="space-y-1.5">
                            <label for="invite_email" class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted)]">Email (optional)</label>
                            <input
                                id="invite_email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="driver@example.com"
                                @error('email') aria-invalid="true" @enderror
                                class="w-full rounded-lg border bg-white/[.03] px-3 py-2.5 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] @error('email') border-[var(--red)] @else border-[var(--line)] @enderror"
                            />
                            @error('email')
                                <p class="text-[10px] text-[var(--red-bright)]">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="text-[10px] text-[var(--muted)]">Leave blank to create a shareable link. Add an email to also send the invitation. Invitations grant member access only, must be accepted while signed in, and work once.</p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="inviteOpen = false" class="rounded-lg border border-[var(--line)] px-4 py-2 text-xs font-semibold text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-white">Cancel</button>
                            <button type="submit" class="rounded-xl bg-[var(--red)] px-4 py-2 text-xs font-bold text-white shadow-lg shadow-[var(--red)]/20 transition hover:bg-[var(--red-bright)]">Create invite</button>
                        </div>
                    </div>
                </form>
            </div>

            <div
                x-cloak
                x-show="addOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-4"
                @keydown.escape.window="addOpen = false"
                role="dialog"
                aria-modal="true"
                aria-label="Add member"
            >
                <form action="{{ route('groups.members.store', $group) }}" method="POST" class="w-full max-w-md rounded-2xl border border-[var(--line)] bg-[#0c0e11] p-6" @click.outside="addOpen = false">
                    @csrf
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold">Add member</h2>
                        <button type="button" @click="addOpen = false" class="grid size-8 place-items-center rounded-lg text-[var(--muted)] hover:bg-white/5 hover:text-white" aria-label="Close">
                            <x-lucide-x class="size-4" />
                        </button>
                    </div>

                    @if ($availableDrivers->isEmpty())
                        <p class="mt-4 text-sm text-[var(--muted)]">Every driver is already a member of this group.</p>
                    @else
                        <div class="mt-4 space-y-4">
                            <div class="space-y-1.5">
                                <label for="driver_id" class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted)]">Driver</label>
                                <select id="driver_id" name="driver_id" x-model="driverId" required class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2.5 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]">
                                    <option value="" disabled>Choose a driver…</option>
                                    @foreach ($availableDrivers as $driver)
                                        <option value="{{ $driver->id }}" class="bg-[#0c0e11]">
                                            {{ $driver->nickname ? $driver->nickname.' · ' : '' }}{{ $driver->profile?->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="addOpen = false" class="rounded-lg border border-[var(--line)] px-4 py-2 text-xs font-semibold text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-white">Cancel</button>
                                <button type="submit" class="rounded-xl bg-[var(--red)] px-4 py-2 text-xs font-bold text-white shadow-lg shadow-[var(--red)]/20 transition hover:bg-[var(--red-bright)]">Add to group</button>
                            </div>
                        </div>
                    @endif
                </form>
            </div>
        @endif
    </div>
</x-app-layout>