<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-8">
        <x-page-header eyebrow="New season" title="Start a season" description="Seasons carry your championship from opening round to trophy night." />

        @if ($groups->isEmpty())
            <x-empty-state title="Nothing to attach it to" description="You need to join a group as an organizer before you can start a season.">
                <a href="{{ route('groups.new') }}">
                    <x-button>
                        <x-lucide-users class="size-3.5" />Create a group
                    </x-button>
                </a>
            </x-empty-state>
        @else
            <x-card class="p-6">
                <form method="POST" action="{{ route('seasons.store') }}" class="space-y-6">
                    @csrf

                    <div class="space-y-2">
                        <label for="season-group" class="text-xs font-semibold">Group <span class="text-[var(--red)]">*</span></label>
                        <select
                            id="season-group"
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
                        <label for="season-name" class="text-xs font-semibold">Season name <span class="text-[var(--red)]">*</span></label>
                        <input
                            id="season-name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
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
                            <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                            <option value="active" @selected(old('status') === 'active')>Active</option>
                            <option value="completed" @selected(old('status') === 'completed')>Completed</option>
                            <option value="archived" @selected(old('status') === 'archived')>Archived</option>
                        </select>
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
                                value="{{ old('start_date') }}"
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
                                value="{{ old('end_date') }}"
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
                            <x-lucide-calendar-plus class="size-3.5" />Create season
                        </x-button>
                        <a href="{{ route('championship') }}">
                            <x-button type="button" variant="ghost">Cancel</x-button>
                        </a>
                    </div>
                </form>
            </x-card>
        @endif
    </div>
</x-app-layout>