<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMemberNfcTagRequest;
use App\Models\Member;
use App\Models\MemberNfcTag;
use App\Services\NfcTagService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Additional NFC tags of a member next to the primary one in the access
 * configuration.
 */
class MemberNfcTagController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreMemberNfcTagRequest $request, Member $member, NfcTagService $nfcTags)
    {
        $nfcTags->add($member, $request->validated('uid'), $request->user());

        return back()->with('success', 'Der NFC-Tag wurde hinzugefügt.');
    }

    /**
     * The tag is resolved through Member::nfcTags() (scoped binding), so a tag
     * of another member never reaches this method.
     */
    public function destroy(Request $request, Member $member, MemberNfcTag $nfcTag, NfcTagService $nfcTags)
    {
        $this->authorize('update', $member);

        $nfcTags->remove($member, $nfcTag, $request->user());

        return back()->with('success', 'Der NFC-Tag wurde entfernt.');
    }
}
