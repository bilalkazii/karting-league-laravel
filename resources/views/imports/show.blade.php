<x-app-layout>
    <div class="space-y-6 pb-10">
        <x-group-header :group="$group" :memberCount="0" />
        <x-group-tabs :group="$group" />

        @if (session('status'))
            <div class="flex items-center gap-2 rounded-lg border border-[var(--green)]/30 bg-[var(--green)]/10 px-4 py-3 text-sm text-[var(--green)]" role="status">
                <x-lucide-check-circle class="size-4" />{{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="space-y-1 rounded-lg border border-[var(--red)]/40 bg-[var(--red)]/10 px-4 py-3 text-sm text-[var(--red-bright)]" role="alert">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <div>
            <h1 class="text-lg font-black tracking-[-.03em]">Review {{ $import->original_filename }}</h1>
            <p class="mt-1 text-xs text-[var(--muted)]">
                @if ($import->isPending())
                    Nothing has been imported. Decide what to do with each row below, then confirm.
                    Rows matched to an existing driver are linked only, never edited.
                @else
                    This import was applied {{ $import->confirmed_at?->format('d M Y H:i') }}.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2 text-[10px]">
            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1">{{ $summary['total'] }} rows</span>
            <span class="rounded-full border border-[var(--green)]/30 bg-[var(--green)]/10 px-2.5 py-1 text-[var(--green)]">{{ $summary['exact'] }} matched</span>
            <span class="rounded-full border border-[var(--line)] bg-white/[.03] px-2.5 py-1">{{ $summary['unmatched'] }} new</span>
            <span class="rounded-full border border-[var(--red-bright)]/30 bg-[var(--red-bright)]/10 px-2.5 py-1 text-[var(--red-bright)]">{{ $summary['uncertain'] + $summary['ambiguous'] }} need review</span>
            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[var(--muted)]">{{ $summary['duplicate'] }} duplicates</span>
            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[var(--muted)]">{{ $summary['invalid'] }} invalid</span>
        </div>

        <form method="POST" action="{{ route('groups.imports.confirm', ['group' => $group, 'import' => $import]) }}">
            @csrf
            <div class="space-y-3">
                @foreach ($import->rows as $row)
                    @php
                        $status = $row->match_status;
                        $review = $row->needsReview();
                    @endphp
                    <x-card class="p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold">{{ $row->raw_name }}</span>
                                    <span @class([
                                        'rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest',
                                        'border-[var(--green)]/30 bg-[var(--green)]/10 text-[var(--green)]' => $status === 'exact',
                                        'border-[var(--red-bright)]/40 bg-[var(--red-bright)]/10 text-[var(--red-bright)]' => $review || $status === 'invalid',
                                        'border-white/10 bg-white/5 text-[var(--muted)]' => ! $review && ! in_array($status, ['exact', 'invalid'], true),
                                    ])>{{ str_replace('_', ' ', $status) }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-[var(--muted)]">
                                    Line {{ $row->row_number }}
                                    @if ($row->raw_event_label) · {{ $row->raw_event_label }} @endif
                                    @if ($row->raw_finish_position) · position {{ $row->raw_finish_position }} @endif
                                    @if ($row->raw_points_displayed !== null) · {{ $row->raw_points_displayed }} pts shown @endif
                                </p>
                                @if ($row->matchedDriver)
                                    <p class="mt-1 text-[11px] text-[var(--muted)]">
                                        Links to existing driver
                                        <span class="text-white">{{ $row->matchedDriver->profile?->full_name ?? $row->matchedDriver->display_name }}</span>
                                        @if ($row->match_score) · {{ (int) round($row->match_score * 100) }}% name match @endif
                                    </p>
                                @endif
                                @if ($row->createdDriver)
                                    <p class="mt-1 text-[11px] text-[var(--green)]">
                                        Created {{ $row->createdDriver->profile?->full_name }}
                                    </p>
                                @endif
                                @foreach ($row->issues ?? [] as $issue)
                                    <p class="mt-1 text-[11px] text-[var(--red-bright)]">{{ $issue }}</p>
                                @endforeach
                            </div>

                            @if ($import->isPending() && $row->applied_at === null)
                                <div class="w-full sm:w-64">
                                    <label for="row-{{ $row->row_number }}" class="sr-only">Action for line {{ $row->row_number }}</label>
                                    <select id="row-{{ $row->row_number }}" name="rows[{{ $row->row_number }}]"
                                            @class([
                                                'w-full rounded-lg border px-3 py-2 text-xs focus:outline-none',
                                                'border-[var(--red-bright)]/40 bg-[var(--red-bright)]/5' => $review,
                                                'border-[var(--line)] bg-white/[.03]' => ! $review,
                                            ])>
                                        <option value="skip">Skip this row</option>
                                        @if ($row->matched_driver_id && ! $review)
                                            <option value="link" selected>Link to existing driver (no changes)</option>
                                        @endif
                                        @if (! $review && $status !== 'invalid')
                                            <option value="create" @selected($status === 'unmatched')>Create new driver</option>
                                        @endif
                                        @if ($review)
                                            <option value="create">Create new driver instead</option>
                                            <optgroup label="Link to an existing driver">
                                                @foreach ($groupDrivers as $driver)
                                                    <option value="link:{{ $driver->id }}">{{ $driver->profile?->full_name ?? $driver->display_name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                </div>
                            @else
                                <span class="text-[11px] text-[var(--muted)]">
                                    {{ $row->isApplied() ? 'Applied' : 'Not applied' }}
                                </span>
                            @endif
                        </div>
                    </x-card>
                @endforeach
            </div>

            @if ($import->isPending())
                <div class="sticky bottom-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--line)] bg-[var(--panel)] px-4 py-3">
                    <p class="text-[11px] text-[var(--muted)]">
                        Confirming adds the drivers you chose. Existing drivers and all race results are left untouched.
                    </p>
                    <x-button type="submit"><x-lucide-check class="size-3.5" />Confirm import</x-button>
                </div>
            @endif
        </form>
    </div>
</x-app-layout>
