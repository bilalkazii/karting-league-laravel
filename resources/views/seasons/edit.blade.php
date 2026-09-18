<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-8">
        <x-page-header eyebrow="Manage season" title="Edit season" description="Adjust dates and status — the group a season belongs to is fixed." />

        <x-card class="p-6">
            <form method="POST" action="{{ route('seasons.update', $season) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="space-y-2">
                    <label for="season-name" class="text-xs font-semibold">Season name <span class="text-[var(--red)]">*</span></label>
                    <input
                        id="season-name"
                        type="text"
                        name="name"
                        value="{{ old('name', $season->name) }}"
                        placeholder="e.g. Crew Championship 2026"
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
                    <label for="season-status" class="text-xs font-semibold">Status <span class="text-[var(--red)]">*</span></label>
                    <select
                        id="season-status"
                        name="status"
                        required
                        class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('status') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                    >
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $season->status->value) === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-[var(--muted)]">
                        Draft → Active/Completed · Active → Completed/Archived · Completed → Archived
                    </p>
                    @error('status')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]">
                            <x-lucide-alert-circle class="size-[11px]" />{{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="season-start" class="text-xs font-semibold">Start date</label>
                        <input
                            id="season-start"
                            type="date"
                            name="start_date"
                            value="{{ old('start_date', $season->start_date?->format('Y-m-d')) }}"
                            class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('start_date') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                        />
                        @error('start_date')
                            <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]">
                                <x-lucide-alert-circle class="size-[11px]" />{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="season-end" class="text-xs font-semibold">End date</label>
                        <input
                            id="season-end"
                            type="date"
                            name="end_date"
                            value="{{ old('end_date', $season->end_date?->format('Y-m-d')) }}"
                            class="w-full rounded-lg border bg-[var(--panel-raised)] px-4 py-3 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('end_date') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                        />
                        @error('end_date')
                            <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]">
                                <x-lucide-alert-circle class="size-[11px]" />{{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit">
                        <x-lucide-check class="size-3.5" />Save changes
                    </x-button>
                    <a href="{{ route('championship.show', $season) }}">
                        <x-button type="button" variant="ghost">Cancel</x-button>
                    </a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>