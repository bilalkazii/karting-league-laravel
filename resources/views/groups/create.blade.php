<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-8">
        <x-page-header eyebrow="New group" title="Create a group" description="Start a private space for your karting crew." />

        <x-card class="p-6">
            <form method="POST" action="{{ route('groups.store') }}" class="space-y-6">
                @csrf

                <div class="space-y-2">
                    <label for="group-name" class="text-xs font-semibold">Group name <span class="text-[var(--red)]">*</span></label>
                    <input
                        id="group-name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="e.g. Karting Crew"
                        maxlength="40"
                        required
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                        class="w-full rounded-lg border bg-white/[.03] px-4 py-3 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] {{ $errors->has('name') ? 'border-[var(--red)]' : 'border-[var(--line)]' }}"
                    />
                    @error('name')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]">
                            <x-lucide-alert-circle class="size-[11px]" />{{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="space-y-2">
                    <label for="group-desc" class="text-xs font-semibold">Description</label>
                    <textarea
                        id="group-desc"
                        name="description"
                        placeholder="What's this group about?"
                        rows="3"
                        class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-4 py-3 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]">
                            <x-lucide-alert-circle class="size-[11px]" />{{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="submit">
                        <x-lucide-plus class="size-3.5" />Create group
                    </x-button>
                    <a href="{{ route('groups') }}">
                        <x-button type="button" variant="ghost">Cancel</x-button>
                    </a>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>