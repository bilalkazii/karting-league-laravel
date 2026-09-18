<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-8">
        <x-page-header eyebrow="Race setup" title="Edit race" description="Details stay editable while the race is still a draft or in the lobby." />

        <x-card class="p-6">
            <form method="POST" action="{{ route('races.update', $race) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="space-y-2">
                    <label for="race-name" class="text-xs font-semibold">Race name <span class="text-[var(--red)]">*</span></label>
                    <input
                        id="race-name"
                        type="text"
                        name="name"
                        value="{{ old('name', $race->name) }}"
                        maxlength="255"
                        required
                        class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white outline-none transition focus:border-[var(--red)] {{ $errors->has('name') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                    />
                    @error('name')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label for="race-venue" class="text-xs font-semibold">Venue <span class="text-[var(--red)]">*</span></label>
                    <input
                        id="race-venue"
                        type="text"
                        name="venue_name"
                        value="{{ old('venue_name', $race->venue_name) }}"
                        maxlength="255"
                        required
                        class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white outline-none transition focus:border-[var(--red)] {{ $errors->has('venue_name') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                    />
                    @error('venue_name')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="race-date" class="text-xs font-semibold">Date <span class="text-[var(--red)]">*</span></label>
                        <input
                            id="race-date"
                            type="date"
                            name="date"
                            value="{{ old('date', $race->date?->format('Y-m-d')) }}"
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
                            value="{{ old('start_time', $race->start_time) }}"
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
                        <label for="race-format" class="text-xs font-semibold">Format</label>
                        <select
                            id="race-format"
                            name="format"
                            class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] {{ $errors->has('format') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                        >
                            <option value="sprint" @selected(old('format', $race->format?->value ?? 'sprint') === 'sprint')>Sprint</option>
                            <option value="feature" @selected(old('format', $race->format?->value) === 'feature')>Feature</option>
                            <option value="custom" @selected(old('format', $race->format?->value) === 'custom')>Custom</option>
                        </select>
                        @error('format')
                            <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="space-y-2">
                        <label for="race-laps" class="text-xs font-semibold">Qualifying lap count</label>
                        <input
                            id="race-laps"
                            type="number"
                            name="qualifying_lap_count"
                            min="1"
                            max="10"
                            value="{{ old('qualifying_lap_count', $race->qualifying_lap_count) }}"
                            class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] {{ $errors->has('qualifying_lap_count') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                        />
                        @error('qualifying_lap_count')
                            <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="race-rules" class="text-xs font-semibold">Rules</label>
                    <textarea
                        id="race-rules"
                        name="rules"
                        rows="3"
                        class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] {{ $errors->has('rules') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                        placeholder="Race notes, track briefing…"
                    >{{ old('rules', $race->rules) }}</textarea>
                    @error('rules')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-[11px]" />{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit">
                        <x-lucide-save class="size-3.5" />Save changes
                    </x-button>
                    <a href="{{ route('races.show', $race) }}">
                        <x-button type="button" variant="ghost">Cancel</x-button>
                    </a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>