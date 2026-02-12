<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Course;
use App\Models\CourseSchedule;
use App\Models\CourseBooking;
use App\Models\CheckIn;
use App\Models\EmailTemplate;
use App\Models\GymLegalUrl;
use App\Models\GymScanner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GymDataImportService
{
    private PaymentService $paymentService;
    private MemberService $memberService;

    public function __construct(PaymentService $paymentService, MemberService $memberService)
    {
        $this->paymentService = $paymentService;
        $this->memberService = $memberService;
    }

    /**
     * ID mappings for resolving references
     */
    private array $idMappings = [
        'plans' => [],
        'members' => [],
        'memberships' => [],
        'courses' => [],
        'schedules' => [],
    ];

    /**
     * Validate import data structure and return preview stats
     */
    public function validateImportData(array $data): array
    {
        $errors = [];
        $warnings = [];
        $stats = [
            'members' => 0,
            'memberships' => 0,
            'payments' => 0,
            'membership_plans' => 0,
            'courses' => 0,
            'course_schedules' => 0,
            'check_ins' => 0,
            'email_templates' => 0,
            'legal_urls' => 0,
            'scanners' => 0,
        ];

        // Check metadata
        if (!isset($data['export_metadata']['version'])) {
            $errors[] = 'Fehlende Export-Version in den Metadaten';
        }

        if (!isset($data['export_metadata']['application']) || $data['export_metadata']['application'] !== 'gymportal.io') {
            $warnings[] = 'Die Datei wurde möglicherweise nicht von gymportal.io exportiert';
        }

        // Validate membership plans
        if (isset($data['membership_plans']) && is_array($data['membership_plans'])) {
            foreach ($data['membership_plans'] as $index => $plan) {
                if (empty($plan['name'])) {
                    $errors[] = "Vertrag an Position {$index}: Name fehlt";
                }
                if (!isset($plan['price']) || !is_numeric($plan['price'])) {
                    $errors[] = "Vertrag an Position {$index}: Ungültiger Preis";
                }
                $stats['membership_plans']++;
            }
        }

        // Validate members
        if (isset($data['members']) && is_array($data['members'])) {
            $emails = [];
            foreach ($data['members'] as $index => $member) {
                if (empty($member['email'])) {
                    $errors[] = "Mitglied an Position {$index}: E-Mail fehlt";
                } elseif (in_array($member['email'], $emails)) {
                    $errors[] = "Mitglied an Position {$index}: Doppelte E-Mail-Adresse '{$member['email']}'";
                } else {
                    $emails[] = $member['email'];
                }

                if (empty($member['first_name']) || empty($member['last_name'])) {
                    $errors[] = "Mitglied an Position {$index}: Name fehlt";
                }

                $stats['members']++;

                // Count nested data
                if (isset($member['memberships']) && is_array($member['memberships'])) {
                    foreach ($member['memberships'] as $membership) {
                        $stats['memberships']++;
                        if (isset($membership['payments']) && is_array($membership['payments'])) {
                            $stats['payments'] += count($membership['payments']);
                        }
                    }
                }

                if (isset($member['check_ins']) && is_array($member['check_ins'])) {
                    $stats['check_ins'] += count($member['check_ins']);
                }
            }
        }

        // Validate courses
        if (isset($data['courses']) && is_array($data['courses'])) {
            foreach ($data['courses'] as $index => $course) {
                if (empty($course['name'])) {
                    $errors[] = "Kurs an Position {$index}: Name fehlt";
                }
                $stats['courses']++;

                if (isset($course['schedules']) && is_array($course['schedules'])) {
                    $stats['course_schedules'] += count($course['schedules']);
                }
            }
        }

        // Count other entities
        if (isset($data['email_templates']) && is_array($data['email_templates'])) {
            $stats['email_templates'] = count($data['email_templates']);
        }

        if (isset($data['legal_urls']) && is_array($data['legal_urls'])) {
            $stats['legal_urls'] = count($data['legal_urls']);
        }

        if (isset($data['scanners']) && is_array($data['scanners'])) {
            $stats['scanners'] = count($data['scanners']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => $stats,
        ];
    }

    /**
     * Import gym data with the specified mode
     */
    public function importGymData(int $gymId, array $data, string $mode): array
    {
        $this->idMappings = [
            'plans' => [],
            'members' => [],
            'memberships' => [],
            'courses' => [],
            'schedules' => [],
        ];

        return DB::transaction(function () use ($gymId, $data, $mode) {
            $stats = [
                'membership_plans' => 0,
                'members' => 0,
                'memberships' => 0,
                'payments' => 0,
                'courses' => 0,
                'course_schedules' => 0,
                'check_ins' => 0,
                'email_templates' => 0,
                'legal_urls' => 0,
                'scanners' => 0,
            ];

            if ($mode === 'replace') {
                $this->deleteExistingData($gymId);
            }

            // Import in dependency order
            if (isset($data['gym_settings'])) {
                $this->importGymSettings($gymId, $data['gym_settings']);
            }

            if (isset($data['membership_plans'])) {
                $stats['membership_plans'] = $this->importMembershipPlans($gymId, $data['membership_plans'], $mode);
            }

            if (isset($data['members'])) {
                $memberStats = $this->importMembers($gymId, $data['members'], $mode);
                $stats['members'] = $memberStats['members'];
                $stats['memberships'] = $memberStats['memberships'];
                $stats['payments'] = $memberStats['payments'];
                $stats['check_ins'] = $memberStats['check_ins'];
            }

            if (isset($data['courses'])) {
                $courseStats = $this->importCourses($gymId, $data['courses'], $mode);
                $stats['courses'] = $courseStats['courses'];
                $stats['course_schedules'] = $courseStats['schedules'];
            }

            // Import course bookings after members and schedules are imported
            if (isset($data['members'])) {
                $this->importCourseBookings($data['members']);
            }

            if (isset($data['email_templates'])) {
                $stats['email_templates'] = $this->importEmailTemplates($gymId, $data['email_templates'], $mode);
            }

            if (isset($data['legal_urls'])) {
                $stats['legal_urls'] = $this->importLegalUrls($gymId, $data['legal_urls'], $mode);
            }

            if (isset($data['scanners'])) {
                $stats['scanners'] = $this->importScanners($gymId, $data['scanners'], $mode);
            }

            return $stats;
        });
    }

    /**
     * Delete all existing data for the gym (replace mode)
     */
    private function deleteExistingData(int $gymId): void
    {
        // Delete in reverse dependency order

        // Course bookings (via course schedules)
        $courseIds = Course::where('gym_id', $gymId)->pluck('id');
        $scheduleIds = CourseSchedule::whereIn('course_id', $courseIds)->pluck('id');
        CourseBooking::whereIn('course_schedule_id', $scheduleIds)->delete();

        // Course schedules
        CourseSchedule::whereIn('course_id', $courseIds)->delete();

        // Courses
        Course::where('gym_id', $gymId)->forceDelete();

        // Check-ins
        CheckIn::where('gym_id', $gymId)->delete();

        // Get member IDs first (needed for multiple deletions)
        $memberIds = Member::where('gym_id', $gymId)->pluck('id');

        // Payment methods (must be deleted before members due to FK constraint)
        PaymentMethod::whereIn('member_id', $memberIds)->forceDelete();

        // Payments (via memberships)
        $membershipIds = Membership::whereIn('member_id', $memberIds)->pluck('id');
        Payment::whereIn('membership_id', $membershipIds)->delete();
        Payment::where('gym_id', $gymId)->delete();

        // Memberships
        Membership::whereIn('member_id', $memberIds)->forceDelete();

        // Members
        Member::where('gym_id', $gymId)->forceDelete();

        // Membership plans
        MembershipPlan::where('gym_id', $gymId)->forceDelete();

        // Email templates
        EmailTemplate::where('gym_id', $gymId)->delete();

        // Legal URLs
        GymLegalUrl::where('gym_id', $gymId)->delete();

        // Scanners
        GymScanner::where('gym_id', $gymId)->delete();
    }

    /**
     * Import gym settings
     */
    private function importGymSettings(int $gymId, array $settings): void
    {
        $gym = Gym::findOrFail($gymId);

        // Only update non-sensitive fields
        $allowedFields = [
            'name', 'display_name', 'description', 'address', 'city',
            'postal_code', 'country', 'latitude', 'longitude', 'phone',
            'email', 'website', 'account_holder', 'iban', 'bic',
            'creditor_identifier', 'primary_color', 'secondary_color',
            'accent_color', 'background_color', 'text_color', 'pwa_enabled',
            'pwa_settings', 'widget_enabled', 'widget_settings',
            'opening_hours', 'social_media', 'member_app_description',
            'payment_methods_config',
        ];

        $updateData = array_intersect_key($settings, array_flip($allowedFields));
        $gym->update($updateData);
    }

    /**
     * Import membership plans
     */
    private function importMembershipPlans(int $gymId, array $plans, string $mode): int
    {
        $count = 0;

        foreach ($plans as $planData) {
            $exportId = $planData['export_id'] ?? null;
            unset($planData['export_id'], $planData['deleted_at']);

            $planData['gym_id'] = $gymId;

            if ($mode === 'append') {
                // Check for existing plan with same name
                $existing = MembershipPlan::where('gym_id', $gymId)
                    ->where('name', $planData['name'])
                    ->first();

                if ($existing) {
                    if ($exportId) {
                        $this->idMappings['plans'][$exportId] = $existing->id;
                    }
                    continue;
                }
            }

            $plan = MembershipPlan::create($planData);

            if ($exportId) {
                $this->idMappings['plans'][$exportId] = $plan->id;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Import members with their nested data
     */
    private function importMembers(int $gymId, array $members, string $mode): array
    {
        $stats = [
            'members' => 0,
            'memberships' => 0,
            'payments' => 0,
            'check_ins' => 0,
        ];

        foreach ($members as $memberData) {
            $exportId = $memberData['export_id'] ?? null;
            $memberships = $memberData['memberships'] ?? [];
            $checkIns = $memberData['check_ins'] ?? [];
            $courseBookings = $memberData['course_bookings'] ?? [];

            unset(
                $memberData['export_id'],
                $memberData['deleted_at'],
                $memberData['memberships'],
                $memberData['check_ins'],
                $memberData['course_bookings']
            );

            $memberData['gym_id'] = $gymId;

            if ($mode === 'append') {
                // Check for existing member with same email
                $existing = Member::where('gym_id', $gymId)
                    ->where('email', $memberData['email'])
                    ->first();

                if ($existing) {
                    if ($exportId) {
                        $this->idMappings['members'][$exportId] = $existing->id;
                    }
                    // Still import memberships for existing member
                    $membershipStats = $this->importMemberships($existing->id, $gymId, $memberships, $mode);
                    $stats['memberships'] += $membershipStats['memberships'];
                    $stats['payments'] += $membershipStats['payments'];
                    continue;
                }
            }

            // Generate member number if not set
            if (empty($memberData['member_number'])) {
                $memberData['member_number'] = MemberService::generateMemberNumber(Gym::findOrFail($gymId));
            }

            $member = Member::create($memberData);

            if ($exportId) {
                $this->idMappings['members'][$exportId] = $member->id;
            }

            $stats['members']++;

            // Import memberships
            $membershipStats = $this->importMemberships($member->id, $gymId, $memberships, $mode);
            $stats['memberships'] += $membershipStats['memberships'];
            $stats['payments'] += $membershipStats['payments'];

            // Import check-ins
            $stats['check_ins'] += $this->importCheckIns($member->id, $gymId, $checkIns);

            // Store course bookings for later (after courses are imported)
            if ($exportId && !empty($courseBookings)) {
                $memberData['_course_bookings'] = $courseBookings;
            }
        }

        return $stats;
    }

    /**
     * Import memberships for a member
     */
    private function importMemberships(int $memberId, int $gymId, array $memberships, string $mode): array
    {
        $stats = ['memberships' => 0, 'payments' => 0];

        foreach ($memberships as $membershipData) {
            $exportId = $membershipData['export_id'] ?? null;
            $planExportId = $membershipData['membership_plan_export_id'] ?? null;
            $payments = $membershipData['payments'] ?? [];

            unset(
                $membershipData['export_id'],
                $membershipData['deleted_at'],
                $membershipData['membership_plan_export_id'],
                $membershipData['payments']
            );

            // Resolve membership plan ID
            $planId = null;
            if ($planExportId && isset($this->idMappings['plans'][$planExportId])) {
                $planId = $this->idMappings['plans'][$planExportId];
            }

            if (!$planId) {
                Log::warning("Could not resolve membership plan for export ID: {$planExportId}");
                continue;
            }

            $membershipData['member_id'] = $memberId;
            $membershipData['membership_plan_id'] = $planId;

            $membership = Membership::create($membershipData);

            if ($exportId) {
                $this->idMappings['memberships'][$exportId] = $membership->id;
            }

            $stats['memberships']++;

            // Import payments
            $stats['payments'] += $this->importPayments($membership->id, $gymId, $memberId, $payments);
        }

        return $stats;
    }

    /**
     * Import payments for a membership
     */
    private function importPayments(int $membershipId, int $gymId, int $memberId, array $payments): int
    {
        $count = 0;

        foreach ($payments as $paymentData) {
            unset($paymentData['export_id']);

            $paymentData['membership_id'] = $membershipId;
            $paymentData['gym_id'] = $gymId;
            $paymentData['member_id'] = $memberId;

            Payment::create($paymentData);
            $count++;
        }

        return $count;
    }

    /**
     * Import check-ins for a member
     */
    private function importCheckIns(int $memberId, int $gymId, array $checkIns): int
    {
        $count = 0;

        foreach ($checkIns as $checkInData) {
            CheckIn::create([
                'member_id' => $memberId,
                'gym_id' => $gymId,
                'check_in_time' => $checkInData['check_in_time'] ?? null,
                'check_out_time' => $checkInData['check_out_time'] ?? null,
                'check_in_method' => $checkInData['check_in_method'] ?? 'manual',
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Import courses with schedules
     */
    private function importCourses(int $gymId, array $courses, string $mode): array
    {
        $stats = ['courses' => 0, 'schedules' => 0];

        foreach ($courses as $courseData) {
            $exportId = $courseData['export_id'] ?? null;
            $schedules = $courseData['schedules'] ?? [];

            unset($courseData['export_id'], $courseData['deleted_at'], $courseData['schedules']);

            $courseData['gym_id'] = $gymId;

            if ($mode === 'append') {
                // Check for existing course with same name
                $existing = Course::where('gym_id', $gymId)
                    ->where('name', $courseData['name'])
                    ->first();

                if ($existing) {
                    if ($exportId) {
                        $this->idMappings['courses'][$exportId] = $existing->id;
                    }
                    // Import schedules for existing course
                    $stats['schedules'] += $this->importCourseSchedules($existing->id, $schedules);
                    continue;
                }
            }

            $course = Course::create($courseData);

            if ($exportId) {
                $this->idMappings['courses'][$exportId] = $course->id;
            }

            $stats['courses']++;

            // Import schedules
            $stats['schedules'] += $this->importCourseSchedules($course->id, $schedules);
        }

        return $stats;
    }

    /**
     * Import course schedules
     */
    private function importCourseSchedules(int $courseId, array $schedules): int
    {
        $count = 0;

        foreach ($schedules as $scheduleData) {
            $exportId = $scheduleData['export_id'] ?? null;

            unset($scheduleData['export_id']);

            $scheduleData['course_id'] = $courseId;

            $schedule = CourseSchedule::create($scheduleData);

            if ($exportId) {
                $this->idMappings['schedules'][$exportId] = $schedule->id;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Import course bookings (after members and schedules exist)
     */
    private function importCourseBookings(array $members): void
    {
        foreach ($members as $memberData) {
            $memberExportId = $memberData['export_id'] ?? null;
            $courseBookings = $memberData['course_bookings'] ?? [];

            if (!$memberExportId || !isset($this->idMappings['members'][$memberExportId])) {
                continue;
            }

            $memberId = $this->idMappings['members'][$memberExportId];

            foreach ($courseBookings as $bookingData) {
                $scheduleExportId = $bookingData['course_schedule_export_id'] ?? null;

                if (!$scheduleExportId || !isset($this->idMappings['schedules'][$scheduleExportId])) {
                    continue;
                }

                CourseBooking::create([
                    'member_id' => $memberId,
                    'course_schedule_id' => $this->idMappings['schedules'][$scheduleExportId],
                    'status' => $bookingData['status'] ?? 'booked',
                ]);
            }
        }
    }

    /**
     * Import email templates
     */
    private function importEmailTemplates(int $gymId, array $templates, string $mode): int
    {
        $count = 0;

        foreach ($templates as $templateData) {
            unset($templateData['export_id']);

            $templateData['gym_id'] = $gymId;

            if ($mode === 'append') {
                // Check for existing template with same name and type
                $existing = EmailTemplate::where('gym_id', $gymId)
                    ->where('name', $templateData['name'])
                    ->where('type', $templateData['type'])
                    ->first();

                if ($existing) {
                    continue;
                }
            }

            // Don't import as default if appending
            if ($mode === 'append') {
                $templateData['is_default'] = false;
            }

            EmailTemplate::create($templateData);
            $count++;
        }

        return $count;
    }

    /**
     * Import legal URLs
     */
    private function importLegalUrls(int $gymId, array $urls, string $mode): int
    {
        $count = 0;

        foreach ($urls as $urlData) {
            $urlData['gym_id'] = $gymId;

            if ($mode === 'append') {
                // Check for existing URL of same type
                $existing = GymLegalUrl::where('gym_id', $gymId)
                    ->where('type', $urlData['type'])
                    ->first();

                if ($existing) {
                    continue;
                }
            }

            GymLegalUrl::create($urlData);
            $count++;
        }

        return $count;
    }

    /**
     * Import scanners (without sensitive tokens)
     */
    private function importScanners(int $gymId, array $scanners, string $mode): int
    {
        $count = 0;

        foreach ($scanners as $scannerData) {
            unset($scannerData['export_id']);

            $scannerData['gym_id'] = $gymId;

            if ($mode === 'append') {
                // Check for existing scanner with same device number
                $existing = GymScanner::where('gym_id', $gymId)
                    ->where('device_number', $scannerData['device_number'])
                    ->first();

                if ($existing) {
                    continue;
                }
            }

            // Token will be auto-generated by the model
            GymScanner::create($scannerData);
            $count++;
        }

        return $count;
    }

    /**
     * Validate CSV data and return preview stats
     */
    public function validateCsvData(array $rows, int $gymId): array
    {
        $errors = [];
        $warnings = [];
        $stats = [
            'rows' => count($rows),
            'valid_rows' => 0,
            'plans_matched' => 0,
            'existing_members' => 0,
            'new_members' => 0,
        ];

        $requiredColumns = ['name'];
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            foreach ($requiredColumns as $col) {
                if (!in_array($col, $columns)) {
                    $errors[] = "Pflichtspalte '{$col}' fehlt in der CSV-Datei.";
                }
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings, 'stats' => $stats];
        }

        $plans = MembershipPlan::where('gym_id', $gymId)->get();

        foreach ($rows as $index => $row) {
            $lineNum = $index + 2; // +2 for header row and 1-based indexing

            if (empty(trim($row['name'] ?? ''))) {
                $warnings[] = "Zeile {$lineNum}: Name fehlt, wird übersprungen.";
                continue;
            }

            if (empty(trim($row['email'] ?? ''))) {
                $warnings[] = "Zeile {$lineNum}: E-Mail fehlt, wird mit Platzhalter-E-Mail importiert.";
            }

            $stats['valid_rows']++;

            // Check for duplicate email (will be imported with generated email)
            $existing = Member::where('gym_id', $gymId)->where('email', trim($row['email']))->exists();
            if ($existing) {
                $stats['existing_members']++;
                $warnings[] = "Zeile {$lineNum}: E-Mail '{$row['email']}' existiert bereits — wird mit Platzhalter-E-Mail importiert.";
            } else {
                $stats['new_members']++;
            }

            // Check if plan can be matched by name (tarif) or price (monatsbeitrag)
            $tarifName = trim($row['tarif'] ?? '');
            $price = $this->parseCsvPrice($row['monatsbeitrag'] ?? null);
            $plan = $this->findPlan($plans, $tarifName, $price);

            if ($plan) {
                $stats['plans_matched']++;
            } elseif ($tarifName && $price !== null) {
                $warnings[] = "Zeile {$lineNum}: Kein aktiver Tarif für '{$tarifName}' / {$price} EUR gefunden.";
            } elseif ($price !== null) {
                $warnings[] = "Zeile {$lineNum}: Kein aktiver Tarif für Beitrag {$price} EUR gefunden.";
            } elseif ($tarifName) {
                $warnings[] = "Zeile {$lineNum}: Kein aktiver Tarif für '{$tarifName}' gefunden.";
            } else {
                $warnings[] = "Zeile {$lineNum}: Kein Tarif und kein Monatsbeitrag angegeben.";
            }
        }

        return [
            'valid' => empty($errors) && $stats['valid_rows'] > 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => $stats,
        ];
    }

    /**
     * Import CSV data: creates Members, Memberships, PaymentMethods, and first Payment
     */
    public function importCsvData(int $gymId, array $rows, string $startDate, string $paymentMethodType, bool $deleteExisting = false): array
    {
        $stats = [
            'members_created' => 0,
            'memberships_created' => 0,
            'payments_created' => 0,
            'payment_methods_created' => 0,
            'skipped' => 0,
            'deleted' => [],
            'errors' => [],
        ];

        $gym = \App\Models\Gym::findOrFail($gymId);
        $plans = MembershipPlan::where('gym_id', $gymId)->get();
        $startDateCarbon = Carbon::parse($startDate);

        DB::beginTransaction();

        try {
            if ($deleteExisting) {
                $stats['deleted'] = $this->deleteAllGymMemberData($gymId);
            }

            foreach ($rows as $index => $row) {
                $lineNum = $index + 2;

                try {
                    $this->importCsvRow($gym, $row, $plans, $startDateCarbon, $paymentMethodType, $stats, $lineNum);
                } catch (\Exception $e) {
                    $stats['errors'][] = "Zeile {$lineNum}: {$e->getMessage()}";
                    $stats['skipped']++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    /**
     * Delete all member-related data for a gym
     */
    private function deleteAllGymMemberData(int $gymId): array
    {
        $gym = Gym::findOrFail($gymId);
        $memberIds = Member::withTrashed()->where('gym_id', $gymId)->pluck('id');

        // Delete Mollie customers before removing payment methods
        $mollieCustomerIds = PaymentMethod::withTrashed()
            ->whereIn('member_id', $memberIds)
            ->whereNotNull('mollie_customer_id')
            ->pluck('mollie_customer_id')
            ->unique();

        $mollieCustomersDeleted = 0;
        if ($mollieCustomerIds->isNotEmpty()) {
            $mollieService = app(MollieService::class);
            foreach ($mollieCustomerIds as $customerId) {
                try {
                    $mollieService->deleteCustomer($gym, $customerId);
                    $mollieCustomersDeleted++;
                } catch (\Exception $e) {
                    Log::warning("Mollie-Kunde {$customerId} konnte nicht gelöscht werden: {$e->getMessage()}");
                }
            }
        }

        // Delete check-ins before members (FK constraint)
        CheckIn::where('gym_id', $gymId)->delete();

        $deleted = [
            'payments' => Payment::where('gym_id', $gymId)->delete(),
            'payment_methods' => PaymentMethod::withTrashed()->whereIn('member_id', $memberIds)->forceDelete(),
            'memberships' => Membership::withTrashed()->whereIn('member_id', $memberIds)->forceDelete(),
            'members' => 0,
            'mollie_customers' => $mollieCustomersDeleted,
        ];

        $deleted['members'] = Member::withTrashed()->where('gym_id', $gymId)->forceDelete();

        return $deleted;
    }

    /**
     * Import a single CSV row
     */
    private function importCsvRow(
        \App\Models\Gym $gym,
        array $row,
        $plans,
        Carbon $startDate,
        string $paymentMethodType,
        array &$stats,
        int $lineNum,
    ): void {
        $email = trim($row['email'] ?? '');
        $name = trim($row['name'] ?? '');

        if (empty($name)) {
            $stats['skipped']++;
            return;
        }

        // Parse name into first_name / last_name
        $nameParts = $this->splitName($name);

        // Generate placeholder email if missing
        if (empty($email)) {
            $email = MemberService::generatePlaceholderEmail();
        }

        // Map CSV fields to member data
        $memberData = array_filter([
            'salutation' => trim($row['anrede'] ?? '') ?: null,
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'email' => $email,
            'phone' => trim($row['telefon'] ?? '') ?: null,
            'birth_date' => $this->parseCsvDate($row['geburtsdatum'] ?? null),
            'address' => $this->buildAddress($row),
            'address_addition' => trim($row['adresszusatz'] ?? '') ?: null,
            'city' => trim($row['adresse_ort'] ?? '') ?: null,
            'postal_code' => trim($row['adresse_plz'] ?? '') ?: null,
            'country' => strtoupper(trim($row['land'] ?? '')) ?: 'DE',
        ], fn($v) => $v !== null);

        // Check for duplicate email
        $existingMember = Member::where('gym_id', $gym->id)->where('email', $email)->first();

        if ($existingMember) {
            // Duplicate email: create new member with generated unique email
            $memberData['email'] = MemberService::generatePlaceholderEmail();
            $memberData['notes'] = "Doppelte E-Mail beim CSV-Import: {$email}";
        }

        $memberData['gym_id'] = $gym->id;
        $memberData['member_number'] = MemberService::generateMemberNumber($gym);
        $memberData['status'] = 'pending';
        $memberData['joined_date'] = $startDate->format('Y-m-d');
        $member = Member::create($memberData);
        $stats['members_created']++;

        // Find matching MembershipPlan by name (tarif) or price (monatsbeitrag)
        $tarifName = trim($row['tarif'] ?? '');
        $price = $this->parseCsvPrice($row['monatsbeitrag'] ?? null);
        $plan = $this->findPlan($plans, $tarifName, $price);

        if (!$plan) {
            if ($tarifName && $price !== null) {
                $stats['errors'][] = "Zeile {$lineNum}: Kein aktiver Tarif für '{$tarifName}' / {$price} EUR gefunden.";
            } elseif ($price !== null) {
                $stats['errors'][] = "Zeile {$lineNum}: Kein aktiver Tarif für {$price} EUR gefunden.";
            } elseif ($tarifName) {
                $stats['errors'][] = "Zeile {$lineNum}: Kein aktiver Tarif für '{$tarifName}' gefunden.";
            } else {
                $stats['errors'][] = "Zeile {$lineNum}: Kein Tarif und kein Monatsbeitrag angegeben.";
            }
            $stats['skipped']++;
            return;
        }

        // Check for existing active membership with same plan
        $existingMembership = Membership::where('member_id', $member->id)
            ->where('membership_plan_id', $plan->id)
            ->whereIn('status', ['active', 'pending'])
            ->first();

        $hasRealEmail = !str_ends_with($member->email, '@import.local');

        if (!$existingMembership) {
            $membershipStatus = $hasRealEmail ? 'active' : 'pending';

            $membership = $this->memberService->createMembership($member, $plan, $membershipStatus);
            $stats['memberships_created']++;

            // Update member status (pending if no real email)
            $member->update(['status' => $hasRealEmail ? 'active' : 'pending']);
        } else {
            $membership = $existingMembership;
        }

        // Create PaymentMethod (only if member has no active one)
        $hasActivePaymentMethod = $member->paymentMethods()
            ->where('status', 'active')
            ->exists();

        if (!$hasActivePaymentMethod) {
            $iban = trim($row['iban'] ?? '') ?: null;
            $accountHolder = trim($row['kontoinhaber'] ?? '') ?: null;

            // Validate account holder: must be a name (letters, spaces, hyphens, dots, apostrophes)
            // Anything containing digits or other non-name characters is invalid
            $invalidAccountHolder = $accountHolder && !preg_match('/^[\p{L}\s\-\.\'\,]+$/u', $accountHolder);

            if ($invalidAccountHolder) {
                Log::warning("Zeile {$lineNum}: Ungültiger Kontoinhaber '{$accountHolder}', Zahlungsart wird als fehlgeschlagen markiert.");
            }

            $paymentMethod = $this->createPaymentMethodForType(
                $member,
                $paymentMethodType,
                $iban,
                $accountHolder,
                $gym,
                $invalidAccountHolder
            );

            if ($paymentMethod) {
                $stats['payment_methods_created']++;
            }
        }

        // Create payments via PaymentService (only if membership has no payments yet)
        $hasPayments = Payment::where('membership_id', $membership->id)->exists();

        if (!$hasPayments) {
            $activePaymentMethod = $member->paymentMethods()
                ->where('is_default', true)
                ->first();

            // Setup fee payment (only if plan has a setup fee)
            $setupFeePayment = $this->paymentService->createSetupFeePayment($member, $membership, $activePaymentMethod);
            if ($setupFeePayment) {
                $stats['payments_created']++;
            }

            // Regular pending payment
            $pendingPayment = $this->paymentService->createPendingPayment($member, $membership, $activePaymentMethod);
            if ($pendingPayment) {
                $stats['payments_created']++;
            }
        }
    }

    /**
     * Create a payment method based on the selected type
     */
    private function createPaymentMethodForType(
        Member $member,
        string $type,
        ?string $iban,
        ?string $accountHolder,
        \App\Models\Gym $gym,
        bool $skipMandate = false
    ): ?PaymentMethod {
        if ($type === 'sepa_direct_debit') {
            return PaymentMethod::createSepaPaymentMethod(
                $member,
                true,
                'sepa_direct_debit',
                $iban,
                $accountHolder
            );
        }

        if ($type === 'mollie_directdebit') {
            $hasRealEmail = !str_ends_with($member->email, '@import.local');

            $paymentMethod = $member->paymentMethods()->create([
                'type' => 'mollie_directdebit',
                'status' => $skipMandate ? 'failed' : 'pending',
                'is_default' => true,
                'requires_mandate' => true,
                'iban' => $iban,
                'account_holder' => $accountHolder,
                'sepa_mandate_acknowledged' => $hasRealEmail,
                'sepa_mandate_status' => 'pending',
            ]);

            // Skip mandate creation if data is invalid (e.g. credit card number as account holder)
            if ($skipMandate) {
                $member->update(['status' => 'pending']);
                $member->memberships()->where('status', 'active')->update(['status' => 'pending']);
                return $paymentMethod;
            }

            if ($hasRealEmail) {
                $paymentMethod->update([
                    'sepa_mandate_reference' => $paymentMethod->generateSepaMandateReference(),
                ]);

                try {
                    app(MollieService::class)->handleMolliePaymentMethod($member, $paymentMethod);
                } catch (\Exception $e) {
                    Log::warning("Mollie-Mandat konnte für {$member->email} nicht erstellt werden: {$e->getMessage()}");
                }
            }

            return $paymentMethod;
        }

        // Simple payment methods (cash, banktransfer, invoice, etc.)
        return $member->paymentMethods()->create([
            'type' => $type,
            'status' => 'active',
            'is_default' => true,
            'iban' => $iban,
            'account_holder' => $accountHolder,
        ]);
    }

    /**
     * Split a full name into first_name and last_name
     */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name));

        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'last_name' => ''];
        }

        $lastName = array_pop($parts);
        return [
            'first_name' => implode(' ', $parts),
            'last_name' => $lastName,
        ];
    }

    /**
     * Build address string from CSV columns
     */
    private function buildAddress(array $row): ?string
    {
        $strasse = trim($row['adresse_strasse'] ?? '');
        $hausnummer = trim($row['adresse_hausnummer'] ?? '');

        if (empty($strasse)) {
            return null;
        }

        return $hausnummer ? "{$strasse} {$hausnummer}" : $strasse;
    }

    /**
     * Find a MembershipPlan by name (tarif) and/or price (monatsbeitrag).
     * Priority: name+price > name only > price only
     */
    private function findPlan($plans, string $tarifName, ?float $price): ?MembershipPlan
    {
        // Try matching by name + price together
        if ($tarifName && $price !== null) {
            $match = $plans->first(fn($p) => mb_strtolower($p->name) === mb_strtolower($tarifName) && abs((float) $p->price - $price) < 0.01);
            if ($match) {
                return $match;
            }
        }

        // Fallback: match by name only
        if ($tarifName) {
            $match = $plans->first(fn($p) => mb_strtolower($p->name) === mb_strtolower($tarifName));
            if ($match) {
                return $match;
            }
        }

        // Fallback: match by price only
        if ($price !== null) {
            return $plans->first(fn($p) => abs((float) $p->price - $price) < 0.01);
        }

        return null;
    }

    /**
     * Parse a price value from CSV
     */
    private function parseCsvPrice(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Handle comma as decimal separator
        $value = str_replace(',', '.', trim($value));

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * Parse a date value from CSV (various German formats)
     */
    private function parseCsvDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Already in Y-m-d format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        try {
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
                return Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
            }
            if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}/', $value)) {
                $value = preg_replace('/\s+\d+:\d+:\d+/', '', $value);
                return Carbon::createFromFormat('j-n-Y', $value)->format('Y-m-d');
            }
        } catch (\Exception) {
            // Fall through
        }

        return null;
    }

}
