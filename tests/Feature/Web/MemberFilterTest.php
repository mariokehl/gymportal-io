<?php

namespace Tests\Feature\Web;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberNfcTag;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $owner = User::factory()->create(['role_id' => $ownerRoleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $owner->fresh();
    }

    private function member(array $attributes = []): Member
    {
        return Member::factory()->create(['gym_id' => $this->gym->id, ...$attributes]);
    }

    private static function condition(string $field, string $op, array $extra = []): array
    {
        return ['field' => $field, 'op' => $op, 'value' => '', 'value2' => '', 'values' => [], ...$extra];
    }

    /**
     * @param  list<array{logic?: string, conditions: list<array>}>  $groups
     */
    private static function filter(array $groups, string $rootLogic = 'AND'): string
    {
        return json_encode([
            'rootLogic' => $rootLogic,
            'groups' => array_map(fn (array $group) => ['logic' => $group['logic'] ?? 'AND', 'conditions' => $group['conditions']], $groups),
        ]);
    }

    /**
     * @return list<int>
     */
    private function idsFor(string $filter, ?User $user = null): array
    {
        $ids = null;

        $this->actingAs($user ?? $this->owner)
            ->get(route('members.index', ['filter' => $filter, 'perPage' => 50]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['members']['data'])->pluck('id')->sort()->values()->all();
            });

        return $ids;
    }

    private function filterFor(string $field, string $op, array $extra = []): string
    {
        return self::filter([['conditions' => [self::condition($field, $op, $extra)]]]);
    }

    #[Test]
    public function it_lists_all_members_of_the_gym_without_a_filter_and_provides_the_builder_props(): void
    {
        $member = $this->member();
        Member::factory()->create();

        $this->actingAs($this->owner)
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Members/Index')
                ->where('members.total', 1)
                ->where('members.data.0.id', $member->id)
                ->where('memberCount', 1)
                ->where('canExport', true)
                ->has('filterFields')
                ->where('savedFilters', [])
            );
    }

    #[Test]
    public function it_filters_by_text_status_and_age(): void
    {
        $lea = $this->member(['first_name' => 'Lea', 'last_name' => 'Hoffmann', 'birth_date' => now()->subYears(30)->subDay()]);
        $tim = $this->member(['first_name' => 'Tim', 'last_name' => 'Wagner', 'birth_date' => now()->subYears(45), 'status' => 'paused']);
        $this->member(['first_name' => 'Eva', 'last_name' => '50%_Rabatt', 'birth_date' => null]);

        $this->assertSame([$lea->id], $this->idsFor($this->filterFor('name', 'contains', ['value' => 'lea hoff'])));
        $this->assertSame([$tim->id], $this->idsFor($this->filterFor('status', 'any', ['values' => ['paused']])));
        $this->assertSame([$lea->id], $this->idsFor($this->filterFor('age', 'between', ['value' => '25', 'value2' => '40'])));
        $this->assertSame([$lea->id], $this->idsFor($this->filterFor('age', 'eq', ['value' => '30'])));
        // LIKE wildcards in the input are matched literally.
        $this->assertCount(1, $this->idsFor($this->filterFor('name', 'contains', ['value' => '%_r'])));
    }

    #[Test]
    public function it_combines_groups_with_and_or(): void
    {
        $activeSleeper = $this->member(['status' => 'active']);
        $activeVisitor = $this->member(['status' => 'active']);
        $pausedSleeper = $this->member(['status' => 'paused']);
        CheckIn::factory()->create(['member_id' => $activeVisitor->id, 'gym_id' => $this->gym->id, 'check_in_time' => now()->subDays(3)]);
        CheckIn::factory()->create(['member_id' => $pausedSleeper->id, 'gym_id' => $this->gym->id, 'check_in_time' => now()->subDays(90)]);

        // Active AND (no check-ins OR last check-in more than 60 days ago)
        $sleepers = self::filter([
            ['conditions' => [self::condition('status', 'any', ['values' => ['active']])]],
            ['logic' => 'OR', 'conditions' => [
                self::condition('check_in_count', 'none'),
                self::condition('last_check_in', 'olderDays', ['value' => '60']),
            ]],
        ]);
        $this->assertSame([$activeSleeper->id], $this->idsFor($sleepers));

        // Same groups combined with OR
        $either = json_encode([...json_decode($sleepers, true), 'rootLogic' => 'OR']);
        $this->assertEqualsCanonicalizing([$activeSleeper->id, $activeVisitor->id, $pausedSleeper->id], $this->idsFor($either));

        $this->assertSame([$activeVisitor->id], $this->idsFor($this->filterFor('last_check_in', 'lastDays', ['value' => '7'])));
        $this->assertSame([$activeSleeper->id], $this->idsFor($this->filterFor('last_check_in', 'empty')));
    }

    #[Test]
    public function it_filters_by_memberships_payments_and_access(): void
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $this->gym->id, 'name' => 'Home-Tarif']);
        $withPlan = $this->member();
        Membership::factory()->create(['member_id' => $withPlan->id, 'membership_plan_id' => $plan->id]);
        $cancelledPlan = $this->member();
        Membership::factory()->cancelled()->create(['member_id' => $cancelledPlan->id, 'membership_plan_id' => $plan->id]);

        $withChargeback = $this->member();
        Payment::create([
            'gym_id' => $this->gym->id, 'member_id' => $withChargeback->id, 'amount' => -49.9, 'description' => 'Rückbuchung',
            'status' => 'chargeback', 'mollie_payment_id' => 'tr_1', 'due_date' => now()->toDateString(),
        ]);
        Payment::create([
            'gym_id' => $this->gym->id, 'member_id' => $withChargeback->id, 'amount' => 49.9, 'description' => 'Beitrag',
            'status' => 'failed', 'due_date' => now()->toDateString(),
        ]);
        PaymentMethod::create(['member_id' => $withChargeback->id, 'type' => 'mollie_directdebit', 'status' => 'active']);

        $withTag = $this->member();
        $config = MemberAccessConfig::factory()->create(['member_id' => $withTag->id]);
        MemberNfcTag::forceCreate(['member_access_config_id' => $config->id, 'uid' => '04A32F1B']);

        $this->assertSame([$withPlan->id], $this->idsFor($this->filterFor('membership_plan', 'any', ['values' => [(string) $plan->id]])));
        $this->assertSame([$withPlan->id], $this->idsFor($this->filterFor('membership_count', 'gte', ['value' => '1'])));
        $this->assertSame([$withChargeback->id], $this->idsFor($this->filterFor('has_outstanding_balance', 'yes')));
        $this->assertSame([$withChargeback->id], $this->idsFor($this->filterFor('outstanding_balance', 'gt', ['value' => '40'])));
        $this->assertSame([$withChargeback->id], $this->idsFor($this->filterFor('failed_payment_count', 'eq', ['value' => '1'])));
        $this->assertSame([$withChargeback->id], $this->idsFor($this->filterFor('payment_method', 'any', ['values' => ['mollie_directdebit']])));
        $this->assertSame([$withTag->id], $this->idsFor($this->filterFor('nfc_tag', 'eq', ['value' => '04a32f1b'])));
        $this->assertSame([$withTag->id], $this->idsFor($this->filterFor('has_nfc_tag', 'yes')));

        $this->actingAs($this->owner)
            ->get(route('members.index', ['filter' => $this->filterFor('has_outstanding_balance', 'yes')]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('members.data.0.outstanding_balance', 49.9)
                ->where('members.data.0.failed_payment_count', 1)
                ->where('members.data.0.payment_method', ['label' => 'SEPA-Lastschrift', 'provider' => 'Mollie'])
            );
    }

    #[Test]
    public function it_ignores_incomplete_conditions_and_unknown_fields_or_operators(): void
    {
        $this->member();
        $this->member();

        $filter = self::filter([['conditions' => [
            self::condition('name', 'contains'),
            self::condition('status', 'any'),
            self::condition('password', 'eq', ['value' => 'x']),
            self::condition('name', 'drop table', ['value' => 'x']),
        ]]]);

        $this->assertCount(2, $this->idsFor($filter));
        $this->assertCount(2, $this->idsFor('{not json'));

        $this->actingAs($this->owner)
            ->get(route('members.index', ['filter' => $filter]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('filter.groups.0.conditions', 2));
    }

    #[Test]
    public function negated_and_or_conditions_never_reach_members_of_another_gym(): void
    {
        $own = $this->member(['status' => 'active']);
        Member::factory()->create(['status' => 'paused']);

        $filter = self::filter([
            ['conditions' => [self::condition('status', 'none', ['values' => ['active']])]],
            ['conditions' => [self::condition('check_in_count', 'none')]],
        ], 'OR');

        $this->assertSame([$own->id], $this->idsFor($filter));
    }

    #[Test]
    public function it_hides_the_total_and_the_export_from_staff(): void
    {
        $staffRoleId = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff'])->id;
        $staff = User::factory()->create(['role_id' => $staffRoleId, 'current_gym_id' => $this->gym->id]);
        $this->member();

        $this->actingAs($staff)
            ->get(route('members.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('memberCount', null)
                ->where('canExport', false)
            );

        $this->actingAs($staff)->post(route('members.export'))->assertForbidden();
    }

    #[Test]
    public function it_exports_the_filtered_members_as_csv(): void
    {
        $lea = $this->member(['first_name' => 'Lea', 'last_name' => '=HYPERLINK("x")', 'status' => 'active']);
        $tim = $this->member(['first_name' => 'Tim', 'status' => 'active']);
        $this->member(['first_name' => 'Eva', 'status' => 'paused']);
        $foreign = Member::factory()->create(['first_name' => 'Fremd', 'status' => 'active']);
        $filter = $this->filterFor('status', 'any', ['values' => ['active']]);

        $csv = $this->actingAs($this->owner)
            ->post(route('members.export'), ['filter' => $filter])
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringStartsWith("\u{FEFF}Mitgliedsnummer;", $csv);
        $this->assertStringContainsString($lea->member_number, $csv);
        $this->assertStringContainsString($tim->member_number, $csv);
        $this->assertStringNotContainsString('Eva', $csv);
        $this->assertStringNotContainsString('Fremd', $csv);
        // Formula cells are neutralized.
        $this->assertStringContainsString('"\'=HYPERLINK(""x"")"', $csv);

        $selection = $this->actingAs($this->owner)
            ->post(route('members.export'), ['filter' => $filter, 'member_ids' => [$tim->id, $foreign->id]])
            ->streamedContent();
        $this->assertStringContainsString($tim->member_number, $selection);
        $this->assertStringNotContainsString($lea->member_number, $selection);
        $this->assertStringNotContainsString('Fremd', $selection);

        $allButLea = $this->actingAs($this->owner)
            ->post(route('members.export'), ['filter' => $filter, 'excluded_ids' => [$lea->id]])
            ->streamedContent();
        $this->assertStringContainsString($tim->member_number, $allButLea);
        $this->assertStringNotContainsString($lea->member_number, $allButLea);
    }
}
