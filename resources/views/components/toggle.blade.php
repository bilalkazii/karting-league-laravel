@props(['name', 'id' => null, 'checked' => false, 'value' => '1'])

@php
    $id = $id ?? 'toggle-'.md5($name);
@endphp

<label for="{{ $id }}" class="relative inline-flex shrink-0 cursor-pointer items-center">
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        id="{{ $id }}"
        type="checkbox"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked($checked)
        class="peer sr-only"
    >
    <span class="h-5 w-9 rounded-full border border-[var(--line)] bg-white/5 transition peer-checked:border-[var(--red)] peer-checked:bg-[var(--red)] peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--red)] peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-[var(--background)]"></span>
    <span class="pointer-events-none absolute left-0.5 top-0.5 size-4 rounded-full bg-white shadow transition peer-checked:translate-x-4"></span>
</label>
