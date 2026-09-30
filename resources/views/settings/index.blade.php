<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-8">
        <x-page-header eyebrow="Your preferences" title="Settings" description="Choose which notifications you receive and who can view your driver profile." />

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

        <form method="POST" action="{{ route('settings.update') }}" class="space-y-6" novalidate>
            @csrf

            <x-card class="p-6">
                <div class="flex items-center gap-3">
                    <x-lucide-bell class="size-5 text-[var(--red-bright)]" />
                    <div>
                        <h2 class="text-sm font-bold">Notifications</h2>
                        <p class="text-xs text-[var(--muted)]">Mute the in-app alerts you do not need. Everything is on by default.</p>
                    </div>
                </div>

                <div class="mt-5 divide-y divide-[var(--line)]">
                    @foreach ($notificationTypes as $type)
                        <div class="flex items-start justify-between gap-4 py-4">
                            <div class="flex items-start gap-3">
                                <x-dynamic-component :component="'lucide-'.$type->icon()" class="mt-0.5 size-4 text-[var(--muted)]" />
                                <div>
                                    <p class="text-sm font-semibold">{{ $type->label() }}</p>
                                    <p class="mt-0.5 text-xs text-[var(--muted)]">{{ $type->description() }}</p>
                                </div>
                            </div>
                            <x-toggle
                                :name="'notifications['.$type->value.']'"
                                :checked="old('notifications.'.$type->value, $notificationEnabled[$type->value] ? '1' : '0') === '1'"
                            />
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card class="p-6">
                <div class="flex items-center gap-3">
                    <x-lucide-eye class="size-5 text-[var(--red-bright)]" />
                    <div>
                        <h2 class="text-sm font-bold">Privacy</h2>
                        <p class="text-xs text-[var(--muted)]">Control who can view your profile and stats.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($visibilityOptions as $option)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition @if ($visibility === $option) border-[var(--red)]/50 bg-[var(--red)]/5 @else border-[var(--line)] bg-white/[.02] hover:bg-white/5 @endif">
                            <input
                                type="radio"
                                name="driver_profile_visibility"
                                value="{{ $option->value }}"
                                @checked(old('driver_profile_visibility', $visibility->value) === $option->value)
                                class="mt-0.5 accent-[var(--red)]"
                            >
                            <span>
                                <span class="block text-sm font-semibold">{{ $option->label() }}</span>
                                <span class="mt-0.5 block text-xs text-[var(--muted)]">{{ $option->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                    @error('driver_profile_visibility')
                        <p class="flex items-center gap-1 text-[10px] text-[var(--red-bright)]"><x-lucide-alert-circle class="size-3" />{{ $message }}</p>
                    @enderror
                </div>
            </x-card>

            <div class="flex items-center gap-3 pt-2">
                <x-button type="submit">
                    <x-lucide-check class="size-3.5" />Save settings
                </x-button>
                <a href="{{ route('dashboard') }}">
                    <x-button variant="ghost" type="button">Cancel</x-button>
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
