<x-app-layout>
    <div
        class="mx-auto max-w-3xl space-y-6 pb-10"
        x-data="{}"
        x-init="setInterval(() => { if (! document.hidden && ! $refs.composer?.contains(document.activeElement)) { window.location.reload(); } }, 20000)"
    >
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <x-page-header eyebrow="Chat" :title="$roomTitle" :description="$roomSubtitle" />
            </div>
            <a href="{{ $backUrl }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[var(--line)] px-3 py-2 text-xs font-semibold text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-white">
                <x-lucide-arrow-left class="size-3.5" />{{ $backLabel }}
            </a>
        </div>

        <x-card class="flex h-[60vh] flex-col overflow-hidden">
            <div class="flex-1 space-y-1 overflow-y-auto px-4 py-4">
                @forelse ($messages as $message)
                    @php
                        $mine = $message->sender_id === auth()->user()?->driver?->id;
                        $senderName = \App\Services\ChatService::senderLabel($message->sender);
                    @endphp
                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <div @class([
                            'max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
                            'bg-[var(--red)] text-white rounded-br-md' => $mine,
                            'border border-[var(--line)] bg-white/[.03] text-[var(--text)] rounded-bl-md' => ! $mine,
                        ])>
                            <p class="text-[10px] font-semibold {{ $mine ? 'text-white/70' : 'text-[var(--muted)]' }}">
                                @if (! $mine)
                                    {{ $senderName }}
                                @endif
                            </p>
                            <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                            <div class="mt-1 flex items-center justify-end gap-2 text-[9px] {{ $mine ? 'text-white/60' : 'text-[var(--muted)]' }}">
                                <span>{{ $message->created_at->diffForHumans() }}</span>
                                @if ($mine)
                                    <form method="POST" action="{{ route('chat.messages.destroy', $message) }}" onsubmit="return confirm('Delete this message?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="underline-offset-2 hover:underline" aria-label="Delete message">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="grid h-full place-items-center">
                        <div class="text-center">
                            <span class="mb-3 inline-block rounded-full bg-white/5 p-3"><x-lucide-message-circle class="size-[18px] text-[var(--muted)]" /></span>
                            <p class="text-sm font-semibold text-white">No messages yet</p>
                            <p class="mt-1 text-xs text-[var(--muted)]">Say hello to the paddock.</p>
                        </div>
                    </div>
                @endforelse
            </div>

            <form
                x-ref="composer"
                method="POST"
                action="{{ $sendUrl }}"
                class="flex items-end gap-3 border-t border-[var(--line)] bg-[var(--panel)] p-4"
            >
                @csrf
                <label for="body" class="sr-only">Message</label>
                <textarea
                    id="body"
                    name="body"
                    rows="1"
                    maxlength="500"
                    required
                    placeholder="Write a message…"
                    class="max-h-32 min-h-[44px] flex-1 resize-none rounded-lg border border-[var(--line)] bg-white/[.03] px-3.5 py-2.5 text-sm text-white placeholder:text-[var(--muted)] outline-none transition focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                ></textarea>
                @if (! $canSend)
                    <p class="text-[10px] text-[var(--muted)]">You need a driver profile to post.</p>
                @else
                    <button type="submit" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-[var(--red)] px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-[var(--red)]/20 transition hover:bg-[var(--red-bright)]">
                        <x-lucide-send class="size-3.5" />Send
                    </button>
                @endif
            </form>
        </x-card>

        <p class="text-center text-[10px] text-[var(--muted)]">
            Messages refresh automatically while this tab is open and you're not typing.
            The app is server-rendered — live broadcasts are not part of this phase.
        </p>
    </div>
</x-app-layout>