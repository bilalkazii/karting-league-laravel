<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Karting') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;900&family=Geist+Mono:wght@400;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-[var(--background)]">
            <x-sidebar :driver="auth()->user()?->driver" />

            <div class="lg:pl-64">
                <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-[var(--line)] bg-[#07090b]/80 px-5 backdrop-blur-xl sm:px-8">
                    <button x-data x-on:click="$dispatch('open-sidebar')" class="text-white lg:hidden" aria-label="Open navigation">
                        <x-lucide-grid-2x2 class="size-[21px]" />
                    </button>
                    <div class="lg:hidden"><x-brand /></div>
                    <div class="ml-auto flex items-center gap-2">
                        <a href="{{ route('races') }}" class="hidden items-center gap-2 rounded-lg border border-[var(--line)] px-3 py-2 text-xs font-semibold text-[var(--muted)] hover:text-white sm:flex">
                            <x-lucide-calendar-days class="size-[15px]" />Next race
                        </a>
                        <a href="{{ route('notifications') }}" class="relative grid size-9 place-items-center rounded-lg text-[var(--muted)] hover:bg-white/5 hover:text-white" aria-label="Notifications">
                            <x-lucide-bell class="size-[18px]" />
                            <span class="absolute right-1 top-1 size-1.5 rounded-full bg-[var(--red)]"></span>
                        </a>
                    </div>
                </header>

                <main class="mx-auto max-w-[1440px] px-5 py-7 pb-24 sm:px-8 lg:px-10 lg:py-10">
                    {{ $slot }}
                </main>
            </div>

            <x-bottom-nav />
        </div>

        @livewireScripts
    </body>
</html>