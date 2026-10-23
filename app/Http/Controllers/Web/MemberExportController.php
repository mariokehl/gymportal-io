<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\ExportMembersRequest;
use App\Services\MemberExportService;
use App\Services\MemberListService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController extends Controller
{
    public function __invoke(
        ExportMembersRequest $request,
        MemberListService $memberList,
        MemberExportService $memberExport,
    ): StreamedResponse {
        $gym = $request->user()->currentGym;

        $query = $memberExport->query(
            $gym,
            $memberList->definition($gym, $request->validated('filter')),
            $request->validated('member_ids'),
            $request->validated('excluded_ids') ?? [],
        );

        return $memberExport->download($query);
    }
}
