<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessControlDeviceTaskTest extends TestCase
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
    private function ownerWithGym(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    private function usageAddon(Gym $gym, array $attributes = []): Addon
    {
        return Addon::factory()->create(array_merge([
            'gym_id' => $gym->id,
            'service_type' => Addon::SERVICE_TYPE_USAGE,
            'is_active' => true,
        ], $attributes));
    }

    #[Test]
    public function it_creates_a_device_with_a_task(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), [
                'device_name' => 'Haupteingang',
                'device_task' => GymScanner::TASK_CHECKIN_CHECKOUT,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('gym_scanners', [
            'gym_id' => $gym->id,
            'device_name' => 'Haupteingang',
            'device_task' => GymScanner::TASK_CHECKIN_CHECKOUT,
            'addon_id' => null,
            'enforce_quota' => true,
        ]);
    }

    #[Test]
    public function a_device_created_without_a_task_defaults_to_check_in(): void
    {
        [, $gym] = $this->ownerWithGym();

        $scanner = GymScanner::create([
            'gym_id' => $gym->id,
            'device_name' => 'Haupteingang',
        ]);

        $this->assertSame(GymScanner::TASK_CHECKIN, $scanner->fresh()->device_task);
        $this->assertSame(GymScanner::TASK_CHECKIN, GymScanner::DEFAULT_DEVICE_TASK);
    }

    #[Test]
    public function changing_the_default_leaves_existing_devices_untouched(): void
    {
        [, $gym] = $this->ownerWithGym();

        $combined = GymScanner::create([
            'gym_id' => $gym->id,
            'device_name' => 'Drehkreuz',
            'device_task' => GymScanner::TASK_CHECKIN_CHECKOUT,
        ]);

        $this->assertSame(GymScanner::TASK_CHECKIN_CHECKOUT, $combined->fresh()->device_task);
    }

    #[Test]
    public function it_creates_a_dispenser_linked_to_a_usage_addon(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $addon = $this->usageAddon($gym, ['name' => 'Getränke-Flatrate']);

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), [
                'device_name' => 'Getränkeautomat Theke',
                'device_task' => GymScanner::TASK_DISPENSER,
                'addon_id' => $addon->id,
                'enforce_quota' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('gym_scanners', [
            'gym_id' => $gym->id,
            'device_task' => GymScanner::TASK_DISPENSER,
            'addon_id' => $addon->id,
            'enforce_quota' => false,
        ]);
    }

    #[Test]
    public function it_requires_an_addon_for_a_dispenser(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), [
                'device_name' => 'Getränkeautomat Theke',
                'device_task' => GymScanner::TASK_DISPENSER,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('addon_id');
    }

    #[Test]
    public function it_requires_an_addon_for_area_control(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), [
                'device_name' => 'Sauna',
                'device_task' => GymScanner::TASK_AREA_CONTROL,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('addon_id');
    }

    #[Test]
    public function it_rejects_an_unknown_device_task(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), [
                'device_name' => 'Haupteingang',
                'device_task' => 'teleporter',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('device_task');
    }

    #[Test]
    public function it_rejects_an_addon_from_another_gym(): void
    {
        [$owner] = $this->ownerWithGym();
        $otherGym = Gym::factory()->create();
        $foreignAddon = $this->usageAddon($otherGym);

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), [
                'device_name' => 'Getränkeautomat Theke',
                'device_task' => GymScanner::TASK_DISPENSER,
                'addon_id' => $foreignAddon->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('addon_id');
    }

    #[Test]
    public function it_rejects_a_non_usage_addon(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $additionalAddon = Addon::factory()->create([
            'gym_id' => $gym->id,
            'service_type' => Addon::SERVICE_TYPE_ADDITIONAL,
        ]);

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), [
                'device_name' => 'Getränkeautomat Theke',
                'device_task' => GymScanner::TASK_DISPENSER,
                'addon_id' => $additionalAddon->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('addon_id');
    }

    #[Test]
    public function it_clears_the_addon_link_when_the_task_no_longer_needs_one(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $addon = $this->usageAddon($gym);
        $scanner = GymScanner::create([
            'gym_id' => $gym->id,
            'device_name' => 'Getränkeautomat Theke',
            'device_task' => GymScanner::TASK_DISPENSER,
            'addon_id' => $addon->id,
            'enforce_quota' => false,
        ]);

        $this->actingAs($owner)
            ->putJson(route('access-control.scanners.update', $scanner), [
                'device_name' => 'Haupteingang',
                'device_task' => GymScanner::TASK_CHECKIN,
                'addon_id' => $addon->id,
                'enforce_quota' => false,
                'is_active' => true,
            ])
            ->assertOk();

        $scanner->refresh();

        $this->assertSame(GymScanner::TASK_CHECKIN, $scanner->device_task);
        $this->assertNull($scanner->addon_id);
        $this->assertTrue($scanner->enforce_quota);
    }

    #[Test]
    public function the_index_only_offers_active_usage_addons(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $usage = $this->usageAddon($gym, ['name' => 'Getränke-Flatrate']);
        $this->usageAddon($gym, ['name' => 'Inaktiv', 'is_active' => false]);
        Addon::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Trainereinweisung',
            'service_type' => Addon::SERVICE_TYPE_ADDITIONAL,
        ]);

        $this->actingAs($owner)
            ->get(route('access-control.index'))
            ->assertInertia(fn ($page) => $page
                ->has('usageAddons', 1)
                ->where('usageAddons.0.id', $usage->id)
                ->where('usageAddons.0.name', 'Getränke-Flatrate')
            );
    }

    #[Test]
    public function the_downloaded_config_contains_the_device_task(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $addon = $this->usageAddon($gym);
        $scanner = GymScanner::create([
            'gym_id' => $gym->id,
            'device_name' => 'Getränkeautomat Theke',
            'device_task' => GymScanner::TASK_DISPENSER,
            'addon_id' => $addon->id,
            'enforce_quota' => false,
        ]);

        // A config needs the plaintext token, which only devices from before
        // the hash migration still carry.
        $scanner->forceFill(['api_token' => 'scan_legacy_token_value'])->save();

        $response = $this->actingAs($owner)
            ->get(route('access-control.scanners.download-config', $scanner))
            ->assertOk();

        $config = $response->streamedContent();

        $this->assertStringContainsString('DEVICE_TASK="'.GymScanner::TASK_DISPENSER.'"', $config);
        $this->assertStringContainsString('ADDON_ID="'.$addon->id.'"', $config);
        $this->assertStringContainsString('ENFORCE_QUOTA=False', $config);
        $this->assertStringContainsString('SAAS_API_KEY="scan_legacy_token_value"', $config);
    }

    #[Test]
    public function a_hash_only_device_cannot_hand_out_a_config(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $scanner = GymScanner::create([
            'gym_id' => $gym->id,
            'device_name' => 'Eingang',
            'device_task' => GymScanner::TASK_CHECKIN,
        ]);

        // Nothing to put in the file, so say so rather than shipping a config
        // with an empty API key.
        $this->actingAs($owner)
            ->getJson(route('access-control.scanners.download-config', $scanner))
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }
}
