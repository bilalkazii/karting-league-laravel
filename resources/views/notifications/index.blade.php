<x-app-layout>
    <div class="space-y-6">
        <x-page-header eyebrow="Activity" title="Notifications" description="Updates on your races, penalties and group activity.">
            @if ($unreadCount > 0)
                <form action="{{ route('notifications.read-all') }}" method="POST">
                    @csrf
                    <x-button variant="secondary" size="sm"><x-lucide-check-check class="size-3.5" />Mark all as read</x-button>
                </form>
            @endif
        </x-page-header>

        @if ($notifications->isEmpty())
            <x-empty-state title="No notifications" description="You're all caught up — nothing here yet.">
                <span class="rounded-full bg-white/5 p-3"><x-lucide-bell class="size-[18px] text-[var(--muted)]" /></span>
            </x-empty-state>
        @else
            <ul class="space-y-3">
                @foreach ($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $unread = is_null($notification->read_at);
                        $icon = match ($notification->type) {
                            \App\Notifications\RaceOpened::class => 'calendar',
                            \App\Notifications\PenaltyIssued::class => 'alert-triangle',
                            \App\Notifications\RaceCompleted::class => 'flag',
                            default => 'bell',
                        };
                        $iconTone = match ($notification->type) {
                            \App\Notifications\PenaltyIssued::class => 'text-[var(--amber)]',
                            \App\Notifications\RaceCompleted::class => 'text-[var(--green)]',
                            default => 'text-[var(--red-bright)]',
                        };
                    @endphp
                    <li>
                        <div
                            @class([
                                'relative flex gap-4 rounded-xl border bg-[var(--panel)] p-5 transition',
                                $unread ? 'border-[var(--red)]/20 bg-[var(--red)]/[.04]' : 'border-[var(--line)] hover:bg-[var(--panel-raised)]',
                            ])
                        >
                            @if ($unread)
                                <span class="absolute right-4 top-4 size-2 rounded-full bg-[var(--red)]" aria-label="Unread"></span>
                            @endif

                            <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/5">
                                <x-dynamic-component :component="'lucide-'.$icon" class="size-[18px] {{ $iconTone }}" />
                            </div>

                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                    <p class="text-sm font-semibold {{ $unread ? 'text-white' : 'text-[var(--muted)]' }}">{{ $data['title'] }}</p>
                                    <span class="text-[10px] text-[var(--muted)]">{{ $notification->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm leading-relaxed text-[var(--muted)]">{{ $data['body'] }}</p>
                                <div class="flex flex-wrap items-center gap-3">
                                    @if (! empty($data['url']))
                                        <a href="{{ $data['url'] }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--red-bright)] hover:underline">
                                            View race <x-lucide-chevron-right class="size-3" />
                                        </a>
                                    @endif
                                    @if ($unread)
                                        <form action="{{ route('notifications.read', $notification) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--line)] px-2.5 py-1 text-[10px] font-semibold text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-white">
                                                <x-lucide-check class="size-3" />Mark read
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-app-layout>