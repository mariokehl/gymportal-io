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
use App\Services\MemberStatusService;
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
                  ->orWhere('member_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sorting logic
        $sortBy = $request->get('sortBy', 'member_number');
        $sortDirection = $request->get('sortDirection', 'asc');

        // Validate sort parameters
        $allowedSortColumns = ['name', 'member_number', 'last_check_in', 'contract_end_date'];
        $allowedDirections = ['asc', 'desc'];

        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'member_number';
        }

        if (!in_array($sortDirection, $allowedDirections)) {
            $sortDirection = 'asc';
        }

        // Apply sorting based on column using database-agnostic approach
        switch ($sortBy) {
            case 'name':
                $query->orderBy('first_name', $sortDirection)
                      ->orderBy('last_name', $sortDirection);
                break;
            case 'member_number':
                $query->orderBy('member_number', $sortDirection);
                break;
            case 'last_check_in':
                // Load members with their last check-in and sort in PHP for database compatibility
                $members = $query->with(['checkIns' => function($q) {
                    $q->orderBy('check_in_time', 'desc')->limit(1);
                }])->get();

                // Sort by last check-in time
                $members = $members->sort(function($a, $b) use ($sortDirection) {
                    $aTime = $a->checkIns->first()?->check_in_time;
                    $bTime = $b->checkIns->first()?->check_in_time;

                    // Handle nulls - put them at the end regardless of sort direction
                    if ($aTime === null && $bTime === null) return 0;
                    if ($aTime === null) return 1;
                    if ($bTime === null) return -1;

                    if ($sortDirection === 'desc') {
                        return $bTime <=> $aTime;
                    } else {
                        return $aTime <=> $bTime;
                    }
                });

                // Convert back to paginated collection
                $perPage = 15;
                $currentPage = request()->get('page', 1);
                $offset = ($currentPage - 1) * $perPage;
                $total = $members->count();

                $members = $members->slice($offset, $perPage)->values();

                // Create a manual paginator
                $members = new \Illuminate\Pagination\LengthAwarePaginator(
                    $members,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => request()->url(), 'pageName' => 'page']
                );
                $members->withQueryString();
                break;
            case 'contract_end_date':
                // Load members with their active membership and sort in PHP for database compatibility
                $members = $query->with(['memberships' => function($q) {
                    $q->where('status', 'active')->orderBy('cancellation_date', 'desc')->limit(1);
                }])->get();

                // Sort by contract end date (cancellation_date)
                $members = $members->sort(function($a, $b) use ($sortDirection) {
                    $aDate = $a->memberships->first()?->cancellation_date;
                    $bDate = $b->memberships->first()?->cancellation_date;

                    // Handle nulls - put them at the end regardless of sort direction
                    if ($aDate === null && $bDate === null) return 0;
                    if ($aDate === null) return 1;
                    if ($bDate === null) return -1;

                    if ($sortDirection === 'desc') {
                        return $bDate <=> $aDate;
                    } else {
                        return $aDate <=> $bDate;
                    }
                });

                // Convert back to paginated collection
                $perPage = 15;
                $currentPage = request()->get('page', 1);
                $offset = ($currentPage - 1) * $perPage;
                $total = $members->count();

                $members = $members->slice($offset, $perPage)->values();

                // Create a manual paginator
                $members = new \Illuminate\Pagination\LengthAwarePaginator(
                    $members,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => request()->url(), 'pageName' => 'page']
                );
                $members->withQueryString();
                break;
            default:
                $query->orderBy('member_number', 'asc');
                break;
        }

        // Only paginate if we haven't already done manual pagination above
        if (!in_array($sortBy, ['last_check_in', 'contract_end_date'])) {
            $members = $query->paginate(15)->withQueryString();
        }

        // Add additional data to each member
        $members->getCollection()->transform(function ($member) {
            $member->last_check_in = $member->last_check_in;
            $member->contract_end_date = $member->activeMembership()?->cancellation_date;
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
            'filters' => $request->only(['search', 'status', 'sortBy', 'sortDirection'])
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
            'paymentMethods' => $paymentMethods,
            'gymSettings' => [
                'contracts_start_first_of_month' => $gym->contracts_start_first_of_month,
                'free_trial_membership_name' => $gym->free_trial_membership_name ?? 'Gratis-Testzeitraum',
            ],
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

        // Prüfen ob als Gast angelegt werden soll
        $createAsGuest = $request->boolean('create_as_guest');

        // Basis-Validierungsregeln (für alle Mitglieder)
        $rules = [
            'salutation' => ['required', Rule::in(['Herr', 'Frau', 'Divers'])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('members', 'email')
                    ->where('gym_id', $user->current_gym_id)
                    ->whereNull('deleted_at')
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'custom_member_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('members', 'member_number')
                    ->where('gym_id', $user->current_gym_id)
                    ->whereNull('deleted_at')
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'address_addition' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'paused', 'overdue', 'pending'])],
            'notes' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'legal_guardian_first_name' => ['nullable', 'string', 'max:255'],
            'legal_guardian_last_name' => ['nullable', 'string', 'max:255'],
            'create_as_guest' => ['nullable', 'boolean'],
        ];

        // Zusätzliche Validierungsregeln für reguläre Mitglieder (nicht Gäste)
        if (!$createAsGuest) {
            $rules['joined_date'] = ['required', 'date'];
            $rules['allow_past_start_date'] = ['nullable', 'boolean'];
            $rules['billing_anchor_date'] = ['nullable', 'date', 'after_or_equal:joined_date'];
            $rules['payment_method'] = ['required', Rule::in($enabledPaymentMethods)];
            $rules['start_immediately'] = ['nullable', 'boolean'];
        } else {
            // Optionale Felder für Gäste
            $rules['joined_date'] = ['nullable', 'date'];
        }

        $validated = $request->validate($rules);

        // Custom validation for billing_anchor_date - must be on the same day of month as joined_date
        if (!$createAsGuest && !empty($validated['billing_anchor_date']) && !empty($validated['joined_date'])) {
            $joinedDate = \Carbon\Carbon::parse($validated['joined_date']);
            $billingDate = \Carbon\Carbon::parse($validated['billing_anchor_date']);

            if ($joinedDate->day !== $billingDate->day) {
                return back()
                    ->withErrors(['billing_anchor_date' => "Das Abrechnungsdatum muss am {$joinedDate->day}. des Monats liegen (wie das Startdatum)."])
                    ->withInput();
            }
        }

        // Member number - use custom if provided, otherwise generate
        $memberData = [];
        if (!empty($validated['custom_member_number'])) {
            $memberData['member_number'] = $validated['custom_member_number'];
        } else {
            $memberData['member_number'] = MemberService::generateMemberNumber($gym, 'M');
        }

        // Add gym_id to the validated data
        $memberData['gym_id'] = $user->current_gym_id;
        $memberData['user_id'] = $user->id;

        // Gastzugang: Mitglied direkt als aktiv mit guest_access anlegen
        if ($createAsGuest) {
            $memberData['status'] = 'active';
            $memberData['guest_access'] = true;
            $memberData['guest_access_granted_at'] = now();
            $memberData['guest_access_granted_by'] = $user->id;
            $memberData['joined_date'] = $validated['joined_date'] ?? now()->toDateString();
        } else {
            // Member status für reguläre Mitglieder
            $memberData['status'] = 'pending';
        }

        try {
            DB::beginTransaction();

            // Create the member
            $newMember = Member::create(
                array_merge(
                    $validated,
                    $memberData,
                )
            );

            // Für Gäste: Keine Mitgliedschaft und keine Zahlungsmethode anlegen
            if (!$createAsGuest) {
                // Get membership plan to calculate end date
                $membershipPlan = MembershipPlan::findOrFail($request->membership_plan_id);

                // Create membership (with optional free period for first-of-month start)
                $memberService = app(MemberService::class);
                $startImmediately = $validated['start_immediately'] ?? false;
                $startDate = \Carbon\Carbon::parse($validated['joined_date']);

                $membershipResult = $memberService->createMembershipWithFreePeriod(
                    $newMember,
                    $membershipPlan,
                    $startDate,
                    'pending',
                    $startImmediately
                );

                $newMembership = $membershipResult['membership'];
                $freeMembership = $membershipResult['free_membership'];

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

                // Pass billing_anchor_date to payment service if provided
                $billingAnchorDate = !empty($validated['billing_anchor_date'])
                    ? \Carbon\Carbon::parse($validated['billing_anchor_date'])
                    : null;
                $paymentService->createPendingPayment($newMember, $newMembership, $newPaymentMethod, $billingAnchorDate);
            }

            DB::commit();

            // Vertrag wird erst bei Aktivierung der Mitgliedschaft generiert (MembershipActivated Event)

        } catch (\Exception $e) {
            DB::rollBack();

            return back()
                ->withErrors(['general' => 'Fehler beim Erstellen des Mitglieds. Bitte versuchen Sie es erneut.'])
                ->withInput();
        }

        $successMessage = $createAsGuest
            ? 'Gast-Mitglied wurde erfolgreich erstellt.'
            : 'Mitglied wurde erfolgreich erstellt.';

        return redirect()->route('members.show', ['member' => $newMember->id])
            ->with('success', $successMessage);
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
            'memberships' => function ($query) {
                $query->with(['membershipPlan' => function ($q) {
                    $q->withTrashed();
                }]);
            },
            'paymentMethods',
            'payments.chargebacks',
            'payments.refunds',
            'checkIns' => function ($query) {
                $query->latest()->take(10);
            },
            'accessConfig',
            'statusHistory.changedBy:id,first_name,last_name',
            'ageVerifiedByUser:id,first_name,last_name',
            'legalGuardian:id,first_name,last_name,member_number'
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

        // Get available membership plans for adding new memberships
        $membershipPlans = MembershipPlan::where('gym_id', $member->gym_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Members/Show', [
            'member' => $member,
            'availablePaymentMethods' => $member->gym->getEnabledPaymentMethods(),
            'membershipPlans' => $membershipPlans,
            'updatedPayments' => session('updated_payments', false) ? $member->payments : null,
            'contractsEnabled' => $member->gym->isOnlineContractEnabled(),
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
            'member_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('members', 'member_number')
                    ->ignore($member->id)
                    ->where('gym_id', $member->gym_id)
                    ->whereNull('deleted_at')
            ],
            'salutation' => ['required', Rule::in(['Herr', 'Frau', 'Divers'])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('members', 'email')
                    ->ignore($member->id)
                    ->where('gym_id', $member->gym_id)
                    ->whereNull('deleted_at')
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_addition' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'paused', 'overdue', 'pending'])],
            'joined_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'legal_guardian_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'legal_guardian_first_name' => ['nullable', 'string', 'max:255'],
            'legal_guardian_last_name' => ['nullable', 'string', 'max:255'],
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
            $memberStatusService = app(MemberStatusService::class);
            $memberStatusService->handleStatusChangeActions($member, $currentStatus, $newStatus, Auth::user());

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
     * Prüft ob eine Mitgliedsnummer bereits für dieses Gym existiert
     * Ignoriert gelöschte Mitglieder (soft deleted)
     */
    public function checkMemberNumber(Request $request)
    {
        try {
            $request->validate([
                'member_number' => [
                    'required',
                    'string',
                    'max:50'
                ],
                'member_id' => [
                    'nullable',
                    'integer',
                    'exists:members,id'
                ],
            ], [
                'member_number.required' => 'Mitgliedsnummer ist erforderlich',
                'member_number.string' => 'Mitgliedsnummer muss eine Zeichenkette sein',
                'member_number.max' => 'Mitgliedsnummer ist zu lang (maximal 50 Zeichen)'
            ]);

            // Aktuelles Gym des Benutzers ermitteln
            $gym = auth()->user()->currentGym ?? auth()->user()->ownedGyms()->first();

            if (!$gym) {
                return response()->json([
                    'error' => 'Kein Fitnessstudio gefunden'
                ], 400);
            }

            $memberNumber = trim($request->member_number);
            $memberId = $request->member_id;

            // Prüfen ob Mitgliedsnummer bereits existiert (nur aktive, nicht-gelöschte Mitglieder)
            // Exclude the current member if member_id is provided (for edit mode)
            $query = $gym->members()
                ->where('member_number', $memberNumber);

            if ($memberId) {
                $query->where('id', '!=', $memberId);
            }

            $existingMember = $query->first();

            if ($existingMember) {
                return response()->json([
                    'exists' => true,
                    'message' => 'Mitgliedsnummer ist bereits vergeben.',
                    'member_name' => $existingMember->full_name,
                    'member_id' => $existingMember->id
                ], 200);
            }

            // Keine Mitglieder mit dieser Nummer gefunden - Nummer ist verfügbar
            return response()->json([
                'exists' => false,
                'message' => 'Mitgliedsnummer ist verfügbar',
                'member_number' => $memberNumber
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->validator->errors()->first('member_number'),
                'errors' => $e->validator->errors()->getMessages(),
                'error' => 'Validierungsfehler'
            ], 422);

        } catch (\Exception $e) {
            Log::error('Member number check failed', [
                'member_number' => $request->member_number ?? 'unknown',
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
                    'indisposable',
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
                'message' => $e->validator->errors()->first('email'),
                'errors' => $e->validator->errors()->getMessages(),
                'error' => 'Validierungsfehler'
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

    /**
     * Send welcome email to member
     */
    public function sendWelcome(Member $member)
    {
        $this->authorize('update', $member);

        try {
            $contractPath = $member->getMembershipOverview()['paid']
                ->pluck('contract_file_path')
                ->filter()
                ->first();

            app(MemberService::class)->sendWelcomeEmail($member, $member->gym, $contractPath);

            return back()->with('success', 'E-Mail wurde erfolgreich versendet.');

        } catch (Exception $e) {
            Log::error('Failed to send welcome email to member', [
                'member_id' => $member->id,
                'member_email' => $member->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Fehler beim Versenden der E-Mail. Bitte versuchen Sie es erneut.');
        }
    }

    /**
     * Toggle age verification for a member
     */
    public function toggleAgeVerification(Member $member)
    {
        $this->authorize('update', $member);

        if ($member->age_verified) {
            $member->revokeAgeVerification();
            return back()->with('success', 'Altersverifizierung wurde entfernt.');
        } else {
            $member->verifyAge(auth()->id());
            return back()->with('success', 'Alter wurde verifiziert.');
        }
    }

    /**
     * Toggle guest access for a member
     */
    public function toggleGuestAccess(Member $member)
    {
        $this->authorize('update', $member);

        if ($member->guest_access) {
            $member->revokeGuestAccess();
            return back()->with('success', 'Gastzugang wurde entzogen.');
        } else {
            $member->grantGuestAccess(auth()->id());
            return back()->with('success', 'Gastzugang wurde gewährt.');
        }
    }

    /**
     * Search for members (used for legal guardian selection)
     */
    public function search(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $search = $request->get('search', '');
        $excludeId = $request->get('exclude_id');

        $query = Member::query()
            ->where('gym_id', $user->current_gym_id)
            ->where('status', 'active')
            ->select('id', 'first_name', 'last_name', 'member_number');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('member_number', 'like', "%{$search}%");
            });
        }

        $members = $query->orderBy('last_name')->orderBy('first_name')->limit(10)->get();

        return response()->json([
            'members' => $members
        ]);
    }

    /**
     * Store a new membership for an existing member
     */
    public function storeMembership(Request $request, Member $member)
    {
        $this->authorize('update', $member);

        /** @var Gym $gym */
        $gym = $member->gym;

        $validated = $request->validate([
            'membership_plan_id' => ['required', 'exists:membership_plans,id'],
            'start_date' => ['required', 'date'],
            'allow_past_start_date' => ['nullable', 'boolean'],
            'billing_anchor_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        // Check if member has a default payment method
        $defaultPaymentMethod = $member->paymentMethods()
            ->where('is_default', true)
            ->first();

        if (!$defaultPaymentMethod) {
            return back()->withErrors([
                'membership' => 'Das Mitglied hat keine Standard-Zahlungsmethode hinterlegt. Bitte zuerst eine Zahlungsmethode anlegen.'
            ]);
        }

        // Verify membership plan belongs to the same gym
        $membershipPlan = MembershipPlan::where('id', $validated['membership_plan_id'])
            ->where('gym_id', $gym->id)
            ->where('is_active', true)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // Calculate end date based on commitment months
            $startDate = \Carbon\Carbon::parse($validated['start_date']);
            $endDate = $membershipPlan->commitment_months > 0
                ? $startDate->copy()->addMonths($membershipPlan->commitment_months)->subDay()
                : null;

            // Create the new membership
            $membership = \App\Models\Membership::create([
                'member_id' => $member->id,
                'membership_plan_id' => $membershipPlan->id,
                'start_date' => $validated['start_date'],
                'end_date' => $endDate,
                'status' => 'pending'
            ]);

            // Create payments using PaymentService (same logic as Members/Create)
            $paymentService = app(PaymentService::class);

            // Create setup fee payment if applicable
            $paymentService->createSetupFeePayment($member, $membership, $defaultPaymentMethod);

            // Create pending payment for the membership
            $billingAnchorDate = !empty($validated['billing_anchor_date'])
                ? \Carbon\Carbon::parse($validated['billing_anchor_date'])
                : null;
            $paymentService->createPendingPayment($member, $membership, $defaultPaymentMethod, $billingAnchorDate);

            DB::commit();

            // Vertrag wird erst bei Aktivierung der Mitgliedschaft generiert (MembershipActivated Event)

            return back()->with('success', 'Mitgliedschaft wurde erfolgreich erstellt.');

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create membership for member', [
                'member_id' => $member->id,
                'membership_plan_id' => $validated['membership_plan_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors([
                'membership' => 'Fehler beim Erstellen der Mitgliedschaft: ' . $e->getMessage()
            ]);
        }
    }
}
