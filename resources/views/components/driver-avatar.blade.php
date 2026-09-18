@props(['color' => '#27272a', 'textColor' => '#ffffff', 'initials' => '', 'size' => 'md'])

@php
    $sizeClasses = match ($size) {
        'sm' => 'size-8 text-[9px]',
        'md' => 'size-10 text-[10px]',
        'lg' => 'size-14 text-sm',
        'xl' => 'size-20 text-lg',
        default => 'size-10 text-[10px]',
    };
@endphp

<span {{ $attributes->merge(['class' => 'grid shrink-0 place-items-center rounded-full font-black '.$sizeClasses]) }} style="background-color: {{ $color }}; color: {{ $textColor }}">{{ $initials }}</span>