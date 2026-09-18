@props(['title' => '', 'description' => ''])

<x-card class="carbon flex flex-col items-center gap-4 p-10 text-center">
    <span class="grid size-12 place-items-center rounded-xl bg-white/5 text-[var(--muted)]">
        @isset($icon)
            {{ $icon }}
        @else
            <x-lucide-inbox class="size-[22px]" />
        @endisset
    </span>
    <div>
        <p class="text-sm font-semibold">{{ $title }}</p>
        @if ($description)
            <p class="mt-1 max-w-xs text-xs text-[var(--muted)]">{{ $description }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div>{{ $slot }}</div>
    @endif
</x-card>