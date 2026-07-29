<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentExecutionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym}
     */
    private function ownerWithGym(array $enabledMethods = ['sepa_direct_debit']): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        foreach ($enabledMethods as $method) {
            $gym->updateStandardPaymentMethod($method, true);
        }

        return [$owner->fresh(), $gym->fresh()];
    }

    #[Test]
    public function the_execution_settings_of_the_current_gym_can_be_read(): void
    {
        [$owner] = $this->ownerWithGym();

        $response = $this->actingAs($owner)
            ->getJson(route('settings.payment-methods.execution-settings.index'))
            ->assertOk();

        $methods = $response->json('methods');

        $this->assertNotEmpty($methods);
        $this->assertSame('sepa_direct_debit', $methods[0]['key']);
        $this->assertFalse($methods[0]['is_custom']);
        $this->assertSame(3, $methods[0]['initial']);
        $this->assertSame(-2, $methods[0]['recurring']);
        $this->assertSame(PaymentMethod::MIN_EXECUTION_OFFSET, $response->json('limits.min'));
        $this->assertSame(PaymentMethod::MAX_EXECUTION_OFFSET, $response->json('limits.max'));
    }

    #[Test]
    public function an_offset_can_be_stored_for_an_enabled_payment_method(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->putJson(route('settings.payment-methods.execution-settings.update'), [
                'method' => 'sepa_direct_debit',
                'initial' => 6,
                'recurring' => -4,
            ])
            ->assertOk();

        $offsets = $gym->fresh()->getPaymentExecutionOffsets('sepa_direct_debit');

        $this->assertSame(6, $offsets['initial']);
        $this->assertSame(-4, $offsets['recurring']);
        $this->assertTrue($gym->fresh()->hasCustomPaymentExecutionOffsets('sepa_direct_debit'));
    }

    #[Test]
    public function a_null_offset_resets_the_payment_method_to_the_system_default(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $gym->setPaymentExecutionOffsets('sepa_direct_debit', 6, -4);

        $this->actingAs($owner)
            ->putJson(route('settings.payment-methods.execution-settings.update'), [
                'method' => 'sepa_direct_debit',
                'initial' => null,
                'recurring' => null,
            ])
            ->assertOk();

        $gym = $gym->fresh();

        $this->assertFalse($gym->hasCustomPaymentExecutionOffsets('sepa_direct_debit'));
        $this->assertSame(3, $gym->getPaymentExecutionOffsets('sepa_direct_debit')['initial']);
    }

    #[Test]
    public function all_offsets_can_be_reset_at_once(): void
    {
        [$owner, $gym] = $this->ownerWithGym(['sepa_direct_debit', 'cash']);
        $gym->setPaymentExecutionOffsets('sepa_direct_debit', 6, -4);
        $gym->setPaymentExecutionOffsets('cash', 2, -2);

        $this->actingAs($owner)
            ->deleteJson(route('settings.payment-methods.execution-settings.reset'))
            ->assertOk();

        $gym = $gym->fresh();

        $this->assertFalse($gym->hasCustomPaymentExecutionOffsets('sepa_direct_debit'));
        $this->assertFalse($gym->hasCustomPaymentExecutionOffsets('cash'));
    }

    #[Test]
    public function an_offset_outside_the_supported_range_is_rejected(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->putJson(route('settings.payment-methods.execution-settings.update'), [
                'method' => 'sepa_direct_debit',
                'initial' => 500,
                'recurring' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('initial');

        $this->assertFalse($gym->fresh()->hasCustomPaymentExecutionOffsets('sepa_direct_debit'));
    }

    #[Test]
    public function a_disabled_payment_method_cannot_be_configured(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        // "invoice" is not enabled for this gym.
        $this->actingAs($owner)
            ->putJson(route('settings.payment-methods.execution-settings.update'), [
                'method' => 'invoice',
                'initial' => 5,
                'recurring' => 0,
            ])
            ->assertStatus(400);

        $this->assertFalse($gym->fresh()->hasCustomPaymentExecutionOffsets('invoice'));
    }

    #[Test]
    public function an_unknown_payment_method_cannot_be_configured(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->putJson(route('settings.payment-methods.execution-settings.update'), [
                'method' => 'not_a_real_method',
                'initial' => 5,
                'recurring' => 0,
            ])
            ->assertStatus(400);

        $this->assertFalse($gym->fresh()->hasCustomPaymentExecutionOffsets('not_a_real_method'));
    }

    #[Test]
    public function guests_cannot_read_or_change_the_execution_settings(): void
    {
        $this->getJson(route('settings.payment-methods.execution-settings.index'))
            ->assertUnauthorized();

        $this->putJson(route('settings.payment-methods.execution-settings.update'), [
            'method' => 'sepa_direct_debit',
            'initial' => 5,
            'recurring' => 0,
        ])->assertUnauthorized();
    }

    #[Test]
    public function disabled_payment_methods_are_listed_as_inactive(): void
    {
        [$owner] = $this->ownerWithGym();

        $inactive = $this->actingAs($owner)
            ->getJson(route('settings.payment-methods.execution-settings.index'))
            ->assertOk()
            ->json('inactive_methods');

        $keys = array_column($inactive, 'key');

        $this->assertContains('invoice', $keys);
        $this->assertNotContains('sepa_direct_debit', $keys);
    }
}
