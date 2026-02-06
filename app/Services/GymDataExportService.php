<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Course;
use App\Models\EmailTemplate;
use App\Models\GymLegalUrl;
use App\Models\GymScanner;
use App\Models\CheckIn;
use App\Models\GymUser;
use Illuminate\Support\Collection;

class GymDataExportService
{
    /**
     * Export all gym data as an array
     */
    public function exportGymData(int $gymId): array
    {
        $gym = Gym::findOrFail($gymId);

        return [
            'export_metadata' => $this->getExportMetadata($gym),
            'gym_settings' => $this->exportGymSettings($gym),
            'membership_plans' => $this->exportMembershipPlans($gymId),
            'members' => $this->exportMembers($gymId),
            'courses' => $this->exportCourses($gymId),
            'email_templates' => $this->exportEmailTemplates($gymId),
            'legal_urls' => $this->exportLegalUrls($gymId),
            'scanners' => $this->exportScanners($gymId),
            'gym_users' => $this->exportGymUsers($gymId),
        ];
    }

    /**
     * Get export statistics for preview
     */
    public function getExportStats(int $gymId): array
    {
        return [
            'members_count' => Member::where('gym_id', $gymId)->count(),
            'membership_plans_count' => MembershipPlan::where('gym_id', $gymId)->count(),
            'memberships_count' => \App\Models\Membership::whereHas('member', fn($q) => $q->where('gym_id', $gymId))->count(),
            'payments_count' => \App\Models\Payment::where('gym_id', $gymId)->count(),
            'courses_count' => Course::where('gym_id', $gymId)->count(),
            'check_ins_count' => CheckIn::where('gym_id', $gymId)->count(),
            'email_templates_count' => EmailTemplate::where('gym_id', $gymId)->count(),
            'scanners_count' => GymScanner::where('gym_id', $gymId)->count(),
        ];
    }

    /**
     * Generate export metadata
     */
    private function getExportMetadata(Gym $gym): array
    {
        return [
            'version' => '1.0',
            'exported_at' => now()->toISOString(),
            'gym_name' => $gym->name,
            'gym_slug' => $gym->slug,
            'application' => 'gymportal.io',
        ];
    }

    /**
     * Export gym settings (excluding sensitive data)
     */
    private function exportGymSettings(Gym $gym): array
    {
        return [
            'name' => $gym->name,
            'display_name' => $gym->display_name,
            'description' => $gym->description,
            'address' => $gym->address,
            'city' => $gym->city,
            'postal_code' => $gym->postal_code,
            'country' => $gym->country,
            'latitude' => $gym->latitude,
            'longitude' => $gym->longitude,
            'phone' => $gym->phone,
            'email' => $gym->email,
            'website' => $gym->website,
            'account_holder' => $gym->account_holder,
            'iban' => $gym->iban,
            'bic' => $gym->bic,
            'creditor_identifier' => $gym->creditor_identifier,
            'primary_color' => $gym->primary_color,
            'secondary_color' => $gym->secondary_color,
            'accent_color' => $gym->accent_color,
            'background_color' => $gym->background_color,
            'text_color' => $gym->text_color,
            'pwa_enabled' => $gym->pwa_enabled,
            'pwa_settings' => $gym->pwa_settings,
            'widget_enabled' => $gym->widget_enabled,
            'widget_settings' => $gym->widget_settings,
            'opening_hours' => $gym->opening_hours,
            'social_media' => $gym->social_media,
            'member_app_description' => $gym->member_app_description,
            'payment_methods_config' => $gym->payment_methods_config,
            // Sensitive data excluded: mollie_config, api_key, scanner_secret_key
        ];
    }

    /**
     * Export membership plans
     */
    private function exportMembershipPlans(int $gymId): array
    {
        return MembershipPlan::where('gym_id', $gymId)
            ->withTrashed()
            ->get()
            ->map(fn($plan) => [
                'export_id' => 'plan_' . $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price,
                'setup_fee' => $plan->setup_fee,
                'trial_period_days' => $plan->trial_period_days,
                'trial_price' => $plan->trial_price,
                'billing_cycle' => $plan->billing_cycle,
                'is_active' => $plan->is_active,
                'commitment_months' => $plan->commitment_months,
                'cancellation_period' => $plan->cancellation_period,
                'cancellation_period_unit' => $plan->cancellation_period_unit,
                'features' => $plan->features,
                'widget_display_options' => $plan->widget_display_options,
                'sort_order' => $plan->sort_order,
                'highlight' => $plan->highlight,
                'badge_text' => $plan->badge_text,
                'deleted_at' => $plan->deleted_at?->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Export members with their memberships, payments, check-ins, and course bookings
     */
    private function exportMembers(int $gymId): array
    {
        return Member::where('gym_id', $gymId)
            ->withTrashed()
            ->with([
                'memberships' => fn($q) => $q->withTrashed()->with(['payments', 'membershipPlan']),
                'checkIns',
                'courseBookings.courseSchedule',
            ])
            ->get()
            ->map(fn($member) => $this->transformMember($member))
            ->toArray();
    }

    /**
     * Transform a single member for export
     */
    private function transformMember(Member $member): array
    {
        return [
            'export_id' => 'member_' . $member->id,
            'member_number' => $member->member_number,
            'salutation' => $member->salutation,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'phone' => $member->phone,
            'birth_date' => $member->birth_date?->format('Y-m-d'),
            'address' => $member->address,
            'address_addition' => $member->address_addition,
            'city' => $member->city,
            'postal_code' => $member->postal_code,
            'country' => $member->country,
            'status' => $member->status,
            'joined_date' => $member->joined_date?->format('Y-m-d'),
            'notes' => $member->notes,
            'emergency_contact_name' => $member->emergency_contact_name,
            'emergency_contact_phone' => $member->emergency_contact_phone,
            'registration_source' => $member->registration_source,
            'fitness_goals' => $member->fitness_goals,
            'voucher_code' => $member->voucher_code,
            'deleted_at' => $member->deleted_at?->toISOString(),
            'memberships' => $member->memberships->map(fn($membership) => $this->transformMembership($membership))->toArray(),
            'check_ins' => $member->checkIns->map(fn($checkIn) => $this->transformCheckIn($checkIn))->toArray(),
            'course_bookings' => $member->courseBookings->map(fn($booking) => $this->transformCourseBooking($booking))->toArray(),
        ];
    }

    /**
     * Transform a membership for export
     */
    private function transformMembership(\App\Models\Membership $membership): array
    {
        return [
            'export_id' => 'membership_' . $membership->id,
            'membership_plan_export_id' => 'plan_' . $membership->membership_plan_id,
            'start_date' => $membership->start_date?->format('Y-m-d'),
            'end_date' => $membership->end_date?->format('Y-m-d'),
            'status' => $membership->status,
            'pause_start_date' => $membership->pause_start_date?->format('Y-m-d'),
            'pause_end_date' => $membership->pause_end_date?->format('Y-m-d'),
            'cancellation_date' => $membership->cancellation_date?->format('Y-m-d'),
            'cancellation_reason' => $membership->cancellation_reason,
            'notes' => $membership->notes,
            'deleted_at' => $membership->deleted_at?->toISOString(),
            'payments' => $membership->payments->map(fn($payment) => $this->transformPayment($payment))->toArray(),
        ];
    }

    /**
     * Transform a payment for export (excluding sensitive gateway data)
     */
    private function transformPayment(\App\Models\Payment $payment): array
    {
        return [
            'export_id' => 'payment_' . $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'description' => $payment->description,
            'status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'due_date' => $payment->due_date?->format('Y-m-d'),
            'paid_date' => $payment->paid_date?->format('Y-m-d'),
            'execution_date' => $payment->execution_date?->format('Y-m-d'),
            'notes' => $payment->notes,
            // Sensitive data excluded: mollie_payment_id, checkout_url, transaction_id
        ];
    }

    /**
     * Transform a check-in for export
     */
    private function transformCheckIn(CheckIn $checkIn): array
    {
        return [
            'check_in_time' => $checkIn->check_in_time?->toISOString(),
            'check_out_time' => $checkIn->check_out_time?->toISOString(),
            'check_in_method' => $checkIn->check_in_method,
        ];
    }

    /**
     * Transform a course booking for export
     */
    private function transformCourseBooking(\App\Models\CourseBooking $booking): array
    {
        return [
            'course_schedule_export_id' => $booking->course_schedule_id ? 'schedule_' . $booking->course_schedule_id : null,
            'status' => $booking->status ?? 'booked',
            'booked_at' => $booking->created_at?->toISOString(),
        ];
    }

    /**
     * Export courses with schedules
     */
    private function exportCourses(int $gymId): array
    {
        return Course::where('gym_id', $gymId)
            ->withTrashed()
            ->with(['schedules.bookings'])
            ->get()
            ->map(fn($course) => [
                'export_id' => 'course_' . $course->id,
                'name' => $course->name,
                'description' => $course->description,
                'capacity' => $course->capacity,
                'duration_minutes' => $course->duration_minutes,
                'requires_booking' => $course->requires_booking,
                'color' => $course->color,
                'deleted_at' => $course->deleted_at?->toISOString(),
                'schedules' => $course->schedules->map(fn($schedule) => [
                    'export_id' => 'schedule_' . $schedule->id,
                    'date' => $schedule->date?->format('Y-m-d'),
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'room' => $schedule->room,
                    'is_cancelled' => $schedule->is_cancelled,
                    'cancellation_reason' => $schedule->cancellation_reason,
                ])->toArray(),
            ])
            ->toArray();
    }

    /**
     * Export email templates
     */
    private function exportEmailTemplates(int $gymId): array
    {
        return EmailTemplate::where('gym_id', $gymId)
            ->get()
            ->map(fn($template) => [
                'export_id' => 'template_' . $template->id,
                'name' => $template->name,
                'type' => $template->type,
                'subject' => $template->subject,
                'body' => $template->body,
                'is_active' => $template->is_active,
                'is_default' => $template->is_default,
                'variables' => $template->variables,
            ])
            ->toArray();
    }

    /**
     * Export legal URLs
     */
    private function exportLegalUrls(int $gymId): array
    {
        return GymLegalUrl::where('gym_id', $gymId)
            ->get()
            ->map(fn($url) => [
                'type' => $url->type,
                'url' => $url->url,
            ])
            ->toArray();
    }

    /**
     * Export scanners (excluding sensitive tokens)
     */
    private function exportScanners(int $gymId): array
    {
        return GymScanner::where('gym_id', $gymId)
            ->get()
            ->map(fn($scanner) => [
                'export_id' => 'scanner_' . $scanner->id,
                'device_number' => $scanner->device_number,
                'device_name' => $scanner->device_name,
                'is_active' => $scanner->is_active,
                'allowed_ips' => $scanner->allowed_ips,
                // Sensitive data excluded: api_token
            ])
            ->toArray();
    }

    /**
     * Export gym user relationships (just email and role, not passwords)
     */
    private function exportGymUsers(int $gymId): array
    {
        $gym = Gym::find($gymId);
        if (!$gym) {
            return [];
        }

        return $gym->users()
            ->get()
            ->map(fn($user) => [
                'user_email' => $user->email,
                'role' => $user->pivot->role,
            ])
            ->toArray();
    }
}
