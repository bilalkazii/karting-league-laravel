<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-8">
        <x-page-header eyebrow="Your profile" title="Edit profile" description="Update the details other drivers see across the paddock." />

        @if (session('status'))
            <div class="flex items-center gap-2 rounded-lg border border-[var(--green)]/30 bg-[var(--green)]/10 px-4 py-3 text-sm text-[var(--green)]" role="status">
                <x-lucide-check-circle class="size-4" />{{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="flex items-start gap-2 rounded-lg border border-[var(--red)]/30 bg-[var(--red)]/10 px-4 py-3 text-sm text-[var(--red-bright)]" role="alert">
                <x-lucide-alert-circle class="mt-0.5 size-4 shrink-0" />
                <div>
                    <p class="font-semibold">Please fix the errors below.</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('drivers.update', $driver) }}" class="space-y-6" novalidate>
            @csrf
            @method('PATCH')

            <div class="space-y-2">
                <label for="full_name" class="text-xs font-semibold">Full name <span class="text-[var(--red)]">*</span></label>
                <input
                    id="full_name"
                    type="text"
                    name="full_name"
                    value="{{ old('full_name', $driver->profile?->full_name ?? '') }}"
                    @error('full_name') aria-invalid="true" aria-describedby="full_name-error" @enderror
                    class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] @error('full_name') border-[var(--red)] @else border-[var(--line)] @enderror"
                />
                @error('full_name')
                    <p id="full_name-error" class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-3" />{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="nickname" class="text-xs font-semibold">Nickname</label>
                <input
                    id="nickname"
                    type="text"
                    name="nickname"
                    value="{{ old('nickname', $driver->nickname ?? '') }}"
                    placeholder="e.g. BD"
                    maxlength="20"
                    @error('nickname') aria-invalid="true" aria-describedby="nickname-error" @enderror
                    class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] @error('nickname') border-[var(--red)] @else border-[var(--line)] @enderror"
                />
                <p class="text-[10px] text-[var(--muted)]">Shown on your avatar. Stored in uppercase.</p>
                @error('nickname')
                    <p id="nickname-error" class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-3" />{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="racing_number" class="text-xs font-semibold">Racing number</label>
                <input
                    id="racing_number"
                    type="number"
                    name="racing_number"
                    value="{{ old('racing_number', $driver->racing_number ?? '') }}"
                    min="1"
                    max="999"
                    placeholder="e.g. 7"
                    @error('racing_number') aria-invalid="true" aria-describedby="racing_number-error" @enderror
                    class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] @error('racing_number') border-[var(--red)] @else border-[var(--line)] @enderror"
                />
                @error('racing_number')
                    <p id="racing_number-error" class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-3" />{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label for="avatar_color" class="text-xs font-semibold">Avatar color</label>
                    <div class="flex items-center gap-3">
                        <input
                            id="avatar_color"
                            type="color"
                            name="avatar_color"
                            value="{{ old('avatar_color', $driver->avatar_color ?? '#27272a') }}"
                            class="size-10 shrink-0 cursor-pointer rounded-lg border border-[var(--line)] bg-white/[.03] p-1"
                        />
                        <input
                            type="text"
                            value="{{ old('avatar_color', $driver->avatar_color ?? '#27272a') }}"
                            disabled
                            class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-4 py-3 font-mono text-xs text-[var(--muted)]"
                        />
                    </div>
                    @error('avatar_color')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-3" />{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label for="avatar_text_color" class="text-xs font-semibold">Avatar text color</label>
                    <div class="flex items-center gap-3">
                        <input
                            id="avatar_text_color"
                            type="color"
                            name="avatar_text_color"
                            value="{{ old('avatar_text_color', $driver->avatar_text_color ?? '#ffffff') }}"
                            class="size-10 shrink-0 cursor-pointer rounded-lg border border-[var(--line)] bg-white/[.03] p-1"
                        />
                        <input
                            type="text"
                            value="{{ old('avatar_text_color', $driver->avatar_text_color ?? '#ffffff') }}"
                            disabled
                            class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-4 py-3 font-mono text-xs text-[var(--muted)]"
                        />
                    </div>
                    @error('avatar_text_color')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-3" />{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-button type="submit">
                    <x-lucide-check class="size-3.5" />Save changes
                </x-button>
                <a href="{{ route('drivers.show', $driver) }}">
                    <x-button variant="ghost" type="button">Cancel</x-button>
                </a>
            </div>
        </form>
    </div>
</x-app-layout>