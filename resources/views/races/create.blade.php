<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-8">
        <x-page-header eyebrow="New race" title="Set up a race" description="Open a lobby, pull in your group, and send them into qualifying." />

        @if ($groups->isEmpty())
            <x-empty-state title="Nothing to organize under" description="You need to be an admin or organizer of a group before you can create a race.">
                <a href="{{ route('groups.new') }}">
                    <x-button>
                        <x-lucide-users class="size-3.5" />Create a group
                    </x-button>
                </a>
            </x-empty-state>
        @else
            <x-card class="p-6">
                <form method="POST" action="{{ route('races.store') }}" class="space-y-6">
                    @csrf

                    <div class="space-y-2">
                        <label for="race-name" class="text-xs font-semibold">Race name <span class="text-[var(--red)]">*</span></label>
                        <input
                            id="race-name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="Saturday GP"
                            maxlength="255"
                            required
                            class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('name') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                        />
                        @error('name')
                            <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]">
                                <x-lucide-alert-circle class="size-[11px]" />{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="race-group" class="text-xs font-semibold">Group <span class="text-[var(--red)]">*</span></label>
                        <select
                            id="race-group"
                            name="group_id"
                            required
                            class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('group_id') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                        >
                            <option value="" disabled @selected(old('group_id') === null)>Select a group…</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" @selected((string) old('group_id') === (string) $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @error('group_id')
                            <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]">
                                <x-lucide-alert-circle class="size-[11px]" />{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="race-venue" class="text-xs font-semibold">Venue <span class="text-[var(--red)]">*</span></label>
                        <input
                            id="race-venue"
                            type="text"
                            name="venue_name"
                            value="{{ old('venue_name') }}"
                            placeholder="Nashik Karting Arena"
                            maxlength="255"
                            required
                            class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('venue_name') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                        />
                        @error('venue_name')
                            <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]">
                                <x-lucide-alert-circle class="size-[11px]" />{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label for="race-date" class="text-xs font-semibold">Date <span class="text-[var(--red)]">*</span></label>
                            <input
                                id="race-date"
                                type="date"
                                name="date"
                                value="{{ old('date', now()->toDateString()) }}"
                                required
                                class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] {{ $errors->has('date') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                            />
                            @error('date')
                                <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="space-y-2">
                            <label for="race-time" class="text-xs font-semibold">Start time <span class="text-[var(--red)]">*</span></label>
                            <input
                                id="race-time"
                                type="time"
                                name="start_time"
                                value="{{ old('start_time', '16:00') }}"
                                required
                                class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] {{ $errors->has('start_time') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                            />
                            @error('start_time')
                                <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label for="race-format" class="text-xs font-semibold">Format <span class="text-[var(--red)]">*</span></label>
                            <select
                                id="race-format"
                                name="format"
                                required
                                class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('format') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                            >
                                <option value="sprint" @selected(old('format', 'sprint') === 'sprint')>Sprint — short shake-down, one qualifying lap</option>
                                <option value="feature" @selected(old('format') === 'feature')>Feature — main event of the day</option>
                                <option value="custom" @selected(old('format') === 'custom')>Custom — fully custom rules</option>
                            </select>
                            @error('format')
                                <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="space-y-2">
                            <label for="race-laps" class="text-xs font-semibold">Qualifying lap count <span class="text-[var(--red)]">*</span></label>
                            <input
                                id="race-laps"
                                type="number"
                                name="qualifying_lap_count"
                                min="1"
                                max="10"
                                required
                                value="{{ old('qualifying_lap_count', 1) }}"
                                class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('qualifying_lap_count') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                            />
                            @error('qualifying_lap_count')
                                <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <p class="text-[10px] text-[var(--muted)]">V1 measures official qualifying laps per driver.</p>

                    <div class="flex items-center gap-3 pt-2">
                        <x-button type="submit">
                            <x-lucide-flag class="size-3.5" />Create race
                        </x-button>
                        <a href="{{ route('races') }}">
                            <x-button type="button" variant="ghost">Cancel</x-button>
                        </a>
                    </div>
                </form>
            </x-card>
        @endif
    </div>
</x-app-layout>