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
            <h1 class="text-lg font-black tracking-[-.03em]">Import drivers from CSV</h1>
            <p class="mt-1 text-xs text-[var(--muted)]">
                Upload a CSV to review the drivers it names before anything is added. Uploading never
                changes the database: existing drivers are matched, never overwritten, and no race
                result is written by this workflow.
            </p>
        </div>

        <x-card class="p-5">
            <form method="POST" action="{{ route('groups.imports.store', $group) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="group_id" value="{{ $group->id }}">

                <div>
                    <label for="file" class="mb-1.5 block text-xs font-semibold">CSV file</label>
                    <input id="file" name="file" type="file" accept=".csv,text/csv" required
                           class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2 text-sm text-white file:mr-3 file:rounded-md file:border-0 file:bg-[var(--red)] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white">
                    <p class="mt-2 text-[11px] text-[var(--muted)]">
                        Recognised columns: name, email, phone, event, position, points. Header names are
                        matched loosely, so <span class="font-mono">Full Name</span> and
                        <span class="font-mono">full_name</span> both work.
                    </p>
                </div>

                <x-button type="submit"><x-lucide-upload class="size-3.5" />Build preview</x-button>
            </form>
        </x-card>

        @if ($import)
            <x-card class="p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold">{{ $import->original_filename }}</h2>
                        <p class="text-[11px] text-[var(--muted)]">
                            {{ $summary['total'] }} {{ \Illuminate\Support\Str::plural('row', $summary['total']) }} ·
                            {{ $import->isPending() ? 'Awaiting confirmation' : 'Applied '.$import->confirmed_at?->format('d M Y H:i') }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('groups.imports.show', ['group' => $group, 'import' => $import]) }}">
                            <x-button type="button" variant="ghost">Review preview</x-button>
                        </a>
                        @if ($import->isPending())
                            <form method="POST" action="{{ route('groups.imports.destroy', ['group' => $group, 'import' => $import]) }}"
                                  onsubmit="return confirm('Discard this import? Nothing was added.')">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="ghost" class="text-[var(--red-bright)]">Discard</x-button>
                            </form>
                        @endif
                    </div>
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
