@props(['class' => ''])

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border border-white/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[.12em] '.$class]) }}>{{ $slot }}</span>