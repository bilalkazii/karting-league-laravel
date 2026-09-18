@props(['heading' => 'Section', 'message' => ''])

<x-app-layout>
    <div class="space-y-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.2em] text-[var(--red-bright)]">Placeholder</p>
            <h1 class="mt-2 text-3xl font-black tracking-[-.05em]">{{ $heading }}</h1>
        </div>
        <div class="rounded-xl border border-[var(--line)] bg-[var(--panel)] p-6">
            <p class="text-sm text-[var(--muted)]">{{ $message }}</p>
        </div>
    </div>
</x-app-layout>