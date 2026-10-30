<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedFilters\StoreSavedFilterRequest;
use App\Http\Requests\SavedFilters\UpdateSavedFilterRequest;
use App\Models\SavedFilter;
use App\Services\SavedFilterService;
use Illuminate\Http\RedirectResponse;

class SavedFilterController extends Controller
{
    public function __construct(private readonly SavedFilterService $savedFilters) {}

    public function store(StoreSavedFilterRequest $request): RedirectResponse
    {
        $savedFilter = $this->savedFilters->save(
            $request->user()->currentGym,
            $request->user(),
            $request->validated('context'),
            $request->validated('name'),
            $request->validated('definition'),
        );

        return back()->with('success', 'Filter „'.$savedFilter->name.'“ gespeichert');
    }

    public function update(UpdateSavedFilterRequest $request, SavedFilter $savedFilter): RedirectResponse
    {
        $this->savedFilters->update($savedFilter, $request->validated('name'), $request->validated('definition'));

        return back()->with('success', 'Filter „'.$savedFilter->name.'“ aktualisiert');
    }

    public function destroy(SavedFilter $savedFilter): RedirectResponse
    {
        $savedFilter->delete();

        return back()->with('success', 'Filter „'.$savedFilter->name.'“ gelöscht');
    }
}
