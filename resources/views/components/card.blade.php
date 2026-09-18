@props(['class' => ''])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-[var(--line)] bg-[var(--panel)] '.$class]) }}>{{ $slot }}</div>