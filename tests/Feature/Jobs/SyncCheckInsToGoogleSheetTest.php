<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncCheckInsToGoogleSheet;
use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymGoogleSheetIntegration;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncCheckInsToGoogleSheetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_rows_in_the_expected_column_order(): void
    {
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'name' => 'Premium']);
        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
        ]);
        Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $yesterday = now()->subDay();
        CheckIn::factory()->create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'check_in_time' => $yesterday->copy()->setTime(9, 30, 0),
        ]);

        GymGoogleSheetIntegration::create([
            'gym_id' => $gym->id,
            'google_sheet_enabled' => true,
            'credentials' => json_encode(['type' => 'service_account', 'client_email' => 'a@b.c', 'private_key' => 'k']),
            'spreadsheet_id' => 'SHEET123',
            'sheet_url' => 'https://docs.google.com/spreadsheets/d/SHEET123/edit',
        ]);

        $capturedRows = null;

        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldReceive('ensureHeaderRow')->once();
        $mock->shouldReceive('appendRows')
            ->once()
            ->andReturnUsing(function ($credentials, $spreadsheetId, $rows) use (&$capturedRows) {
                $capturedRows = $rows;
            });
        $this->app->instance(GoogleSheetsService::class, $mock);

        (new SyncCheckInsToGoogleSheet($gym->id, $yesterday->toDateString()))
            ->handle($this->app->make(GoogleSheetsService::class));

        $this->assertNotNull($capturedRows);
        $this->assertCount(1, $capturedRows);

        $row = $capturedRows[0];
        $this->assertCount(10, $row);
        $this->assertSame('Max Mustermann', $row[0]);
        $this->assertSame($yesterday->copy()->setTime(9, 30, 0)->format('Y-m-d H:i:s'), $row[1]);
        $this->assertSame('Premium', $row[2]);
        $this->assertSame('', $row[3]);
        $this->assertSame('max@example.com', $row[4]);
        $this->assertSame(['', '', '', '', ''], array_slice($row, 5));
    }

    #[Test]
    public function it_skips_when_the_integration_is_not_configured(): void
    {
        $gym = Gym::factory()->create();
        GymGoogleSheetIntegration::create([
            'gym_id' => $gym->id,
            'google_sheet_enabled' => false,
        ]);

        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldNotReceive('appendRows');
        $this->app->instance(GoogleSheetsService::class, $mock);

        (new SyncCheckInsToGoogleSheet($gym->id, now()->subDay()->toDateString()))
            ->handle($this->app->make(GoogleSheetsService::class));

        // No append should have happened; the shouldNotReceive expectation guards this.
        $this->assertDatabaseHas('gym_google_sheet_integrations', [
            'gym_id' => $gym->id,
            'last_synced_at' => null,
        ]);
    }

    #[Test]
    public function it_updates_last_synced_at_when_there_are_no_checkins(): void
    {
        $gym = Gym::factory()->create();
        $integration = GymGoogleSheetIntegration::create([
            'gym_id' => $gym->id,
            'google_sheet_enabled' => true,
            'credentials' => json_encode(['type' => 'service_account', 'client_email' => 'a@b.c', 'private_key' => 'k']),
            'spreadsheet_id' => 'SHEET123',
        ]);

        $mock = Mockery::mock(GoogleSheetsService::class);
        $mock->shouldNotReceive('appendRows');
        $this->app->instance(GoogleSheetsService::class, $mock);

        (new SyncCheckInsToGoogleSheet($gym->id, now()->subDay()->toDateString()))
            ->handle($this->app->make(GoogleSheetsService::class));

        $this->assertNotNull($integration->fresh()->last_synced_at);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
