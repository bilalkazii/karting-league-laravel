@props(['variant' => 'primary', 'size' => 'default'])

@php
    $variantClasses = '';
    if ($variant === 'primary') {
        $variantClasses = 'bg-[var(--red)] text-white hover:bg-[var(--red-bright)]';
    } elseif ($variant === 'secondary') {
        $variantClasses = 'border border-[var(--line)] bg-[var(--panel-raised)] text-white hover:bg-[#1c272d]';
    } elseif ($variant === 'ghost') {
        $variantClasses = 'text-[var(--muted)] hover:bg-white/5 hover:text-white';
    }

    $sizeClasses = '';
    if ($size === 'default') {
        $sizeClasses = 'h-10 px-4';
    } elseif ($size === 'sm') {
        $sizeClasses = 'h-8 rounded-md px-3 text-xs';
    } elseif ($size === 'icon') {
        $sizeClasses = 'size-10';
    }

    $baseClasses = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--red)] disabled:pointer-events-none disabled:opacity-50';
@endphp

<button {{ $attributes->merge(['class' => trim($baseClasses.' '.$variantClasses.' '.$sizeClasses)]) }}>
    {{ $slot }}
</button>