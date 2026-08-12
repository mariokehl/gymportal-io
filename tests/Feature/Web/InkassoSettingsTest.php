<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InkassoSettingsTest extends TestCase
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
    private function ownerWithGym(array $settings = []): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        if ($settings !== []) {
            $gym->update(['inkasso_settings' => array_merge($gym->inkasso_settings, $settings)]);
        }

        return [$owner->fresh(), $gym->fresh()];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => '40218-BER',
            'client_number' => '40218',
            'username' => 'fitzone-berlin@api',
            'creditor_name' => 'FitZone Berlin GmbH',
            'contact' => 'Max Mustermann',
            'min_amount' => 10,
            'include_minors' => false,
            'residual_handling' => Gym::RESIDUAL_ALWAYS_WRITE_OFF,
            'auto_resubmit' => true,
            'handover_flat_fee' => 58.5,
            'default_interest_rate' => 5,
            'levels' => Gym::DEFAULT_DUNNING_LEVELS,
        ], $overrides);
    }

    public function test_settings_are_returned_without_the_password(): void
    {
        [$owner] = $this->ownerWithGym([
            'active' => true,
            'password' => Crypt::encryptString('geheimespasswort'),
        ]);

        $response = $this->actingAs($owner)->getJson(route('settings.inkasso.index'));

        $response->assertOk()
            ->assertJsonPath('settings.has_password', true)
            ->assertJsonMissingPath('settings.password');

        $this->assertStringNotContainsString('geheimespasswort', $response->getContent());
    }

    public function test_the_settings_page_never_exposes_the_encrypted_password(): void
    {
        [$owner, $gym] = $this->ownerWithGym([
            'active' => true,
            'password' => Crypt::encryptString('geheimespasswort'),
        ]);

        $encrypted = $gym->fresh()->inkasso_settings['password'];

        $content = $this->actingAs($owner)->get(route('settings.index'))->assertOk()->getContent();

        // Neither the plaintext nor the ciphertext may reach the browser.
        $this->assertStringNotContainsString('geheimespasswort', $content);
        $this->assertStringNotContainsString($encrypted, $content);
    }

    public function test_saving_keeps_the_stored_password_when_none_is_supplied(): void
    {
        [$owner, $gym] = $this->ownerWithGym([
            'active' => true,
            'password' => Crypt::encryptString('geheimespasswort'),
        ]);

        $this->actingAs($owner)
            ->putJson(route('settings.inkasso.update'), $this->validPayload())
            ->assertOk();

        $this->assertSame('geheimespasswort', $gym->fresh()->getInkassoPassword());
    }

    public function test_a_new_password_replaces_the_stored_one_encrypted(): void
    {
        [$owner, $gym] = $this->ownerWithGym([
            'active' => true,
            'password' => Crypt::encryptString('altes-passwort'),
        ]);

        $this->actingAs($owner)
            ->putJson(route('settings.inkasso.update'), $this->validPayload(['password' => 'neues-passwort']))
            ->assertOk();

        $fresh = $gym->fresh();
        $this->assertSame('neues-passwort', $fresh->getInkassoPassword());
        // The value is stored encrypted, never in plaintext.
        $this->assertNotSame('neues-passwort', $fresh->inkasso_settings['password']);
    }

    public function test_the_creditor_number_must_have_five_characters(): void
    {
        [$owner] = $this->ownerWithGym(['active' => true]);

        $this->actingAs($owner)
            ->putJson(route('settings.inkasso.update'), $this->validPayload(['client_number' => '123']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('client_number');
    }

    public function test_activation_stores_the_credentials_and_enables_the_partner(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->postJson(route('settings.inkasso.activate'), [
                'tenant_id' => '40218-BER',
                'client_number' => '40218',
                'username' => 'fitzone-berlin@api',
                'password' => 'geheimespasswort',
            ])
            ->assertOk()
            ->assertJsonPath('settings.active', true)
            ->assertJsonMissingPath('settings.password');

        $fresh = $gym->fresh();
        $this->assertTrue($fresh->isInkassoEnabled());
        $this->assertSame('geheimespasswort', $fresh->getInkassoPassword());
    }

    public function test_deactivation_disables_the_partner(): void
    {
        [$owner, $gym] = $this->ownerWithGym(['active' => true]);

        $this->actingAs($owner)
            ->postJson(route('settings.inkasso.deactivate'))
            ->assertOk()
            ->assertJsonPath('settings.active', false);

        $this->assertFalse($gym->fresh()->isInkassoEnabled());
    }

    public function test_the_connection_test_reports_the_partner_result(): void
    {
        Http::fake(['*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200)]);

        [$owner] = $this->ownerWithGym(['active' => true]);

        $this->actingAs($owner)
            ->postJson(route('settings.inkasso.test-connection'), [
                'tenant_id' => '40218-BER',
                'username' => 'fitzone-berlin@api',
                'password' => 'geheimespasswort',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_a_staff_member_may_not_change_the_settings(): void
    {
        [, $gym] = $this->ownerWithGym(['active' => true]);

        $staffRoleId = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff'])->id;
        $staff = User::factory()->create(['role_id' => $staffRoleId]);
        $staff->update(['current_gym_id' => $gym->id]);
        $gym->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff->fresh())
            ->putJson(route('settings.inkasso.update'), $this->validPayload())
            ->assertForbidden();
    }
}
