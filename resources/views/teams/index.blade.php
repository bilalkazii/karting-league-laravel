<x-app-layout>
    <div class="space-y-6 pb-10">
        <x-group-header :group="$group" :memberCount="$members->count()" />
        <x-group-tabs :group="$group" />

        @if (session('status'))
            <div class="flex items-center gap-2 rounded-lg border border-[var(--green)]/30 bg-[var(--green)]/10 px-4 py-3 text-sm text-[var(--green)]" role="status">
                <x-lucide-check-circle class="size-4" />{{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-lg font-black tracking-[-.03em]">Teams</h1>
                <p class="text-xs text-[var(--muted)]">
                    {{ $teams->count() }} {{ \Illuminate\Support\Str::plural('team', $teams->count()) }} in this group.
                    Renaming a team never changes driver names or past results.
                </p>
            </div>
            @if ($canManage)
                <a
                    href="#new-team"
                    class="inline-flex items-center gap-1.5 self-start rounded-xl bg-[var(--red)] px-4 py-2 text-xs font-bold text-white shadow-lg shadow-[var(--red)]/20 transition hover:bg-[var(--red-bright)]"
                >
                    <x-lucide-plus class="size-[13px]" />New team
                </a>
            @endif
        </div>

        @if ($teams->isEmpty())
            <x-empty-state title="No teams yet" description="Teams pair drivers so they can compete together in a championship." />
        @else
            <div class="space-y-4">
                @foreach ($teams as $team)
                    <x-card class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg text-xs font-black"
                                      style="background-color: {{ $team->logo_color }}; color: {{ $team->logo_text_color }}">{{ $team->logo_initials }}</span>
                                <div class="min-w-0">
                                    <h2 class="truncate text-sm font-bold">{{ $team->name }}</h2>
                                    <p class="text-[10px] text-[var(--muted)]">{{ $team->members->count() }} {{ \Illuminate\Support\Str::plural('driver', $team->members->count()) }}</p>
                                </div>
                            </div>

                            @if ($canManage)
                                <form method="POST" action="{{ route('teams.destroy', $team) }}" onsubmit="return confirm('Remove {{ addslashes($team->name) }}? Drivers and their results are kept.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="grid size-8 place-items-center rounded-lg border border-[var(--line)] text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-[var(--red-bright)]" title="Remove {{ $team->name }}" aria-label="Remove {{ $team->name }}">
                                        <x-lucide-trash-2 class="size-3.5" />
                                    </button>
                                </form>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('teams.members.update', $team) }}" class="mt-4"
                              x-data="{
                                  current: {{ \Illuminate\Support\Js::from($team->members->pluck('drivers.id')->all()) }},
                                  removalPrompt(event) {
                                      const boxes = [...this.$el.querySelectorAll('input[name=\'driver_ids[]\'][type=checkbox]')]
                                      const removed = boxes
                                          .filter(box => box.checked && ! this.current.includes(Number(box.value)))
                                          .map(box => box.closest('label').querySelector('span').textContent.trim())

                                      if (! removed.length) {
                                          this.$el.querySelector('input[name=confirm_removals]').value = '1'
                                          return
                                      }

                                      const message = 'Remove ' + removed.join(', ') + ' from this team?\n\n'
                                          + 'They keep every result they already earned, but they stop scoring for this team.'

                                      if (confirm(message)) {
                                          this.$el.querySelector('input[name=confirm_removals]').value = '1'
                                      } else {
                                          event.preventDefault()
                                      }
                                  },
                              }"
                              x-on:submit="removalPrompt($event)">
                            @csrf
                            @method('PUT')
                            <div class="flex flex-wrap gap-2">
                                @foreach ($members as $member)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2 text-xs transition has-[:checked]:border-[var(--red)]/50 has-[:checked]:bg-[var(--red)]/10">
                                        <input
                                            type="checkbox"
                                            name="driver_ids[]"
                                            value="{{ $member->id }}"
                                            @checked($team->members->contains($member->id))
                                            @disabled(! $canManage)
                                            class="size-3.5 accent-[var(--red)]"
                                        >
                                        <span class="font-semibold text-white">{{ $member->profile?->full_name ?? $member->nickname }}</span>
                                    </label>
                                @endforeach
                            </div>

                            @error('driver_ids')
                                <p class="mt-2 text-[10px] text-[var(--red-bright)]">{{ $message }}</p>
                            @enderror

                            @if ($canManage)
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-[10px] text-[var(--muted)]">
                                        Up to {{ \App\Http\Requests\StoreTeamRequest::MAX_MEMBERS }} drivers per team. Untick a current driver to remove them from the team.
                                    </p>
                                    {{-- Removal must be acknowledged, so a driver is never dropped by an unnoticed untick. --}}
                                    <input type="hidden" name="confirm_removals" value="0">
                                    <button type="submit" class="rounded-lg border border-[var(--line)] px-3 py-1.5 text-[10px] font-semibold text-[var(--muted)] transition hover:border-[var(--red)]/40 hover:text-white">
                                        Save lineup
                                    </button>
                                </div>
                            @endif
                        </form>

                        @if ($canManage)
                            <form method="POST" action="{{ route('teams.update', $team) }}" class="mt-4 flex flex-wrap items-end gap-2 border-t border-[var(--line)] pt-4">
                                @csrf
                                @method('PATCH')
                                <div class="min-w-0 grow space-y-1.5">
                                    <label for="rename-{{ $team->id }}" class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted)]">Team name</label>
                                    <input
                                        id="rename-{{ $team->id }}"
                                        type="text"
                                        name="name"
                                        value="{{ old('name', $team->name) }}"
                                        maxlength="40"
                                        required
                                        class="w-full rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2 text-sm text-white outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)]"
                                    >
                                </div>
                                <button type="submit" class="rounded-lg bg-[var(--red)] px-3.5 py-2 text-xs font-bold text-white shadow-lg shadow-[var(--red)]/20 transition hover:bg-[var(--red-bright)]">
                                    Save name
                                </button>
                            </form>
                            @error('name')
                                <p class="mt-1 text-[10px] text-[var(--red-bright)]">{{ $message }}</p>
                            @enderror
                        @endif
                    </x-card>
                @endforeach
            </div>
        @endif

        @if ($canManage)
            <x-card id="new-team" class="scroll-mt-24 p-5">
                <h2 class="flex items-center gap-2 text-sm font-bold">
                    <x-lucide-plus class="size-4 text-[var(--red-bright)]" />Create a team
                </h2>
                <form method="POST" action="{{ route('groups.teams.store', $group) }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="group_id" value="{{ $group->id }}">

                    <div class="space-y-1.5">
                        <label for="team_name" class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted)]">Team name</label>
                        <input
                            id="team_name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            maxlength="40"
                            required
                            @error('name') aria-invalid="true" @enderror
                            class="w-full rounded-lg border bg-white/[.03] px-3 py-2.5 text-sm text-white placeholder:text-[var(--muted)] outline-none focus:border-[var(--red)] focus:ring-1 focus:ring-[var(--red)] @error('name') border-[var(--red)] @else border-[var(--line)] @enderror"
                        >
                        @error('name')
                            <p class="text-[10px] text-[var(--red-bright)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted)]">Drivers (optional)</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($members as $member)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--line)] bg-white/[.03] px-3 py-2 text-xs transition has-[:checked]:border-[var(--red)]/50 has-[:checked]:bg-[var(--red)]/10">
                                    <input type="checkbox" name="driver_ids[]" value="{{ $member->id }}" class="size-3.5 accent-[var(--red)]">
                                    <span class="truncate font-semibold text-white">{{ $member->profile?->full_name ?? $member->nickname }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('driver_ids')
                            <p class="text-[10px] text-[var(--red-bright)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="rounded-xl bg-[var(--red)] px-4 py-2 text-xs font-bold text-white shadow-lg shadow-[var(--red)]/20 transition hover:bg-[var(--red-bright)]">
                        Create team
                    </button>
                </form>
            </x-card>
        @endif
    </div>
</x-app-layout>