<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-xs font-black uppercase tracking-[.2em] text-[var(--muted)]">Driver pool</h2>
        <x-badge class="border-white/10 bg-white/5 font-mono text-[10px] text-[var(--muted)]">
            {{ $race->entries->where('confirmed', true)->count() }}/{{ $race->entries->count() }} confirmed
        </x-badge>
    </div>

    @if ($race->entries->isEmpty())
        <x-empty-state title="Nobody here yet" description="Open the Setup tab and add group members to the field." />
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($race->entries as $entry)
                <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-driver-avatar
                                :color="$entry->driver->avatar_color"
                                :textColor="$entry->driver->avatar_text_color"
                                :initials="$entry->driver->nickname"
                                size="md"
                            />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $entry->driver->display_name }}</p>
                                <p class="text-[10px] uppercase tracking-widest text-[var(--muted)]">
                                    Kart <span class="font-mono">{{ $entry->kart_number ?: '—' }}</span>
                                </p>
                            </div>
                        </div>
                        <x-result-status-badge :status="$entry->status" />
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <form method="POST" action="{{ route('races.entries.update', ['race' => $race, 'driver' => $entry->driver_id]) }}" class="flex-1">
                            @csrf
                            <input type="hidden" name="confirmed" value="1" />
                            <x-button type="submit" variant="secondary" size="sm" class="w-full"
                                      :data-checked="$entry->confirmed">
                                <x-lucide-check class="size-3.5" />{{ $entry->confirmed ? 'Confirmed' : 'Confirm' }}
                            </x-button>
                        </form>
                        <form method="POST" action="{{ route('races.entries.ready', ['race' => $race, 'driver' => $entry->driver_id]) }}" class="flex-1">
                            @csrf
                            <x-button type="submit" variant="secondary" size="sm" class="w-full"
                                      :data-checked="$entry->ready"
                                      :disabled="! $entry->confirmed">
                                <x-lucide-flag class="size-3.5" />{{ $entry->ready ? 'Ready' : 'Mark ready' }}
                            </x-button>
                        </form>
                        @if ($currentDriver && $currentEntry && $currentEntry->driver_id === $entry->driver_id && in_array($status, ['draft', 'lobby'], true) && ! $entry->confirmed)
                            <x-badge class="border-[var(--amber)]/30 bg-[var(--amber)]/10 text-[var(--amber)]">Waiting on you</x-badge>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>