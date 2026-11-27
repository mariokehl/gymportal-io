<?php

namespace App\Http\Controllers\Api\Agent;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\ScannerAccessLog;
use App\Services\MemberService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the Agent API. Every query is bound to the gym of the token.
 */
class AgentReadController extends AgentController
{
    /** Below this, an occupancy profile would be noise presented as advice. */
    private const OCCUPANCY_MIN_VISITS = 200;

    private const OCCUPANCY_MIN_DAYS = 21;

    public function health(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $gym = $this->gym($request);

        return $this->ok([
            'api' => 'gymportal-agent-api',
            'version' => self::VERSION,
            'gym' => ['id' => $gym->id, 'name' => $gym->name, 'slug' => $gym->slug],
            'client' => [
                'name' => $client->name,
                'purpose' => $client->purpose,
                'abilities' => $client->abilities,
                'expires_at' => optional($client->expires_at)->toIso8601String(),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function plans(Request $request): JsonResponse
    {
        $plans = MembershipPlan::where('gym_id', $this->gym($request)->id)
            ->where('is_active', true)
            ->where('is_free_trial_plan', false)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price !== null ? (float) $plan->price : null,
                'setup_fee' => $plan->setup_fee !== null ? (float) $plan->setup_fee : null,
                'billing_cycle' => $plan->billing_cycle,
                'commitment_months' => $plan->commitment_months,
                'cancellation_period' => $plan->cancellation_period,
                'cancellation_period_unit' => $plan->cancellation_period_unit,
                'renewal_months' => $plan->renewal_months,
                'trial_period_days' => $plan->trial_period_days,
            ])
            ->values()
            ->all();

        return $this->ok(['plans' => $plans, 'currency' => 'EUR']);
    }

    /**
     * Exact lookup, for the moment an agent has to find out who is writing.
     * Deliberately no fuzzy matching: a near miss must not expose another
     * member's contract.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'nullable|string|max:255',
            'member_number' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
        ]);

        $given = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

        if ($given === []) {
            return $this->fail('missing_criteria', 'Give email, member_number or phone.', 422);
        }

        $query = Member::where('gym_id', $this->gym($request)->id);

        if (isset($given['email'])) {
            $query->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($given['email']))]);
        }

        if (isset($given['member_number'])) {
            $query->where('member_number', trim($given['member_number']));
        }

        if (isset($given['phone'])) {
            // Stored numbers come in any format, so the comparison happens after
            // normalising both sides, over every member with a phone number.
            $wanted = $this->normalizePhone($given['phone']);
            $members = $wanted === '' ? collect() : $query->whereNotNull('phone')->get()
                ->filter(fn (Member $member) => $this->normalizePhone((string) $member->phone) === $wanted)
                ->values();
        } else {
            $members = $query->limit(25)->get();
        }

        if ($members->count() === 1) {
            $request->attributes->set('agent_member_id', $members->first()->id);
        }

        return $this->ok([
            'match' => match (true) {
                $members->count() === 1 => 'unique',
                $members->count() === 0 => 'none',
                default => 'ambiguous',
            },
            // With several hits the agent has to ask, not pick.
            'members' => $members->count() === 1
                ? [$this->presentMember($members->first())]
                : $members->map(fn (Member $m) => ['id' => $m->id, 'member_number' => $m->member_number])->all(),
        ]);
    }

    public function members(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:30',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = Member::where('gym_id', $this->gym($request)->id);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $term = '%'.addcslashes(mb_strtolower($validated['search']), '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(member_number) LIKE ?', [$term]);
            });
        }

        $page = $query->orderBy('last_name')->orderBy('first_name')->paginate($validated['per_page'] ?? 25);

        return $this->ok(
            ['members' => collect($page->items())->map(fn (Member $m) => $this->presentMember($m))->all()],
            ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        );
    }

    public function showMember(Request $request, string $memberId): JsonResponse
    {
        $member = $this->member($request, $memberId);

        if (! $member) {
            return $this->memberNotFound();
        }

        return $this->ok(['member' => $this->presentMember($member)]);
    }

    public function contract(Request $request, string $memberId): JsonResponse
    {
        $member = $this->member($request, $memberId);

        if (! $member) {
            return $this->memberNotFound();
        }

        $active = $member->activeMembership();
        $contract = $this->contractMembership($member);
        $memberships = $member->memberships()->with('membershipPlan')->orderByDesc('start_date')->get();

        return $this->ok([
            'member' => ['id' => $member->id, 'member_number' => $member->member_number, 'status' => $member->status],
            'active_membership' => $active ? $this->presentMembership($active->loadMissing('membershipPlan')) : null,
            // During a free period this is the paid contract behind it; its dates
            // are the ones a cancellation or pause refers to.
            'contract_membership' => $contract ? $this->presentMembership($contract->loadMissing('membershipPlan')) : null,
            'memberships' => $memberships->map(fn ($m) => $this->presentMembership($m))->all(),
            'as_of' => today()->toDateString(),
        ]);
    }

    /**
     * Answers "why does my QR code not work" with facts, each naming its source.
     *
     * Two kinds of answer: `blockers` applies the rules the door scanner runs
     * at the member's own location (ScannerController::verifyMembership), in
     * the same order. `last_denied_attempt` and `recent_attempts` are what
     * actually happened, read from the access log the scanners and the
     * check-in station write, with the reason the portal stored at the time.
     * Rules that depend on the device (add-on dispensers) or on another
     * location (cross-location access) only show up there.
     */
    public function access(Request $request, string $memberId): JsonResponse
    {
        $member = $this->member($request, $memberId);

        if (! $member) {
            return $this->memberNotFound();
        }

        $guest = $member->hasGuestAccess();
        $membership = $guest ? null : $member->activeMembership();
        $config = $member->accessConfig;
        $lastCheckIn = CheckIn::where('gym_id', $member->gym_id)
            ->where('member_id', $member->id)
            ->orderByDesc('check_in_time')
            ->first();

        $blockers = [];
        $notes = [];

        // The scanner lets "extern" through (guests are stored that way).
        if (! $member->isActive() && ! $member->isExtern()) {
            $blockers[] = [
                'code' => 'member_not_active',
                'detail' => 'Member status is "'.$member->status.'" ('.(Member::STATUS_LABELS[$member->status] ?? $member->status).').',
            ];
        }

        if ($member->isExtern()) {
            $notes[] = 'Status "extern": the door scanner admits the member, the check-in station in the app does not.';
        }

        // Guest access skips the contract check entirely.
        if (! $guest) {
            if (! $membership) {
                $blockers[] = ['code' => 'no_active_membership', 'detail' => 'No membership with status "active".'];
            } elseif ($membership->start_date->isFuture()) {
                // activeMembership() falls back to a contract that has not
                // started yet; the scanner refuses it until its start date.
                $blockers[] = [
                    'code' => 'membership_not_started',
                    'detail' => 'Membership starts on '.$membership->start_date->toDateString().'.',
                ];
            }
        }

        // Without a configuration the app shows the QR code by default, and
        // the scanner does not look at the configuration for QR scans.
        if ($config && ! $config->qr_code_enabled && ! $config->nfc_enabled) {
            $blockers[] = ['code' => 'no_access_medium_enabled', 'detail' => 'Neither QR code nor NFC is enabled.'];
        }

        if (! $config) {
            $notes[] = 'No access configuration yet: the QR code is on by default.';
        }

        $openPayments = Payment::where('gym_id', $member->gym_id)
            ->where('member_id', $member->id)
            ->where('status', 'failed')
            ->count();

        $recent = $this->accessAttemptQuery($member)->latest('id')->limit(5)->get();
        $lastDenied = $recent->first(fn (ScannerAccessLog $log) => ! $log->access_granted)
            ?? $this->accessAttemptQuery($member)->denied()->latest('id')->first();

        $presented = $this->presentAttempts($member, $recent->concat([$lastDenied])->filter());

        return $this->ok([
            'member' => ['id' => $member->id, 'status' => $member->status, 'guest_access' => $guest],
            'should_have_access' => $blockers === [],
            'blockers' => $blockers,
            'notes' => $notes,
            'access' => [
                'qr_code_enabled' => $config ? (bool) $config->qr_code_enabled : null,
                'qr_code_invalidated_at' => $config ? $this->dateTimeString($config->getRawOriginal('qr_code_invalidated_at')) : null,
                'nfc_enabled' => $config ? (bool) $config->nfc_enabled : null,
            ],
            'failed_payments' => $openPayments,
            'last_check_in' => $lastCheckIn ? [
                'at' => $this->dateTimeString($lastCheckIn->getRawOriginal('check_in_time')),
                'method' => $lastCheckIn->check_in_method,
            ] : null,
            'last_denied_attempt' => $lastDenied ? $presented[$lastDenied->id] : null,
            'recent_attempts' => $recent->map(fn (ScannerAccessLog $log) => $presented[$log->id])->values()->all(),
        ]);
    }

    /**
     * Every scan and station check-in of a member, granted or denied, with the
     * reason the portal stored when it refused.
     */
    public function accessAttempts(Request $request, string $memberId): JsonResponse
    {
        $member = $this->member($request, $memberId);

        if (! $member) {
            return $this->memberNotFound();
        }

        $result = $request->query('result', 'all');

        if (! in_array($result, ['all', 'denied', 'granted'], true)) {
            return $this->fail('invalid_result', 'result is all, denied or granted.', 422);
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 200);

        $query = $this->accessAttemptQuery($member);

        if ($result === 'denied') {
            $query->denied();
        } elseif ($result === 'granted') {
            $query->granted();
        }

        $rows = $query->latest('id')->limit($limit)->get();
        $presented = $this->presentAttempts($member, $rows);

        return $this->ok([
            'total' => $this->accessAttemptQuery($member)->count(),
            'denied' => $this->accessAttemptQuery($member)->denied()->count(),
            'attempts' => $rows->map(fn (ScannerAccessLog $log) => $presented[$log->id])->values()->all(),
        ]);
    }

    /**
     * Log entries of one member: the ones at the member's own location and the
     * ones other locations of the organisation wrote with this location as
     * home. Scans of the same member id in an unrelated studio carry no home
     * location and stay out.
     */
    private function accessAttemptQuery(Member $member): Builder
    {
        return ScannerAccessLog::where('member_id', (string) $member->id)
            ->where(fn (Builder $q) => $q->where('gym_id', $member->gym_id)->orWhere('home_gym_id', $member->gym_id));
    }

    /**
     * Shapes log entries without the request data the log also keeps (IP,
     * user agent, NFC id), keyed by log id.
     *
     * @param  Collection<int, ScannerAccessLog>  $logs
     * @return array<int, array<string, mixed>>
     */
    private function presentAttempts(Member $member, Collection $logs): array
    {
        $gymIds = $logs->pluck('gym_id')->unique()->all();

        $locations = Gym::whereIn('id', $gymIds)->pluck('name', 'id');
        $devices = GymScanner::whereIn('gym_id', $gymIds)
            ->whereIn('device_number', $logs->pluck('device_number')->filter()->unique()->all())
            ->get(['gym_id', 'device_number', 'device_name'])
            ->keyBy(fn (GymScanner $scanner) => $scanner->gym_id.':'.$scanner->device_number);

        return $logs->mapWithKeys(function (ScannerAccessLog $log) use ($member, $locations, $devices) {
            $metadata = $log->metadata ?? [];
            $device = $log->device_number === null ? null
                : ($devices->get($log->gym_id.':'.$log->device_number)?->device_name ?? 'Scanner #'.$log->device_number);

            return [$log->id => [
                'at' => $this->dateTimeString($log->getRawOriginal('created_at')),
                'granted' => (bool) $log->access_granted,
                // The portal's own wording, as the studio sees it in the live log.
                'reason' => $log->access_granted ? null : $log->denial_reason,
                // contract or location: a denial the operator can resolve.
                'denial_kind' => $metadata['denial_kind'] ?? null,
                // Set by the check-in station, e.g. MEMBERSHIP_NOT_STARTED.
                'code' => $metadata['code'] ?? null,
                'scan_type' => $log->scan_type,
                'device' => $device,
                'location' => [
                    'gym_id' => $log->gym_id,
                    'name' => $locations->get($log->gym_id),
                    'own' => (int) $log->gym_id === (int) $member->gym_id,
                ],
            ]];
        })->all();
    }

    public function checkIns(Request $request, string $memberId): JsonResponse
    {
        $member = $this->member($request, $memberId);

        if (! $member) {
            return $this->memberNotFound();
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 200);

        $rows = CheckIn::where('gym_id', $member->gym_id)
            ->where('member_id', $member->id)
            ->orderByDesc('check_in_time')
            ->limit($limit)
            ->get();

        return $this->ok([
            'total' => CheckIn::where('gym_id', $member->gym_id)->where('member_id', $member->id)->count(),
            'check_ins' => $rows->map(fn (CheckIn $c) => [
                'check_in_time' => $this->dateTimeString($c->getRawOriginal('check_in_time')),
                'check_out_time' => $this->dateTimeString($c->getRawOriginal('check_out_time')),
                'method' => $c->check_in_method,
            ])->all(),
        ]);
    }

    public function payments(Request $request, string $memberId): JsonResponse
    {
        $member = $this->member($request, $memberId);

        if (! $member) {
            return $this->memberNotFound();
        }

        $limit = min(max((int) $request->query('limit', 24), 1), 200);

        $rows = Payment::where('gym_id', $member->gym_id)
            ->where('member_id', $member->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $present = fn (Payment $p) => [
            'id' => $p->id,
            'amount' => (float) $p->amount,
            'currency' => $p->currency,
            'description' => $p->description,
            'status' => $p->status,
            'due_date' => $this->dateString($p->getRawOriginal('due_date')),
            'paid_date' => $this->dateString($p->getRawOriginal('paid_date')),
            'payment_method' => $p->payment_method,
        ];

        return $this->ok([
            'summary' => [
                'failed' => $rows->where('status', 'failed')->count(),
                'pending' => $rows->where('status', 'pending')->count(),
                'failed_amount' => round((float) $rows->where('status', 'failed')->sum('amount'), 2),
            ],
            'payments' => $rows->map($present)->all(),
        ]);
    }

    /**
     * When is it quiet? An aggregate only, never single visits. Without enough
     * visits the answer says so instead of recommending an hour.
     */
    public function occupancy(Request $request): JsonResponse
    {
        $days = min(max((int) $request->query('days', 56), 7), 365);
        $since = now()->subDays($days)->startOfDay();

        $times = CheckIn::where('gym_id', $this->gym($request)->id)
            ->where('check_in_time', '>=', $since)
            ->pluck('check_in_time');

        $grid = [];
        $distinctDays = [];

        foreach ($times as $time) {
            $at = Carbon::parse($time);
            $weekday = (int) $at->isoWeekday();
            $hour = (int) $at->format('G');
            $grid[$weekday][$hour] = ($grid[$weekday][$hour] ?? 0) + 1;
            $distinctDays[$at->toDateString()] = true;
        }

        $visits = $times->count();
        $enough = $visits >= self::OCCUPANCY_MIN_VISITS && count($distinctDays) >= self::OCCUPANCY_MIN_DAYS;

        $quietest = null;
        $busiest = null;

        if ($enough) {
            foreach ($grid as $weekday => $hours) {
                asort($hours);
                $quietest[$weekday] = ['hour' => array_key_first($hours), 'visits' => reset($hours)];
                $busiest[$weekday] = ['hour' => array_key_last($hours), 'visits' => end($hours)];
            }
            ksort($quietest);
            ksort($busiest);
        }

        ksort($grid);

        return $this->ok([
            'window_days' => $days,
            'visits' => $visits,
            'days_with_visits' => count($distinctDays),
            'data_quality' => $enough ? 'ok' : 'insufficient_data',
            // ISO weekday (1 = Monday) => hour of day => visits in the window
            'visits_by_weekday_and_hour' => $grid,
            'quietest_hour_by_weekday' => $quietest,
            'busiest_hour_by_weekday' => $busiest,
            'note' => 'Quietest hour only among hours with at least one visit.',
        ]);
    }

    /**
     * The key figures the portal shows on its dashboard. Money figures need
     * payments.read on top of stats.read.
     */
    public function stats(Request $request, MemberService $memberService): JsonResponse
    {
        $stats = $memberService->getGymStatistics($this->gym($request)->id);

        if (! $this->client($request)->can('payments.read')) {
            $stats = $this->withoutMoney($stats);
        }

        return $this->ok($stats, ['as_of' => now()->toIso8601String()]);
    }

    /**
     * Removes every revenue figure the dashboard statistics carry: the
     * financial block, the revenue card, revenue per member and per plan.
     */
    private function withoutMoney(array $stats): array
    {
        unset($stats['financial_stats'], $stats['detailed_stats']['average_revenue_per_member']);

        $stats['main_stats'] = array_values(array_filter(
            $stats['main_stats'] ?? [],
            fn (array $card) => ! str_contains((string) ($card['key'] ?? ''), 'revenue'),
        ));

        $stats['plan_stats'] = collect($stats['plan_stats'] ?? [])
            ->map(fn (array $plan) => collect($plan)->reject(fn ($value, $key) => str_contains((string) $key, 'revenue'))->all())
            ->values()
            ->all();

        $stats['money_omitted'] = 'needs ability payments.read';

        return $stats;
    }

    /**
     * Members with a running contract who have not shown up for a while: the
     * raw material for "who is about to quit".
     */
    public function inactiveMembers(Request $request): JsonResponse
    {
        $days = min(max((int) $request->query('days', 30), 7), 365);
        $limit = min(max((int) $request->query('limit', 50), 1), 200);
        $gymId = $this->gym($request)->id;
        $threshold = now()->subDays($days);

        $lastVisits = CheckIn::where('gym_id', $gymId)
            ->select('member_id', DB::raw('MAX(check_in_time) as last_visit'))
            ->groupBy('member_id')
            ->pluck('last_visit', 'member_id');

        $members = Member::where('gym_id', $gymId)
            ->where('status', 'active')
            ->whereHas('memberships', fn ($q) => $q->where('status', 'active'))
            ->get()
            ->map(function (Member $member) use ($lastVisits) {
                $last = $lastVisits[$member->id] ?? null;

                return [
                    'id' => $member->id,
                    'member_number' => $member->member_number,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'joined_date' => $this->dateString($member->getRawOriginal('joined_date')),
                    'last_visit' => $last ? (string) $last : null,
                    'days_since_last_visit' => $last ? (int) Carbon::parse($last)->startOfDay()->diffInDays(today()) : null,
                ];
            })
            ->filter(fn (array $row) => $row['last_visit'] === null || Carbon::parse($row['last_visit'])->lt($threshold))
            ->sortByDesc(fn (array $row) => $row['days_since_last_visit'] ?? PHP_INT_MAX)
            ->values();

        return $this->ok([
            'threshold_days' => $days,
            'count' => $members->count(),
            'members' => $members->take($limit)->all(),
        ]);
    }

    /**
     * +49, 0049 and a leading 0 mean the same German number. A foreign number
     * keeps its country code, so 0043 664 (Austria) never equals the German
     * area code 04366.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with(ltrim($phone), '+')) {
            $digits = '00'.$digits;
        }

        return match (true) {
            str_starts_with($digits, '0049') => substr($digits, 4),
            str_starts_with($digits, '00') => $digits,
            str_starts_with($digits, '0') => substr($digits, 1),
            str_starts_with($digits, '49') && strlen($digits) >= 11 => substr($digits, 2),
            default => $digits,
        };
    }
}
