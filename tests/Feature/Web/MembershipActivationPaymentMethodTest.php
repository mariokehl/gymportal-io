<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipActivationPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Member, 2: Membership}
     */
    private function pendingMembershipScenario(bool $freeTrial = false): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'pending']);

        $planFactory = MembershipPlan::factory();
        $plan = ($freeTrial ? $planFactory->freeTrial() : $planFactory)->create(['gym_id' => $gym->id]);

        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'pending',
        ]);

        return [$owner->fresh(), $member, $membership];
    }

    private function activate(User $user, Member $member, Membership $membership)
    {
        return $this->actingAs($user)
            ->put(route('members.memberships.activate', [
                'member' => $member,
                'membership' => $membership,
            ]));
    }

    private function createPaymentMethod(Member $member, array $attributes = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'member_id' => $member->id,
            'type' => 'cash',
            'status' => 'active',
            'is_default' => true,
            'requires_mandate' => false,
        ], $attributes));
    }

    #[Test]
    public function it_blocks_activation_when_no_payment_method_exists(): void
    {
        [$owner, $member, $membership] = $this->pendingMembershipScenario();

        $response = $this->activate($owner, $member, $membership);

        $response->assertSessionHasErrors('payment_method');
        $this->assertStringContainsString(
            'Legen Sie mindestens eine Zahlungsart',
            session('errors')->first('payment_method')
        );
        $this->assertSame('pending', $membership->fresh()->status);
    }

    #[Test]
    public function it_blocks_activation_when_the_sepa_mandate_is_not_signed_yet(): void
    {
        [$owner, $member, $membership] = $this->pendingMembershipScenario();

        $this->createPaymentMethod($member, [
            'type' => 'sepa_direct_debit',
            // 'expired' rather than 'pending': the SQLite test schema keeps the
            // original status CHECK constraint, and either value is equally
            // "not usable" for the activation guard.
            'status' => 'expired',
            'requires_mandate' => true,
            'sepa_mandate_status' => 'pending',
        ]);

        $response = $this->activate($owner, $member, $membership);

        $response->assertSessionHasErrors('payment_method');
        $this->assertStringContainsString(
            'als unterschrieben markieren',
            session('errors')->first('payment_method')
        );
        $this->assertSame('pending', $membership->fresh()->status);
    }

    #[Test]
    public function it_blocks_activation_when_the_signed_sepa_mandate_is_not_activated_yet(): void
    {
        [$owner, $member, $membership] = $this->pendingMembershipScenario();

        $this->createPaymentMethod($member, [
            'type' => 'sepa_direct_debit',
            // 'expired' rather than 'pending': the SQLite test schema keeps the
            // original status CHECK constraint, and either value is equally
            // "not usable" for the activation guard.
            'status' => 'expired',
            'requires_mandate' => true,
            'sepa_mandate_status' => 'signed',
        ]);

        $response = $this->activate($owner, $member, $membership);

        $response->assertSessionHasErrors('payment_method');
        $this->assertStringContainsString(
            'Lastschriftmandat muss noch aktiviert werden',
            session('errors')->first('payment_method')
        );
        $this->assertSame('pending', $membership->fresh()->status);
    }

    #[Test]
    public function it_blocks_activation_when_the_only_payment_method_is_inactive(): void
    {
        [$owner, $member, $membership] = $this->pendingMembershipScenario();

        $this->createPaymentMethod($member, ['status' => 'expired']);

        $response = $this->activate($owner, $member, $membership);

        $response->assertSessionHasErrors('payment_method');
        $this->assertStringContainsString(
            'keine aktive Zahlungsart',
            session('errors')->first('payment_method')
        );
        $this->assertSame('pending', $membership->fresh()->status);
    }

    #[Test]
    public function it_activates_a_paid_membership_with_an_active_payment_method(): void
    {
        [$owner, $member, $membership] = $this->pendingMembershipScenario();

        $this->createPaymentMethod($member);

        $response = $this->activate($owner, $member, $membership);

        $response->assertSessionHasNoErrors();
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('active', $member->fresh()->status);
    }

    #[Test]
    public function it_activates_a_paid_membership_with_an_active_sepa_mandate(): void
    {
        [$owner, $member, $membership] = $this->pendingMembershipScenario();

        $this->createPaymentMethod($member, [
            'type' => 'sepa_direct_debit',
            'status' => 'active',
            'requires_mandate' => true,
            'sepa_mandate_status' => 'active',
        ]);

        $response = $this->activate($owner, $member, $membership);

        $response->assertSessionHasNoErrors();
        $this->assertSame('active', $membership->fresh()->status);
    }

    #[Test]
    public function it_activates_when_a_second_payment_method_is_usable(): void
    {
        [$owner, $member, $membership] = $this->pendingMembershipScenario();

        $this->createPaymentMethod($member, [
            'type' => 'sepa_direct_debit',
            // 'expired' rather than 'pending': the SQLite test schema keeps the
            // original status CHECK constraint, and either value is equally
            // "not usable" for the activation guard.
            'status' => 'expired',
            'requires_mandate' => true,
            'sepa_mandate_status' => 'pending',
        ]);
        $this->createPaymentMethod($member, ['is_default' => false]);

        $response = $this->activate($owner, $member, $membership);

        $response->assertSessionHasNoErrors();
        $this->assertSame('active', $membership->fresh()->status);
    }

    #[Test]
    public function it_activates_a_free_trial_membership_without_any_payment_method(): void
    {
        [$owner, $member, $membership] = $this->pendingMembershipScenario(freeTrial: true);

        $response = $this->activate($owner, $member, $membership);

        $response->assertSessionHasNoErrors();
        $this->assertSame('active', $membership->fresh()->status);
    }
}
