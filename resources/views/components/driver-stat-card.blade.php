@props(['label' => '', 'value' => '', 'detail' => '', 'accent' => false])

<div class="rounded-xl border border-[var(--line)] bg-[var(--panel)] p-5">
    <span @class([
        'grid size-9 place-items-center rounded-lg',
        $accent ? 'bg-[var(--red)]/10 text-[var(--red-bright)]' : 'bg-white/5 text-[var(--muted)]',
    ])>{{ $slot }}</span>
    <p class="mt-4 text-xs text-[var(--muted)]">{{ $label }}</p>
    <p class="mt-1 text-2xl font-black tracking-tight">{{ $value }}</p>
    @if ($detail)
        <p class="mt-1 text-xs text-[var(--green)]">{{ $detail }}</p>
    @endif
</div>