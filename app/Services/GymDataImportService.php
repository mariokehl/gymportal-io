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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GymDataImportService
{
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
                $memberData['member_number'] = $this->generateMemberNumber($gymId);
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
     * Generate a unique member number
     */
    private function generateMemberNumber(int $gymId): string
    {
        $prefix = 'M' . str_pad($gymId, 3, '0', STR_PAD_LEFT);
        $lastMember = Member::where('gym_id', $gymId)
            ->where('member_number', 'like', $prefix . '%')
            ->orderBy('member_number', 'desc')
            ->first();

        if ($lastMember) {
            $lastNumber = (int) substr($lastMember->member_number, strlen($prefix));
            return $prefix . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        }

        return $prefix . '00001';
    }
}
