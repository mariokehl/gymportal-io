<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\GymGoogleSheetIntegration;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleSheetSettingsTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    private int $staffRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->staffRoleId = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff'])->id;
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

    private function validKeyFile(): UploadedFile
    {
        $key = [
            'type' => 'service_account',
            'client_email' => 'sync@project.iam.gserviceaccount.com',
            'private_key' => '-----BEGIN PRIVATE KEY-----',
        ];

        return UploadedFile::fake()->createWithContent('key.json', json_encode($key));
    }

    private function fakeGoogleSheets(): void
    {
        $mock = Mockery::mock(GoogleSheetsService::class)->makePartial();
        $mock->shouldReceive('verifyAccess')->andReturnTrue();
        $mock->shouldReceive('ensureHeaderRow')->andReturnNull();
        $this->app->instance(GoogleSheetsService::class, $mock);
    }

    #[Test]
    public function an_owner_can_link_a_google_sheet(): void
    {
        $this->fakeGoogleSheets();
        [$owner, $gym] = $this->ownerWithGym();

        $response = $this->actingAs($owner)->post(route('access-control.google-sheet-settings.update'), [
            'enabled' => '1',
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/ABC123def456/edit',
            'credentials_file' => $this->validKeyFile(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $integration = GymGoogleSheetIntegration::where('gym_id', $gym->id)->firstOrFail();
        $this->assertTrue($integration->google_sheet_enabled);
        $this->assertSame('ABC123def456', $integration->spreadsheet_id);
        $this->assertSame('sync@project.iam.gserviceaccount.com', $integration->service_account_email);
    }

    #[Test]
    public function the_response_never_exposes_the_credentials(): void
    {
        $this->fakeGoogleSheets();
        [$owner] = $this->ownerWithGym();

        $response = $this->actingAs($owner)->post(route('access-control.google-sheet-settings.update'), [
            'enabled' => '1',
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/ABC123def456/edit',
            'credentials_file' => $this->validKeyFile(),
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('private_key', $response->getContent());
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $response->getContent());
    }

    #[Test]
    public function the_credentials_are_stored_encrypted(): void
    {
        $this->fakeGoogleSheets();
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)->post(route('access-control.google-sheet-settings.update'), [
            'enabled' => '1',
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/ABC123def456/edit',
            'credentials_file' => $this->validKeyFile(),
        ])->assertOk();

        $raw = DB::table('gym_google_sheet_integrations')->where('gym_id', $gym->id)->value('credentials');

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $raw);
        $this->assertStringNotContainsString('client_email', $raw);
    }

    #[Test]
    public function an_invalid_key_file_is_rejected(): void
    {
        [$owner] = $this->ownerWithGym();

        $badFile = UploadedFile::fake()->createWithContent('key.json', json_encode(['type' => 'authorized_user']));

        $this->actingAs($owner)->post(route('access-control.google-sheet-settings.update'), [
            'enabled' => '1',
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/ABC123def456/edit',
            'credentials_file' => $badFile,
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    #[Test]
    public function an_owner_can_remove_the_link(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        GymGoogleSheetIntegration::create([
            'gym_id' => $gym->id,
            'google_sheet_enabled' => true,
            'credentials' => json_encode(['type' => 'service_account']),
            'spreadsheet_id' => 'ABC123def456',
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/ABC123def456/edit',
        ]);

        $this->actingAs($owner)
            ->delete(route('access-control.google-sheet-settings.destroy'))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('gym_google_sheet_integrations', ['gym_id' => $gym->id]);
    }

    #[Test]
    public function a_staff_member_cannot_manage_the_integration(): void
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);

        $staff = User::factory()->create(['role_id' => $this->staffRoleId]);
        $staff->update(['current_gym_id' => $gym->id]);

        $this->actingAs($staff->fresh())
            ->delete(route('access-control.google-sheet-settings.destroy'))
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
