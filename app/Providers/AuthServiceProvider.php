<?php

namespace App\Providers;

use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Policies\GymPolicy;
use App\Policies\GymUserPolicy;
use App\Policies\MemberPolicy;
use App\Policies\MembershipPlanPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\NotificationRecipientPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Gym::class => GymPolicy::class,
        GymUser::class => GymUserPolicy::class,
        Member::class => MemberPolicy::class,
        MembershipPlan::class => MembershipPlanPolicy::class,
        Membership::class => MembershipPolicy::class,
        Notification::class => NotificationPolicy::class,
        NotificationRecipient::class => NotificationRecipientPolicy::class,
        PaymentMethod::class => PaymentMethodPolicy::class,
        Payment::class => PaymentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
