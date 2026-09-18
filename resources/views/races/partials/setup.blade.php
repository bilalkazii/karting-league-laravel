@php
    $members = $race->group->members()->orderBy('nickname')->get();
    $entriesByDriver = $race->entries->keyBy('driver_id');
@endphp

<div class="grid gap-4 lg:grid-cols-2">
    <x-card class="p-5">
        <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Field</h2>
        <p class="mt-1 text-[10px] text-[var(--muted)]">Pick which group members join this race. Ticking a driver adds them to the lobby.</p>

        <form method="POST" action="{{ route('races.entries.set', $race) }}" class="mt-4 space-y-1.5">
            @csrf
            @foreach ($members as $member)
                @php $entry = $entriesByDriver->get($member->id); @endphp
                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2.5 transition {{ $entry ? 'border-[var(--red)]/30 bg-[var(--red)]/[.06]' : 'border-[var(--line)] bg-white/[.02] hover:bg-white/[.04]' }}">
                    <span class="flex min-w-0 items-center gap-3">
                        <x-driver-avatar
                            :color="$member->avatar_color"
                            :textColor="$member->avatar_text_color"
                            :initials="$member->nickname"
                            size="sm"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold">{{ $member->display_name }}</span>
                            <span class="block text-[10px] uppercase tracking-widest text-[var(--muted)]">
                                {{ $entry ? ucfirst($entry->status->value) : 'Not in field' }}
                            </span>
                        </span>
                    </span>
                    <input
                        type="checkbox"
                        name="driver_ids[]"
                        value="{{ $member->id }}"
                        @checked($entry !== null)
                        class="size-4 accent-[var(--red)]"
                    />
                </label>
            @endforeach

            <div class="flex justify-end pt-3">
                <x-button type="submit"><x-lucide-users class="size-3.5" />Save field</x-button>
            </div>
        </form>
    </x-card>

    <x-card class="p-5">
        <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Declarations</h2>
        <p class="mt-1 text-[10px] text-[var(--muted)]">Drivers mark themselves confirmed and ready in the lobby. Manual overrides:</p>

        <ul class="mt-4 space-y-1.5">
            @forelse ($race->entries as $entry)
                <li class="flex items-center justify-between gap-3 rounded-lg border border-[var(--line)] bg-white/[.02] px-3 py-2.5">
                    <span class="flex min-w-0 items-center gap-3">
                        <x-badge class="border-white/10 bg-white/5 font-mono text-xs font-bold">{{ $entry->kart_number ?: '—' }}</x-badge>
                        <span class="truncate text-sm font-semibold">{{ $entry->driver->display_name }}</span>
                    </span>
                    <span class="flex shrink-0 items-center gap-2">
                        <x-result-status-badge :status="$entry->status" />
                        <form method="POST" action="{{ route('races.entries.update', ['race' => $race, 'driver' => $entry->driver_id]) }}">
                            @csrf
                            <input type="hidden" name="confirmed" value="1" />
                            <x-button type="submit" variant="ghost" size="sm">{{ $entry->confirmed ? 'Confirmed' : 'Confirm' }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('races.entries.remove', ['race' => $race, 'driver' => $entry->driver_id]) }}"
                              onsubmit="return confirm('Remove {{ $entry->driver->display_name }} from this race?')">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" size="sm" class="text-[var(--muted)]">Remove</x-button>
                        </form>
                    </span>
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-[var(--line)] p-4 text-center text-xs text-[var(--muted)]">
                    No drivers in the field yet.
                </li>
            @endforelse
        </ul>
    </x-card>
</div>