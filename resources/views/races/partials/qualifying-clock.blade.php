@php
    $entriesByDriver = $race->entries->keyBy('driver_id');
@endphp

<form method="POST" action="{{ route('races.qualifying.record', $race) }}" class="space-y-4">
    @csrf

    <div class="space-y-2">
        <label for="q-driver" class="text-xs font-semibold">Driver</label>
        <select id="q-driver" name="driver_id" required class="w-full rounded-lg border border-[var(--line)] bg-[var(--panel-raised)] px-4 py-2.5 text-sm text-white outline-none focus:border-[var(--red)]">
            @foreach ($race->entries as $entry)
                <option value="{{ $entry->driver_id }}">{{ $entry->driver->display_name }} — kart {{ $entry->kart_number ?: '—' }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="space-y-2">
            <label for="q-min" class="text-xs font-semibold">Minutes</label>
            <input id="q-min" type="number" name="split_min" min="0" value="0"
                   class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2.5 font-mono text-sm text-white outline-none focus:border-[var(--red)]" />
        </div>
        <div class="space-y-2">
            <label for="q-sec" class="text-xs font-semibold">Seconds <span class="text-[var(--red)]">*</span></label>
            <input id="q-sec" type="number" name="split_sec" min="0" max="120" step="0.001" placeholder="32.415" required
                   class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2.5 font-mono text-sm text-white outline-none focus:border-[var(--red)]" />
        </div>
        <div class="space-y-2">
            <label for="q-ms" class="text-xs font-semibold">Millis</label>
            <input id="q-ms" type="number" name="split_ms" min="0" max="999" value="0"
                   class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2.5 font-mono text-sm text-white outline-none focus:border-[var(--red)]" />
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="action" value="record" />
        <x-button type="submit">
            <x-lucide-timer class="size-3.5" />Record lap
        </x-button>
        <label class="flex items-center gap-1.5 text-[10px] text-[var(--muted)]">
            <input type="checkbox" name="manual" value="1" class="size-3 accent-[var(--red)]" />
            Manual override (mark as corrected)
        </label>
        <span class="text-[10px] leading-relaxed text-[var(--muted)]">
            Enter e.g. 1:23.456 or 34.725. Record replaces the official time; the manual flag marks times entered from a stopwatch log.
        </span>
    </div>
</form>