@props(['value' => 'available'])

@php
    $options = [
        'available' => [
            'label' => 'Available',
            'activeClass' => 'border-[var(--green)]/40 bg-[var(--green)]/10 text-[var(--green)]',
            'inactiveClass' => 'border-[var(--line)] bg-white/[.02] text-[var(--muted)] hover:bg-white/5',
        ],
        'maybe' => [
            'label' => 'Maybe',
            'activeClass' => 'border-[var(--amber)]/40 bg-[var(--amber)]/10 text-[var(--amber)]',
            'inactiveClass' => 'border-[var(--line)] bg-white/[.02] text-[var(--muted)] hover:bg-white/5',
        ],
        'not-available' => [
            'label' => 'Not available',
            'activeClass' => 'border-white/15 bg-white/5 text-[var(--muted)]',
            'inactiveClass' => 'border-[var(--line)] bg-white/[.02] text-[var(--muted)] hover:bg-white/5',
        ],
    ];
@endphp

<div class="flex gap-1.5" role="radiogroup" aria-label="Availability status">
    @foreach ($options as $key => $option)
        @if ($value === $key)
            <button type="button" role="radio" aria-checked="true" class="rounded-lg border px-3 py-1.5 text-[10px] font-semibold transition {{ $option['activeClass'] }}">{{ $option['label'] }}</button>
        @else
            <button type="button" role="radio" aria-checked="false" class="rounded-lg border px-3 py-1.5 text-[10px] font-semibold transition {{ $option['inactiveClass'] }}">{{ $option['label'] }}</button>
        @endif
    @endforeach
</div>