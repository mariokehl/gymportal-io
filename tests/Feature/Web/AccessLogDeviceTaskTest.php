<?php

namespace Tests\Feature\Web;

use App\Events\ScannerAccessEvent;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Role;
use App\Models\ScannerAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessLogDeviceTaskTest extends TestCase
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

    private function device(Gym $gym, string $task, string $number, string $name): GymScanner
    {
        return GymScanner::create([
            'gym_id' => $gym->id,
            'device_number' => $number,
            'device_name' => $name,
            'device_task' => $task,
        ]);
    }

    private function log(Gym $gym, string $deviceNumber): ScannerAccessLog
    {
        return ScannerAccessLog::create([
            'gym_id' => $gym->id,
            'device_number' => $deviceNumber,
            // Scanned identifier, not a foreign key — the column is a string.
            'member_id' => '1',
            'scan_type' => ScannerAccessLog::SCAN_TYPE_QR,
            'access_granted' => true,
        ]);
    }

    #[Test]
    public function the_log_list_exposes_the_device_task(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $this->device($gym, GymScanner::TASK_DISPENSER, '002', 'Getränkeautomat Theke');
        $this->log($gym, '002');

        $this->actingAs($owner)
            ->getJson(route('access-control.logs'))
            ->assertOk()
            ->assertJsonPath('data.0.device_task', GymScanner::TASK_DISPENSER)
            ->assertJsonPath('data.0.scanner_name', 'Getränkeautomat Theke');
    }

    #[Test]
    public function a_log_without_a_matching_device_has_a_null_task(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        // Log of a device that was deleted in the meantime.
        $this->log($gym, '099');

        $this->actingAs($owner)
            ->getJson(route('access-control.logs'))
            ->assertOk()
            ->assertJsonPath('data.0.device_task', null);
    }

    #[Test]
    public function a_log_without_a_device_has_no_scanner_name(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        // Member scanned the printed station with their own phone.
        ScannerAccessLog::create([
            'gym_id' => $gym->id,
            'device_number' => null,
            'member_id' => '1',
            'scan_type' => ScannerAccessLog::SCAN_TYPE_MANUAL,
            'access_granted' => true,
        ]);

        $this->actingAs($owner)
            ->getJson(route('access-control.logs'))
            ->assertOk()
            ->assertJsonPath('data.0.scanner_name', null)
            ->assertJsonPath('data.0.scan_type_label', 'Manuell (Aufsteller)');
    }

    #[Test]
    public function a_log_of_a_deleted_device_falls_back_to_its_number(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $this->log($gym, '099');

        $this->actingAs($owner)
            ->getJson(route('access-control.logs'))
            ->assertOk()
            ->assertJsonPath('data.0.scanner_name', 'Scanner #099');
    }

    #[Test]
    public function logs_can_be_filtered_by_device_task(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $this->device($gym, GymScanner::TASK_CHECKIN_CHECKOUT, '001', 'Haupteingang');
        $this->device($gym, GymScanner::TASK_DISPENSER, '002', 'Getränkeautomat Theke');
        $this->log($gym, '001');
        $this->log($gym, '002');

        $response = $this->actingAs($owner)
            ->getJson(route('access-control.logs', ['task' => GymScanner::TASK_DISPENSER]))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('002', $response->json('data.0.device_number'));
    }

    #[Test]
    public function an_unknown_task_filter_is_ignored(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $this->device($gym, GymScanner::TASK_CHECKIN_CHECKOUT, '001', 'Haupteingang');
        $this->log($gym, '001');

        $response = $this->actingAs($owner)
            ->getJson(route('access-control.logs', ['task' => 'teleporter']))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function the_task_filter_does_not_leak_across_gyms(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $otherGym = Gym::factory()->create();

        // Same device number in another gym, but a different task.
        $this->device($gym, GymScanner::TASK_CHECKIN_CHECKOUT, '001', 'Haupteingang');
        $this->device($otherGym, GymScanner::TASK_DISPENSER, '001', 'Fremde Getränkeanlage');
        $this->log($gym, '001');

        $response = $this->actingAs($owner)
            ->getJson(route('access-control.logs', ['task' => GymScanner::TASK_DISPENSER]))
            ->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    #[Test]
    public function the_scanner_relation_survives_eager_loading(): void
    {
        [, $gym] = $this->ownerWithGym();
        $this->device($gym, GymScanner::TASK_DISPENSER, '002', 'Getränkeautomat Theke');
        $log = $this->log($gym, '002');

        $eager = ScannerAccessLog::withScanner($gym->id)->find($log->id);

        $this->assertNotNull(
            $eager->scanner,
            'Eager loading must resolve the scanner, otherwise the log falls back to "Scanner #002".'
        );
        $this->assertSame('Getränkeautomat Theke', $eager->scanner->device_name);
    }

    #[Test]
    public function eager_loading_does_not_attach_another_gyms_device(): void
    {
        [, $gym] = $this->ownerWithGym();
        $otherGym = Gym::factory()->create();

        // Same device number, different gym — must not be picked up.
        $this->device($otherGym, GymScanner::TASK_DISPENSER, '002', 'Fremdes Gerät');
        $log = $this->log($gym, '002');

        $eager = ScannerAccessLog::withScanner($gym->id)->find($log->id);

        $this->assertNull($eager->scanner);
    }

    #[Test]
    public function the_broadcast_payload_carries_the_device_task(): void
    {
        [, $gym] = $this->ownerWithGym();
        $this->device($gym, GymScanner::TASK_DISPENSER, '002', 'Getränkeautomat Theke');
        $log = $this->log($gym, '002');

        $event = new ScannerAccessEvent($log);

        $this->assertSame(
            GymScanner::TASK_DISPENSER,
            $event->broadcastWith()['log']['device_task']
        );
    }

    #[Test]
    public function the_dashboard_lists_recent_logs_with_their_task(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $this->device($gym, GymScanner::TASK_AREA_CONTROL, '003', 'Sauna');
        $this->log($gym, '003');

        $this->actingAs($owner)
            ->get(route('access-control.index'))
            ->assertInertia(fn ($page) => $page
                ->where('recentLogs.0.device_task', GymScanner::TASK_AREA_CONTROL)
            );
    }
}
