<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberQuickSearchRequest;
use App\Services\MemberQuickSearchService;
use Illuminate\Http\JsonResponse;

class MemberQuickSearchController extends Controller
{
    /**
     * Global member search in the sidebar. Without a query it resolves the
     * recently viewed members whose IDs the browser keeps locally.
     */
    public function __invoke(MemberQuickSearchRequest $request, MemberQuickSearchService $service): JsonResponse
    {
        $gymId = $request->user()->current_gym_id;
        $query = (string) $request->validated('q', '');

        $members = trim($query) !== ''
            ? $service->search($gymId, $query)
            : $service->findByIds($gymId, $request->validated('ids', []));

        return response()->json(['members' => $members]);
    }
}
