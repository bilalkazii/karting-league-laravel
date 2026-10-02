<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDriverImportRequest;
use App\Models\Driver;
use App\Models\DriverImport;
use App\Models\Group;
use App\Services\DriverImportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverImportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly DriverImportService $imports) {}

    public function index(Request $request, Group $group): View
    {
        $this->authorize('update', $group);

        $import = DriverImport::where('group_id', $group->id)
            ->where('created_by', $request->user()->id)
            ->latest('id')
            ->with('rows')
            ->first();

        $summary = $import ? $this->imports->summary($import) : null;

        return view('imports.index', [
            'group' => $group,
            'import' => $import,
            'summary' => $summary,
            'drivers' => $import
                ? Driver::whereIn('id', $import->rows->whereNotNull('matched_driver_id')->pluck('matched_driver_id'))->with('profile')->get()
                : collect(),
        ]);
    }

    public function store(StoreDriverImportRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $import = $this->imports->preview(
            $group,
            $request->user(),
            (string) file_get_contents($request->file('file')->getRealPath()),
            (string) $request->file('file')->getClientOriginalName(),
        );

        return redirect()
            ->route('groups.imports.show', ['group' => $group, 'import' => $import])
            ->with('status', "Preview ready. Nothing has been imported yet — review the {$this->imports->summary($import)['total']} rows and confirm.");
    }

    public function show(Group $group, DriverImport $import): View
    {
        $this->authorize('update', $group);
        abort_unless($import->group_id === $group->id, 404);

        $import->load('rows.matchedDriver.profile', 'rows.createdDriver.profile');

        return view('imports.show', [
            'group' => $group,
            'import' => $import,
            'summary' => $this->imports->summary($import),
            'groupDrivers' => Driver::with('profile')
                ->whereHas('groups', fn ($q) => $q->where('groups.id', $group->id))
                ->orderBy('nickname')
                ->get(),
        ]);
    }

    public function confirm(Request $request, Group $group, DriverImport $import): RedirectResponse
    {
        $this->authorize('update', $group);
        abort_unless($import->group_id === $group->id, 404);

        $actions = $request->input('rows', []);
        abort_if(! is_array($actions), 422, 'No import decisions were submitted.');

        // Every held-back row must be given an explicit decision, so nothing is
        // applied by omission.
        $unresolved = $import->rows()
            ->whereIn('match_status', ['ambiguous', 'uncertain'])
            ->pluck('row_number')
            ->reject(fn ($line) => isset($actions[$line]) && $actions[$line] !== 'skip')
            ->all();

        if ($unresolved !== []) {
            return back()->withErrors([
                'rows' => 'Resolve or skip every row marked for review before confirming (lines: '.implode(', ', $unresolved).').',
            ]);
        }

        $import = $this->imports->confirm($import, $actions);

        $created = $import->rows()->whereNotNull('created_driver_id')->count();

        return redirect()
            ->route('groups.imports.show', ['group' => $group, 'import' => $import])
            ->with('status', "Import applied. {$created} new ".($created === 1 ? 'driver' : 'drivers').' added, no existing driver or race result was changed.');
    }

    public function destroy(Request $request, Group $group, DriverImport $import): RedirectResponse
    {
        $this->authorize('update', $group);
        abort_unless($import->group_id === $group->id, 404);

        $import->delete();

        return redirect()
            ->route('groups.imports.index', $group)
            ->with('status', 'Import discarded.');
    }
}
