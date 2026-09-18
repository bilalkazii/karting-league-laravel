<x-app-layout>
    <div class="space-y-8">
        <x-page-header eyebrow="The paddock" title="Chat" description="Group rooms for your crews. Race rooms open from each race page.">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/5 px-3 py-1 text-[10px] font-semibold text-[var(--muted)]">
                <x-lucide-message-square class="size-3" />
                @if ($totalUnread > 0)
                    {{ $totalUnread }} unread across your rooms
                @else
                    You're all caught up
                @endif
            </span>
        </x-page-header>

        @if (empty($rooms))
            <x-empty-state title="No chat rooms yet" description="Join a group to unlock its chat room.">
                <a href="{{ route('groups') }}">
                    <x-button><x-lucide-users class="size-3.5" />My groups</x-button>
                </a>
            </x-empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($rooms as $room)
                    @php
                        $latest = $room['latest'];
                        $preview = $latest
                            ? \App\Services\ChatService::senderLabel($latest->sender).': '.$latest->body
                            : 'No messages yet — start the conversation.';
                    @endphp
                    <a href="{{ route('chat.group', $room['group']) }}" class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--red)]">
                        <x-card class="flex h-full flex-col gap-3 p-4 transition hover:border-[var(--line)] hover:bg-[var(--panel-raised)]">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-lg text-[10px] font-black" style="background-color: {{ $room['group']->logo_color }}; color: {{ $room['group']->logo_text_color }}">
                                        {{ $room['group']->logo_initials }}
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="truncate text-sm font-bold">{{ $room['group']->name }}</h3>
                                        <p class="text-[10px] text-[var(--muted)]">Group room</p>
                                    </div>
                                </div>
                                @if ($room['unread'] > 0)
                                    <span class="shrink-0 rounded-full bg-[var(--red)] px-2 py-0.5 text-[10px] font-bold text-white">{{ $room['unread'] }}</span>
                                @endif
                            </div>
                            <p class="line-clamp-2 flex-1 text-xs leading-relaxed text-[var(--muted)]">{{ $preview }}</p>
                            <div class="flex items-center justify-between text-xs text-[var(--muted)] transition-colors group-hover:text-white">
                                <span>Open chat</span>
                                <x-lucide-message-circle class="size-3.5" />
                            </div>
                        </x-card>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>