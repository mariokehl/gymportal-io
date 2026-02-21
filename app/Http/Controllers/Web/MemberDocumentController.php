<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Membership;
use App\Services\ContractService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class MemberDocumentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ContractService $contractService
    ) {}

    /**
     * Alle Dokumente eines Mitglieds auflisten.
     */
    public function index(Member $member)
    {
        $this->authorize('view', $member);

        $documents = $member->memberships()
            ->whereNotNull('contract_file_path')
            ->with('membershipPlan')
            ->get()
            ->map(fn(Membership $m) => [
                'id' => $m->id,
                'type' => 'contract',
                'name' => 'Mitgliedschaftsvertrag - ' . ($m->membershipPlan->name ?? 'Unbekannt'),
                'membership_id' => $m->id,
                'plan_name' => $m->membershipPlan->name ?? '',
                'created_at' => $m->updated_at->format('d.m.Y H:i'),
                'start_date' => $m->start_date?->format('d.m.Y'),
            ]);

        // Memberships ohne Vertrag (für nachträgliche Erstellung)
        // Gratis-Testzeitraum und ausstehende Mitgliedschaften ausschließen
        $membershipsWithoutContract = $member->memberships()
            ->whereNull('contract_file_path')
            ->where('status', '!=', 'pending')
            ->whereHas('membershipPlan', fn($q) => $q->where('is_free_trial_plan', false))
            ->with('membershipPlan')
            ->get()
            ->map(fn(Membership $m) => [
                'id' => $m->id,
                'plan_name' => $m->membershipPlan->name ?? 'Unbekannt',
                'start_date' => $m->start_date?->format('d.m.Y'),
                'status' => $m->status,
            ]);

        return response()->json([
            'documents' => $documents,
            'memberships_without_contract' => $membershipsWithoutContract,
            'contracts_enabled' => $member->gym->isOnlineContractEnabled(),
        ]);
    }

    /**
     * Vertrag-PDF herunterladen.
     */
    public function download(Member $member, Membership $membership)
    {
        $this->authorize('view', $member);

        if ($membership->member_id !== $member->id) {
            abort(404);
        }

        if (!$membership->contract_file_path) {
            abort(404, 'Kein Vertrag vorhanden.');
        }

        if (!Storage::disk('local')->exists($membership->contract_file_path)) {
            abort(404, 'Vertragsdatei nicht gefunden.');
        }

        $fileName = 'Vertrag_' . $member->member_number . '.pdf';

        return Storage::disk('local')->download($membership->contract_file_path, $fileName);
    }

    /**
     * Vertrag nachträglich generieren.
     */
    public function generateContract(Member $member, Membership $membership)
    {
        $this->authorize('update', $member);

        if ($membership->member_id !== $member->id) {
            abort(404);
        }

        // Kein Vertrag für Gratis-Testzeitraum oder ausstehende Mitgliedschaften
        if ($membership->is_free_trial) {
            return response()->json([
                'success' => false,
                'message' => 'Für den Gratis-Testzeitraum kann kein Vertrag erstellt werden.',
            ], 422);
        }

        if ($membership->status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Für ausstehende Mitgliedschaften kann kein Vertrag erstellt werden. Die Mitgliedschaft muss zuerst aktiviert werden.',
            ], 422);
        }

        $path = $this->contractService->generateContractRetroactively($membership);

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'Vertragsgenerierung fehlgeschlagen. Prüfen Sie die Vertragseinstellungen.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vertrag wurde erfolgreich generiert.',
        ]);
    }
}
