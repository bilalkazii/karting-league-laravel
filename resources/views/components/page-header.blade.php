@props(['eyebrow' => '', 'title' => '', 'description' => ''])

<div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.22em] text-[var(--red-bright)]">{{ $eyebrow }}</p>
        <h1 class="mt-3 text-3xl font-black tracking-[-.05em] sm:text-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-xl text-sm text-[var(--muted)]">{{ $description }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>