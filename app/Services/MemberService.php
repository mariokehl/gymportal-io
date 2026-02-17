<?php

namespace App\Services;

use App\Mail\WelcomeMemberMail;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class MemberService
{
    /**
     * Mitgliedsnummer generieren
     */
    public static function generateMemberNumber(Gym $gym, string $prefix = 'M'): string
    {
        $prefix = $prefix . str_pad($gym->id, 3, '0', STR_PAD_LEFT);
        $year = date('y');
        $lastNumber = Member::withTrashed()
            ->where('gym_id', $gym->id)
            ->where('member_number', 'like', $prefix . $year . '%')
            ->orderBy('member_number', 'desc')
            ->value('member_number');

        if ($lastNumber) {
            $lastSequence = intval(substr($lastNumber, -4));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix . $year . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Platzhalter-E-Mail generieren für Mitglieder ohne E-Mail-Adresse
     */
    public static function generatePlaceholderEmail(): string
    {
        $uid = (string) \Illuminate\Support\Str::uuid();

        return "{$uid}@import.local";
    }

    /**
     * Mitgliedschaft erstellen
     */
    public function createMembership(Member $member, MembershipPlan $plan, string $status = 'active'): Membership
    {
        return Membership::create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $member->joined_date,
            'end_date' => $plan->commitment_months > 0
                ? Carbon::parse($member->joined_date)
                    ->addMonths($plan->commitment_months)
                    ->subDay()
                : null, // Keine Mindestlaufzeit = unbefristet
            'status' => $status
        ]);
    }

    /**
     * Prüft ob der Vertrag zum 1. des Monats starten soll
     */
    public function shouldStartFirstOfMonth(Gym $gym, Carbon $startDate): bool
    {
        return $gym->contracts_start_first_of_month && $startDate->day !== 1;
    }

    /**
     * Holt oder erstellt den Gratis-Testzeitraum-Plan für ein Gym
     */
    public function getOrCreateFreeTrialPlan(Gym $gym): MembershipPlan
    {
        $plan = MembershipPlan::where('gym_id', $gym->id)
            ->where('is_free_trial_plan', true)
            ->first();

        if (!$plan) {
            $plan = MembershipPlan::create([
                'gym_id' => $gym->id,
                'name' => $gym->free_trial_membership_name ?? 'Gratis-Testzeitraum',
                'description' => 'Kostenloser Testzeitraum bis zum Vertragsbeginn',
                'price' => 0,
                'setup_fee' => 0,
                'billing_cycle' => 'monthly',
                'is_active' => false, // Nicht in normaler Planauswahl sichtbar
                'is_free_trial_plan' => true,
                'commitment_months' => 0,
                'cancellation_period_days' => 0,
            ]);
        } else {
            // Namen aktualisieren wenn geändert
            if ($plan->name !== $gym->free_trial_membership_name && $gym->free_trial_membership_name) {
                $plan->update(['name' => $gym->free_trial_membership_name]);
            }
        }

        return $plan;
    }

    /**
     * Erstellt eine Gratis-Mitgliedschaft für den Überbrückungszeitraum
     */
    public function createFreePeriodMembership(
        Member $member,
        Carbon $startDate,
        Carbon $endDate,
        ?Membership $linkedPaidMembership = null
    ): Membership {
        $gym = $member->gym;
        $freeTrialPlan = $this->getOrCreateFreeTrialPlan($gym);

        $freeMembership = Membership::create([
            'member_id' => $member->id,
            'membership_plan_id' => $freeTrialPlan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
        ]);

        // Verknüpfung mit der zahlungspflichtigen Mitgliedschaft
        if ($linkedPaidMembership) {
            $linkedPaidMembership->update([
                'linked_free_membership_id' => $freeMembership->id
            ]);
        }

        return $freeMembership;
    }

    /**
     * Berechnet das Enddatum des Gratis-Zeitraums (letzter Tag des Monats)
     */
    public function calculateFreePeriodEndDate(Carbon $startDate): Carbon
    {
        return $startDate->copy()->endOfMonth();
    }

    /**
     * Berechnet das Startdatum des zahlungspflichtigen Vertrags (1. des Folgemonats)
     */
    public function calculatePaidMembershipStartDate(Carbon $startDate): Carbon
    {
        return $startDate->copy()->addMonth()->startOfMonth();
    }

    /**
     * Erstellt Mitgliedschaft(en) mit optionalem Gratis-Zeitraum bei Start zum 1. des Monats
     *
     * @return array{membership: Membership, free_membership: ?Membership}
     */
    public function createMembershipWithFreePeriod(
        Member $member,
        MembershipPlan $plan,
        Carbon $startDate,
        string $status = 'active',
        bool $startImmediately = false
    ): array {
        $gym = $member->gym;
        $freeMembership = null;

        // Prüfen ob Gratis-Zeitraum erstellt werden soll
        if (!$startImmediately && $this->shouldStartFirstOfMonth($gym, $startDate)) {
            $freePeriodEnd = $this->calculateFreePeriodEndDate($startDate);
            $paidStart = $this->calculatePaidMembershipStartDate($startDate);

            // Mitglied joined_date auf den 1. des Folgemonats setzen
            $originalJoinedDate = $member->joined_date;
            $member->joined_date = $paidStart;

            // Zahlungspflichtige Mitgliedschaft erstellen
            $membership = $this->createMembership($member, $plan, $status);

            // joined_date zurücksetzen
            $member->joined_date = $originalJoinedDate;
            $member->save();

            // Gratis-Mitgliedschaft erstellen und verknüpfen
            $freeMembership = $this->createFreePeriodMembership(
                $member,
                $startDate,
                $freePeriodEnd,
                $membership
            );
        } else {
            // Normale Mitgliedschaft erstellen
            $membership = $this->createMembership($member, $plan, $status);
        }

        return [
            'membership' => $membership,
            'free_membership' => $freeMembership,
        ];
    }

    /**
     * Welcome E-Mail senden
     */
    public function sendWelcomeEmail(Member $member, Gym $gym, ?string $contractPath = null): void
    {
        try {
            Mail::to($member->email)->send(new WelcomeMemberMail($member, $gym, [], $contractPath));
        } catch (\Exception $e) {
            logger()->error('Failed to send welcome email', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Umfassende Gym-Statistiken berechnen
     */
    public function getGymStatistics(int $gymId): array
    {
        $currentMonth = Carbon::now();
        $lastMonth = $currentMonth->copy()->subMonth();

        return [
            'main_stats' => $this->getMainStatistics($gymId, $currentMonth, $lastMonth),
            'detailed_stats' => $this->getDetailedStatistics($gymId),
            'status_distribution' => $this->getStatusDistribution($gymId),
            'plan_stats' => $this->getPlanStatistics($gymId),
            'financial_stats' => $this->getFinancialStatistics($gymId, $currentMonth, $lastMonth)
        ];
    }

    /**
     * Haupt-Statistiken für Dashboard Cards
     */
    public function getMainStatistics(int $gymId, Carbon $currentMonth, Carbon $lastMonth): array
    {
        // Aktuelle Werte
        $activeMembers = Member::active()->where('gym_id', $gymId)->count();
        $newMembersThisMonth = $this->getNewMembersCount($gymId, $currentMonth);
        $expiring = $this->getExpiringMembershipsCount($gymId, $currentMonth);
        $monthlyRevenue = $this->calculateMonthlyRevenue($gymId);

        // Vergleichswerte vom letzten Monat
        $newMembersLastMonth = $this->getNewMembersCount($gymId, $lastMonth);
        $expiredLastMonth = $this->getExpiringMembershipsCount($gymId, $lastMonth);
        $lastMonthRevenue = $this->calculateMonthlyRevenue($gymId, $lastMonth);

        return [
            [
                'title' => 'Aktive Mitglieder',
                'value' => $activeMembers,
                'change' => $this->calculatePercentageChange($activeMembers, $activeMembers - $newMembersThisMonth),
                'icon' => 'users',
                'color' => 'green'
            ],
            [
                'title' => 'Neue Verträge',
                'value' => $newMembersThisMonth,
                'change' => $this->calculatePercentageChange($newMembersThisMonth, $newMembersLastMonth),
                'icon' => 'file-plus',
                'color' => 'blue'
            ],
            [
                'title' => 'Monatsumsatz',
                'value' => number_format($monthlyRevenue, 2, ',', '.') . ' €',
                'change' => $this->calculatePercentageChange($monthlyRevenue, $lastMonthRevenue),
                'icon' => 'dollar-sign',
                'color' => 'green'
            ],
            [
                'title' => 'Vertragserneuerungen',
                'value' => $expiring,
                'change' => $this->calculatePercentageChange($expiring, $expiredLastMonth),
                'icon' => 'bar-chart',
                'color' => $expiring > 5 ? 'red' : 'gray'
            ]
        ];
    }

    /**
     * Detaillierte Statistiken
     */
    public function getDetailedStatistics(int $gymId): array
    {
        $totalMembers = Member::where('gym_id', $gymId)->count();
        $activeMembers = Member::active()->where('gym_id', $gymId)->count();

        return [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'inactive_members' => Member::inactive()->where('gym_id', $gymId)->count(),
            'pending_members' => Member::pending()->where('gym_id', $gymId)->count(),
            'overdue_members' => Member::overdue()->where('gym_id', $gymId)->count(),
            'paused_members' => Member::paused()->where('gym_id', $gymId)->count(),
            'new_members_this_month' => $this->getNewMembersCount($gymId, Carbon::now()),
            'expiring_this_month' => $this->getExpiringMembershipsCount($gymId, Carbon::now()),
            'average_revenue_per_member' => $activeMembers > 0 ?
                ($this->calculateMonthlyRevenue($gymId) / $activeMembers) : 0
        ];
    }

    /**
     * Status-Verteilung für Charts
     */
    public function getStatusDistribution(int $gymId): array
    {
        return [
            'active' => Member::active()->where('gym_id', $gymId)->count(),
            'inactive' => Member::inactive()->where('gym_id', $gymId)->count(),
            'pending' => Member::pending()->where('gym_id', $gymId)->count(),
            'overdue' => Member::overdue()->where('gym_id', $gymId)->count(),
            'paused' => Member::paused()->where('gym_id', $gymId)->count()
        ];
    }

    /**
     * Membership Plans Statistiken
     */
    public function getPlanStatistics(int $gymId): array
    {
        return MembershipPlan::where('gym_id', $gymId)
            ->where('is_active', true)
            ->withCount(['memberships as active_memberships_count' => function($query) {
                $query->where('status', 'active');
            }])
            ->get()
            ->map(function($plan) {
                $monthlyRevenue = $this->calculatePlanMonthlyRevenue($plan);
                return [
                    'name' => $plan->name,
                    'price' => $plan->formatted_price,
                    'billing_cycle' => $plan->billing_cycle_text,
                    'active_memberships' => $plan->active_memberships_count,
                    'monthly_revenue' => number_format($monthlyRevenue, 2, ',', '.') . ' €',
                    'total_revenue' => number_format($plan->active_memberships_count * $plan->price, 2, ',', '.') . ' €'
                ];
            })
            ->sortByDesc('active_memberships')
            ->values()
            ->all();
    }

    /**
     * Finanzstatistiken mit Zeitvergleich
     */
    public function getFinancialStatistics(int $gymId, Carbon $currentMonth, Carbon $lastMonth): array
    {
        $currentRevenue = $this->calculateMonthlyRevenue($gymId, $currentMonth);
        $lastMonthRevenue = $this->calculateMonthlyRevenue($gymId, $lastMonth);
        $yearToDateRevenue = $this->calculateYearToDateRevenue($gymId);

        return [
            'current_monthly_revenue' => $currentRevenue,
            'last_month_revenue' => $lastMonthRevenue,
            'revenue_change' => $this->calculatePercentageChange($currentRevenue, $lastMonthRevenue),
            'year_to_date_revenue' => $yearToDateRevenue,
            'average_monthly_revenue' => $yearToDateRevenue / $currentMonth->month,
            'projected_yearly_revenue' => ($yearToDateRevenue / $currentMonth->month) * 12
        ];
    }

    /**
     * Neue Mitglieder für einen bestimmten Monat zählen
     */
    private function getNewMembersCount(int $gymId, Carbon $month): int
    {
        return Member::where('gym_id', $gymId)
            ->whereMonth('created_at', $month->month)
            ->whereYear('created_at', $month->year)
            ->count();
    }

    /**
     * Auslaufende Mitgliedschaften für einen bestimmten Monat zählen
     */
    private function getExpiringMembershipsCount(int $gymId, Carbon $month): int
    {
        return Membership::whereHas('member', function($query) use ($gymId) {
                $query->where('gym_id', $gymId);
            })
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereMonth('end_date', $month->month)
            ->whereYear('end_date', $month->year)
            ->count();
    }

    /**
     * Monatlichen Umsatz berechnen
     */
    private function calculateMonthlyRevenue(int $gymId, ?Carbon $month = null): float
    {
        $query = Membership::whereHas('member', function($query) use ($gymId) {
                $query->where('gym_id', $gymId);
            })
            ->where('status', 'active')
            ->with('membershipPlan');

        if ($month) {
            $query->whereDate('created_at', '<=', $month->endOfMonth());
        }

        return $query->get()->sum(function($membership) {
            $plan = $membership->membershipPlan;
            if (!$plan) return 0;

            return $this->convertToMonthlyPrice($plan->price, $plan->billing_cycle);
        });
    }

    /**
     * Jahresumsatz bis heute berechnen
     */
    private function calculateYearToDateRevenue(int $gymId): float
    {
        $startOfYear = Carbon::now()->startOfYear();

        return Membership::whereHas('member', function($query) use ($gymId) {
                $query->where('gym_id', $gymId);
            })
            ->where('status', 'active')
            ->whereDate('created_at', '>=', $startOfYear)
            ->with('membershipPlan')
            ->get()
            ->sum(function($membership) {
                $plan = $membership->membershipPlan;
                if (!$plan) return 0;

                $monthsActive = Carbon::parse($membership->created_at)->diffInMonths(Carbon::now()) + 1;
                $monthlyPrice = $this->convertToMonthlyPrice($plan->price, $plan->billing_cycle);

                return $monthlyPrice * $monthsActive;
            });
    }

    /**
     * Plan-spezifischen monatlichen Umsatz berechnen
     */
    private function calculatePlanMonthlyRevenue(MembershipPlan $plan): float
    {
        $monthlyPrice = $this->convertToMonthlyPrice($plan->price, $plan->billing_cycle);
        return $monthlyPrice * $plan->active_memberships_count;
    }

    /**
     * Preis auf monatliche Basis umrechnen
     */
    private function convertToMonthlyPrice(float $price, string $billingCycle): float
    {
        return match($billingCycle) {
            'monthly' => $price,
            'quarterly' => $price / 3,
            'yearly' => $price / 12,
            default => $price
        };
    }

    /**
     * Prozentuale Änderung berechnen
     */
    private function calculatePercentageChange(float $current, float $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $sign = $change >= 0 ? '+' : '';

        return $sign . number_format($change, 0) . '%';
    }

    /**
     * Dashboard-ready Statistiken abrufen
     */
    public function getDashboardStats(int $gymId): array
    {
        $stats = $this->getGymStatistics($gymId);

        // Zusätzliche Dashboard-spezifische Berechnungen
        $criticalAlerts = $this->getCriticalAlerts($gymId);
        $trends = $this->calculateTrends($gymId);

        return array_merge($stats, [
            'alerts' => $criticalAlerts,
            'trends' => $trends,
            'last_updated' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Kritische Warnungen ermitteln
     */
    private function getCriticalAlerts(int $gymId): array
    {
        $alerts = [];

        // Viele ausstehende SEPA-Mandate
        $pendingSepa = Member::withPendingSepaMandate()->where('gym_id', $gymId)->count();
        if ($pendingSepa > 5) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$pendingSepa} SEPA-Mandate warten auf Unterschrift",
                'action_url' => '/members?filter=pending_sepa'
            ];
        }

        // Viele überfällige Mitglieder
        $overdue = Member::overdue()->where('gym_id', $gymId)->count();
        if ($overdue > 10) {
            $alerts[] = [
                'type' => 'error',
                'message' => "{$overdue} Mitglieder sind überfällig",
                'action_url' => '/members?filter=overdue'
            ];
        }

        // Viele auslaufende Verträge
        $expiring = $this->getExpiringMembershipsCount($gymId, Carbon::now());
        if ($expiring > 15) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$expiring} Verträge laufen diesen Monat aus",
                'action_url' => '/memberships?filter=expiring'
            ];
        }

        return $alerts;
    }

    /**
     * Trends über die letzten Monate berechnen
     */
    private function calculateTrends(int $gymId): array
    {
        $months = [];
        $currentMonth = Carbon::now();

        // Daten für die letzten 6 Monate sammeln
        for ($i = 5; $i >= 0; $i--) {
            $month = $currentMonth->copy()->subMonths($i);
            $months[] = [
                'month' => $month->format('M Y'),
                'new_members' => $this->getNewMembersCount($gymId, $month),
                'revenue' => $this->calculateMonthlyRevenue($gymId, $month),
                'active_members' => Member::active()
                    ->where('gym_id', $gymId)
                    ->whereDate('created_at', '<=', $month->endOfMonth())
                    ->count()
            ];
        }

        return $months;
    }

    /**
     * Export-fähige Statistiken für Reports
     */
    public function getExportableStats(int $gymId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->startOfMonth();
        $endDate = $endDate ?? Carbon::now()->endOfMonth();

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'description' => $startDate->format('M Y')
            ],
            'summary' => [
                'total_members' => Member::where('gym_id', $gymId)->count(),
                'active_members' => Member::active()->where('gym_id', $gymId)->count(),
                'new_members' => Member::where('gym_id', $gymId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'cancelled_members' => Member::inactive()->where('gym_id', $gymId)
                    ->whereBetween('updated_at', [$startDate, $endDate])
                    ->count(),
                'total_revenue' => $this->calculatePeriodRevenue($gymId, $startDate, $endDate),
                'average_member_value' => $this->calculateAverageMemberValue($gymId)
            ],
            'detailed_breakdown' => $this->getDetailedStatistics($gymId),
            'plan_performance' => $this->getPlanStatistics($gymId)
        ];
    }

    /**
     * Umsatz für einen bestimmten Zeitraum berechnen
     */
    private function calculatePeriodRevenue(int $gymId, Carbon $startDate, Carbon $endDate): float
    {
        return Membership::whereHas('member', function($query) use ($gymId) {
                $query->where('gym_id', $gymId);
            })
            ->where('status', 'active')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('membershipPlan')
            ->get()
            ->sum(function($membership) {
                $plan = $membership->membershipPlan;
                return $plan ? $plan->price : 0;
            });
    }

    /**
     * Durchschnittlichen Wert pro Mitglied berechnen
     */
    private function calculateAverageMemberValue(int $gymId): float
    {
        $activeMembers = Member::active()->where('gym_id', $gymId)->count();
        if ($activeMembers === 0) return 0;

        $totalMonthlyRevenue = $this->calculateMonthlyRevenue($gymId);
        return $totalMonthlyRevenue / $activeMembers;
    }

    /**
     * Mitglieder-Performance für einen bestimmten Zeitraum
     */
    public function getMemberPerformanceStats(int $gymId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        return [
            'period_days' => $days,
            'check_ins' => [
                'total' => Member::where('gym_id', $gymId)
                    ->whereHas('checkIns', function($q) use ($startDate) {
                        $q->where('check_in_time', '>=', $startDate);
                    })
                    ->count(),
                'unique_members' => Member::where('gym_id', $gymId)
                    ->whereHas('checkIns', function($q) use ($startDate) {
                        $q->where('check_in_time', '>=', $startDate);
                    })
                    ->distinct()
                    ->count('id'),
                'average_per_member' => $this->calculateAverageCheckInsPerMember($gymId, $days)
            ],
            'engagement' => [
                'highly_active' => $this->getHighlyActiveMembers($gymId, $days),
                'inactive' => $this->getInactiveMembers($gymId, $days),
                'at_risk' => $this->getAtRiskMembers($gymId)
            ]
        ];
    }

    /**
     * Durchschnittliche Check-ins pro Mitglied berechnen
     */
    private function calculateAverageCheckInsPerMember(int $gymId, int $days): float
    {
        $startDate = Carbon::now()->subDays($days);
        $activeMembers = Member::active()->where('gym_id', $gymId)->count();

        if ($activeMembers === 0) return 0;

        $totalCheckIns = Member::where('gym_id', $gymId)
            ->join('check_ins', 'members.id', '=', 'check_ins.member_id')
            ->where('check_ins.check_in_time', '>=', $startDate)
            ->count();

        return $totalCheckIns / $activeMembers;
    }

    /**
     * Hochaktive Mitglieder identifizieren
     */
    private function getHighlyActiveMembers(int $gymId, int $days): int
    {
        $startDate = Carbon::now()->subDays($days);
        $threshold = max(1, floor($days / 3)); // Mindestens jeden 3. Tag

        return Member::where('gym_id', $gymId)
            ->whereHas('checkIns', function($q) use ($startDate) {
                $q->where('check_in_time', '>=', $startDate);
            }, '>=', $threshold)
            ->count();
    }

    /**
     * Inaktive Mitglieder identifizieren
     */
    private function getInactiveMembers(int $gymId, int $days): int
    {
        $startDate = Carbon::now()->subDays($days);

        return Member::active()
            ->where('gym_id', $gymId)
            ->whereDoesntHave('checkIns', function($q) use ($startDate) {
                $q->where('check_in_time', '>=', $startDate);
            })
            ->count();
    }

    /**
     * Gefährdete Mitglieder identifizieren (überfällig oder lange inaktiv)
     */
    private function getAtRiskMembers(int $gymId): int
    {
        return Member::where('gym_id', $gymId)
            ->where(function($query) {
                // Überfällige Mitglieder
                $query->where('status', 'overdue')
                    // Oder lange inaktiv (30+ Tage ohne Check-in)
                    ->orWhere(function($subQuery) {
                        $thirtyDaysAgo = Carbon::now()->subDays(30);
                        $subQuery->where('status', 'active')
                            ->whereDoesntHave('checkIns', function($checkInQuery) use ($thirtyDaysAgo) {
                                $checkInQuery->where('check_in_time', '>=', $thirtyDaysAgo);
                            });
                    });
            })
            ->count();
    }
}
