<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberStatusHistory;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\MemberService;
use App\Services\PaymentService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MemberController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the members.
     */
    public function index(Request $request)
    {
        /** @var User $auth */
        $user = Auth::user();

        $query = Member::query()
            ->with(['user', 'gym'])
            ->where('gym_id', $user->current_gym_id);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('member_number', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Order by latest joined
        $query->orderBy('joined_date', 'desc');

        $members = $query->paginate(15)->withQueryString();

        // Add additional data to each member
        $members->getCollection()->transform(function ($member) {
            // Get last visit from visits table (assuming you have a visits table)
            //$member->last_visit = $member->visits()->latest()->first()?->visited_at;

            // Get contract end date from contracts table (assuming you have a contracts table)
            //$member->contract_end_date = $member->contracts()
            //    ->where('status', 'active')
            //    ->first()?->end_date;

            // Füge Lösch-Informationen hinzu
            $member->can_delete = $member->canBeDeleted();
            if (!$member->can_delete) {
                $deleteBlockInfo = $member->getDeleteBlockReason();
                $member->delete_block_reason = $deleteBlockInfo['reason'] ?? 'Löschen nicht möglich';
                $member->delete_block_type = $deleteBlockInfo['type'] ?? 'unknown';
            }

            return $member;
        });

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'status'])
        ]);
    }

    /**
     * Show the form for creating a new member.
     */
    public function create()
    {
        $user = Auth::user();

        $membershipPlans = MembershipPlan::where('gym_id', $user->current_gym_id)
            ->withCount(['memberships' => function ($query) {
                $query->where('status', 'active');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        /** @var Gym $gym */
        $gym = $user->currentGym;
        $paymentMethods = $gym->getEnabledPaymentMethods(); // Now returns a proper array

        return Inertia::render('Members/Create', [
            'membershipPlans' => $membershipPlans,
            'paymentMethods' => $paymentMethods
        ]);
    }

    /**
     * Store a newly created member in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Member::class);

        /** @var User $user */
        $user = Auth::user();

        /** @var Gym $gym */
        $gym = $user->currentGym;
        $enabledPaymentMethods = array_column($gym->getEnabledPaymentMethods(), 'key');

        $validated = $request->validate([
            'salutation' => ['required', Rule::in(['Herr', 'Frau', 'Divers'])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('members', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'paused', 'overdue', 'pending'])],
            'joined_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', Rule::in($enabledPaymentMethods)],
        ]);

        // Next member number
        $memberData = [];
        $memberData['member_number'] = MemberService::generateMemberNumber($gym, 'M');

        // Add gym_id to the validated data
        $memberData['gym_id'] = $user->current_gym_id;
        $memberData['user_id'] = $user->id;

        // Member status
        $memberData['status'] = 'pending';

        try {
            DB::beginTransaction();

            // Create the member
            $newMember = Member::create(
                array_merge(
                    $validated,
                    $memberData,
                )
            );

            // Get membership plan to calculate end date
            $membershipPlan = MembershipPlan::findOrFail($request->membership_plan_id);

            // Create membership
            $newMembership = app(MemberService::class)->createMembership($newMember, $membershipPlan, 'pending');

            // Select payment method
            $paymentMethodData = [
                'member_id' => $newMember->id,
                'type' => $request->payment_method,
                'is_default' => true, // Setze als Standard-Zahlungsmethode
            ];

            // Check if this payment method requires a mandate
            if (PaymentMethod::typeRequiresMandate($request->payment_method, $gym->getPaymentMethodForKey($request->payment_method))) {
                $paymentMethodData['status'] = 'pending'; // bis Zahlungsdaten vollständig hinterlegt (z.B. SEPA-Mandat)
                $paymentMethodData['requires_mandate'] = true;
                $paymentMethodData['sepa_mandate_status'] = 'pending';
                $paymentMethodData['sepa_mandate_acknowledged'] = false;
            } else {
                $paymentMethodData['requires_mandate'] = false;
            }

            $newPaymentMethod = PaymentMethod::create($paymentMethodData);

            // Create Payment
            $paymentService = app(PaymentService::class);
            $paymentService->createSetupFeePayment($newMember, $newMembership, $newPaymentMethod);
            $paymentService->createPendingPayment($newMember, $newMembership, $newPaymentMethod);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withErrors(['general' => 'Fehler beim Erstellen des Mitglieds. Bitte versuchen Sie es erneut.'])
                ->withInput();
        }

        return redirect()->route('members.show', ['member' => $newMember->id])
            ->with('success', 'Mitglied wurde erfolgreich erstellt.');
    }

    /**
     * Display the specified member.
     */
    public function show(Member $member)
    {
        $this->authorize('view', $member);

        $member->load([
            'user',
            'gym',
            'memberships.membershipPlan',
            'paymentMethods',
            'payments',
            'checkIns' => function ($query) {
                $query->latest()->take(10);
            },
            'accessConfig',
            'statusHistory.changedBy:id,first_name,last_name'
        ]);

        // Transformiere die Status History für das Frontend
        $member->setRelation('status_history',
            $member->statusHistory->map(function ($history) {
                return [
                    'id' => $history->id,
                    'old_status' => $history->old_status,
                    'new_status' => $history->new_status,
                    'old_status_text' => $history->old_status_text,
                    'new_status_text' => $history->new_status_text,
                    'reason' => $history->reason,
                    'changed_by_name' => $history->changedBy ? $history->changedBy->fullName() : 'System',
                    'metadata' => $history->metadata,
                    'created_at' => $history->created_at->toISOString(),
                    'formatted_date' => $history->created_at->format('d.m.Y H:i')
                ];
            })
        );

        return Inertia::render('Members/Show', [
            'member' => $member,
            'availablePaymentMethods' => $member->gym->getEnabledPaymentMethods(),
            'updatedPayments' => session('updated_payments', false) ? $member->payments : null
        ]);
    }

    /**
     * Update the specified member in storage.
     */
    public function update(Request $request, Member $member)
    {
        // Ensure the member belongs to the current gym
        $this->authorize('update', $member);

        $validated = $request->validate([
            'member_number' => ['required', 'string', 'max:50',
                Rule::unique('members', 'member_number')->ignore($member->id)
            ],
            'salutation' => ['required', Rule::in(['Herr', 'Frau', 'Divers'])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('members', 'email')->ignore($member->id)
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'paused', 'overdue', 'pending'])],
            'joined_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $member->update($validated);

        return redirect()->route('members.show', ['member' => $member->id])->with('success', 'Mitglied wurde erfolgreich aktualisiert.');
    }

    /**
     * Update member status with validation
     */
    public function updateStatus(Request $request, Member $member)
    {
        $this->authorize('update', $member);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive', 'paused', 'overdue', 'pending'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'previous_status' => ['nullable', 'string'] // Für Frontend-Validierung
        ]);

        $newStatus = $validated['status'];
        $currentStatus = $member->status;

        // Nutze die Model-Methode für Validierung
        $blockReason = $member->getStatusChangeBlockReason($newStatus);

        if ($blockReason) {
            return back()->withErrors([
                'status' => $blockReason
            ]);
        }

        // Zusätzliche Prüfung für gleichen Status
        if ($currentStatus === $newStatus) {
            return back()->withErrors([
                'status' => 'Status ist bereits ' . $member->status_text
            ]);
        }

        try {
            DB::beginTransaction();

            // Status ändern
            $member->status = $newStatus;
            $member->save();

            // Status History aufzeichnen
            MemberStatusHistory::recordChange(
                $member,
                $currentStatus,
                $newStatus,
                $validated['reason'] ?? null,
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'action_source' => 'manual_update'
                ]
            );

            // Zusätzliche Aktionen basierend auf Statusänderung
            $this->handleStatusChangeActions($member, $currentStatus, $newStatus);

            DB::commit();

            return back()->with('success', 'Mitgliedsstatus wurde erfolgreich geändert.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Member status update failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors([
                'status' => 'Fehler beim Ändern des Status. Bitte versuchen Sie es erneut.'
            ]);
        }
    }

    /**
     * Handle additional actions after status change
     */
    private function handleStatusChangeActions(Member $member, string $oldStatus, string $newStatus): void
    {
        /** @var User $user */
        $user = Auth::user();

        // Aktivierung von Pending
        if ($oldStatus === 'pending' && $newStatus === 'active') {
            // Aktiviere alle pending Mitgliedschaften
            $activatedCount = $member->memberships()
                ->where('memberships.status', 'pending')
                ->update(['status' => 'active']);

            // Log die Aktivierung in der History
            if ($activatedCount > 0) {
                // Speichere die IDs der aktivierten Mitgliedschaften
                $activatedMembershipIds = $member->memberships()
                    ->where('memberships.status', 'active')
                    ->pluck('memberships.id')
                    ->toArray();

                MemberStatusHistory::create([
                    'member_id' => $member->id,
                    'old_status' => 'pending',
                    'new_status' => 'active',
                    'reason' => "Automatische Aktivierung von {$activatedCount} Mitgliedschaft(en)",
                    'changed_by' => $user->id,
                    'metadata' => [
                        'activated_memberships' => $activatedCount,
                        'activated_membership_ids' => $activatedMembershipIds,
                        'activated_at' => now()->toISOString(),
                        'action_type' => 'auto_activation'
                    ]
                ]);
            }

            // Sende Willkommens-E-Mail (optional)
            // Mail::to($member->email)->send(new WelcomeMemberMail($member));
        }

        // Inaktivierung
        if ($newStatus === 'inactive') {
            // Pausiere alle aktiven Mitgliedschaften
            $pausedCount = $member->memberships()
                ->where('memberships.status', 'active')
                ->update(['status' => 'paused']);

            if ($pausedCount > 0) {
                $pausedMembershipIds = $member->memberships()
                    ->where('memberships.status', 'paused')
                    ->pluck('memberships.id')
                    ->toArray();

                MemberStatusHistory::create([
                    'member_id' => $member->id,
                    'old_status' => 'active',
                    'new_status' => 'paused',
                    'reason' => "Mitgliedschaften pausiert wegen Mitgliedsinaktivierung",
                    'changed_by' => $user->id,
                    'metadata' => [
                        'paused_memberships' => $pausedCount,
                        'paused_membership_ids' => $pausedMembershipIds,
                        'triggered_by' => 'member_inactivation'
                    ]
                ]);
            }
        }

        // Von Overdue zu Active
        if ($oldStatus === 'overdue' && $newStatus === 'active') {
            // Reaktiviere pausierte Mitgliedschaften (prüfe über Status History)
            $recentOverduePause = MemberStatusHistory::where('member_id', $member->id)
                ->where('new_status', 'paused')
                ->where('metadata->triggered_by', 'payment_overdue')
                ->latest()
                ->first();

            if ($recentOverduePause) {
                $reactivatedCount = $member->memberships()
                    ->where('memberships.status', 'paused')
                    ->where('memberships.updated_at', '>=', $recentOverduePause->created_at)
                    ->update(['status' => 'active']);

                if ($reactivatedCount > 0) {
                    $reactivatedMembershipIds = $member->memberships()
                        ->where('memberships.status', 'active')
                        ->pluck('memberships.id')
                        ->toArray();

                    MemberStatusHistory::create([
                        'member_id' => $member->id,
                        'old_status' => 'paused',
                        'new_status' => 'active',
                        'reason' => "Mitgliedschaften reaktiviert nach Zahlungseingang",
                        'changed_by' => $user->id,
                        'metadata' => [
                            'reactivated_memberships' => $reactivatedCount,
                            'reactivated_membership_ids' => $reactivatedMembershipIds,
                            'reactivated_at' => now()->toISOString(),
                            'triggered_by' => 'payment_resolved'
                        ]
                    ]);
                }
            }
        }

        // Zu Overdue
        if ($newStatus === 'overdue' && $oldStatus === 'active') {
            // Pausiere aktive Mitgliedschaften
            $pausedCount = $member->memberships()
                ->where('memberships.status', 'active')
                ->update(['status' => 'paused']);

            if ($pausedCount > 0) {
                $pausedMembershipIds = $member->memberships()
                    ->where('memberships.status', 'paused')
                    ->pluck('memberships.id')
                    ->toArray();

                MemberStatusHistory::create([
                    'member_id' => $member->id,
                    'old_status' => 'active',
                    'new_status' => 'paused',
                    'reason' => "Mitgliedschaften pausiert wegen überfälliger Zahlung",
                    'changed_by' => $user->id,
                    'metadata' => [
                        'paused_memberships' => $pausedCount,
                        'paused_membership_ids' => $pausedMembershipIds,
                        'triggered_by' => 'payment_overdue'
                    ]
                ]);
            }
        }
    }

    /**
     * Prüft ob eine E-Mail-Adresse bereits für dieses Gym existiert
     * Ignoriert gelöschte Mitglieder (soft deleted)
     */
    public function checkEmail(Request $request)
    {
        try {
            $request->validate([
                'email' => [
                    'required',
                    'email:rfc,dns', // Strengere E-Mail-Validierung
                    'max:255'
                ],
            ], [
                'email.required' => 'E-Mail ist erforderlich',
                'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein',
                'email.max' => 'E-Mail-Adresse ist zu lang (maximal 255 Zeichen)'
            ]);

            // Aktuelles Gym des Benutzers ermitteln
            $gym = auth()->user()->currentGym ?? auth()->user()->ownedGyms()->first();

            if (!$gym) {
                return response()->json([
                    'error' => 'Kein Fitnessstudio gefunden'
                ], 400);
            }

            $email = trim(strtolower($request->email));

            // Prüfen ob E-Mail bereits existiert (nur aktive, nicht-gelöschte Mitglieder)
            $existingMember = $gym->members()
                // withTrashed() ENTFERNT - gelöschte Mitglieder werden ignoriert
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($existingMember) {
                $statusMessages = [
                    'active' => 'E-Mail-Adresse ist bereits für ein aktives Mitglied registriert.',
                    'inactive' => 'E-Mail-Adresse ist bereits für ein inaktives Mitglied registriert.',
                    'pending' => 'E-Mail-Adresse ist bereits für ein Mitglied mit ausstehender Aktivierung registriert.',
                    'paused' => 'E-Mail-Adresse ist bereits für ein pausiertes Mitglied registriert.',
                    'overdue' => 'E-Mail-Adresse ist bereits für ein Mitglied mit überfälligen Zahlungen registriert.'
                ];

                $message = $statusMessages[$existingMember->status] ?? 'E-Mail-Adresse ist bereits registriert.';

                return response()->json([
                    'exists' => true,
                    'message' => $message,
                    'member_status' => $existingMember->status,
                    'member_name' => $existingMember->full_name,
                    'member_id' => $existingMember->id
                ], 200);
            }

            // Keine aktiven Mitglieder mit dieser E-Mail gefunden - E-Mail ist verfügbar
            return response()->json([
                'exists' => false,
                'message' => 'E-Mail-Adresse ist verfügbar',
                'email' => $email
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
                'error' => $e->validator->errors()->first('email')
            ], 422);

        } catch (\Exception $e) {
            Log::error('Email check failed', [
                'email' => $request->email ?? 'unknown',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
                'debug' => app()->isLocal() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Enhanced destroy method with validation
     */
    public function destroy(Member $member)
    {
        $this->authorize('delete', $member);

        // Prüfe ob Mitglied gelöscht werden kann
        if (!$member->canBeDeleted()) {
            $blockReason = $member->getDeleteBlockReason();

            return back()->withErrors([
                'delete' => $blockReason['reason'] ?? 'Mitglied kann nicht gelöscht werden.'
            ])->with('error_details', $blockReason);
        }

        $memberName = $member->full_name;

        try {
            DB::beginTransaction();

            /** @var User $user */
            $user = Auth::user();

            // Log deletion in history before soft delete
            MemberStatusHistory::create([
                'member_id' => $member->id,
                'old_status' => $member->status,
                'new_status' => 'deleted',
                'reason' => 'Mitglied gelöscht',
                'changed_by' => $user->id,
                'metadata' => [
                    'deleted_at' => now()->toISOString(),
                    'member_data' => $member->only(['member_number', 'email'])
                ]
            ]);

            $member->delete();

            DB::commit();

            return redirect()->route('members.index')
                ->with('success', "Mitglied {$memberName} wurde erfolgreich gelöscht.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Member deletion failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'delete' => 'Fehler beim Löschen des Mitglieds.'
            ]);
        }
    }
}
