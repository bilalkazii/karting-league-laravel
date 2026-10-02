@props(['event'])

@php
    $label = $event['round'] ? 'R'.$event['round'] : null;
@endphp

<th scope="col" class="min-w-[4.5rem] px-3 py-3 text-right align-bottom">
    <span class="block truncate text-[10px] uppercase tracking-widest text-[var(--red-bright)]">{{ $event['name'] }}</span>
    <span class="mt-0.5 block font-mono text-[9px] font-normal normal-case tracking-normal text-[var(--muted)]">
        {{ $label ? $label.' · ' : '' }}{{ $event['date']?->format('d M') }}
    </span>
</th>