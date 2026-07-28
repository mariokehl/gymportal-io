<?php

namespace Tests\Unit\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberCreditLedger;
use App\Models\Payment;
use App\Services\CreditLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CreditLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreditLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreditLedgerService::class);
    }

    private function member(): Member
    {
        $gym = Gym::factory()->create();

        return Member::factory()->create(['gym_id' => $gym->id]);
    }

    public function test_top_up_increases_balance_cent_accurate(): void
    {
        $member = $this->member();

        $this->service->credit($member, 300, description: 'Test');
        $this->service->credit($member, 4990, description: 'Test');

        $this->assertSame(5290, $this->service->getBalance($member));
        $this->assertSame('52,90 €', $this->service->getFormattedBalance($member));
    }

    public function test_empty_ledger_has_zero_balance(): void
    {
        $this->assertSame(0, $this->service->getBalance($this->member()));
    }

    public function test_partial_redemption_returns_only_available(): void
    {
        $member = $this->member();
        $this->service->credit($member, 300, description: 'Aufladung');

        $redeemed = $this->service->redeem($member, 4990);

        $this->assertSame(300, $redeemed);
        $this->assertSame(0, $this->service->getBalance($member));
    }

    public function test_full_redemption_deducts_exact_amount(): void
    {
        $member = $this->member();
        $this->service->credit($member, 10000, description: 'Aufladung');

        $redeemed = $this->service->redeem($member, 4990);

        $this->assertSame(4990, $redeemed);
        $this->assertSame(5010, $this->service->getBalance($member));
    }

    public function test_balance_never_goes_negative(): void
    {
        $member = $this->member();

        $this->assertSame(0, $this->service->redeem($member, 5000));
        $this->assertSame(0, $this->service->getBalance($member));
    }

    /**
     * Core security requirement: a third party must not be able to inflate a
     * balance by editing the database (e.g. turning 3 € into 300 €).
     */
    public function test_tampering_with_amount_is_detected(): void
    {
        $member = $this->member();
        $entry = $this->service->credit($member, 300, description: 'Aufladung'); // 3,00 €

        // Attacker rewrites the encrypted amount and balance to 300,00 €.
        DB::table('member_credit_ledgers')->where('id', $entry->id)->update([
            'encrypted_amount' => Crypt::encryptString('30000'),
            'encrypted_balance_after' => Crypt::encryptString('30000'),
        ]);

        $this->assertFalse($this->service->verify($member->fresh()));

        // The forged 300 € must never be trusted: balance access throws and the
        // safe Member accessor reports 0 instead of the manipulated value.
        $this->assertSame(0, $member->fresh()->credit_balance_cents);
    }

    public function test_tampering_with_row_order_is_detected(): void
    {
        $member = $this->member();
        $this->service->credit($member, 1000, description: 'A');
        $this->service->credit($member, 2000, description: 'B');

        // Swap the encrypted balances of the two rows.
        $rows = DB::table('member_credit_ledgers')->orderBy('id')->get();
        DB::table('member_credit_ledgers')->where('id', $rows[0]->id)
            ->update(['encrypted_balance_after' => $rows[1]->encrypted_balance_after]);

        $this->assertFalse($this->service->verify($member->fresh()));
    }

    public function test_deleting_a_row_breaks_the_chain(): void
    {
        $member = $this->member();
        $this->service->credit($member, 1000, description: 'A');
        $second = $this->service->credit($member, 2000, description: 'B');
        $this->service->credit($member, 3000, description: 'C');

        // Force-delete the middle row bypassing the model guard.
        DB::table('member_credit_ledgers')->where('id', $second->id)->delete();

        $this->assertFalse($this->service->verify($member->fresh()));
    }

    public function test_chain_survives_blank_padded_hashes(): void
    {
        $member = $this->member();
        $this->service->credit($member, 1000, description: 'A');
        $this->service->credit($member, 2000, description: 'B');

        // Postgres returns char(64) columns blank-padded to their full width,
        // unlike MariaDB/SQLite. Simulate that by padding the stored hashes.
        foreach (DB::table('member_credit_ledgers')->orderBy('id')->get() as $row) {
            DB::table('member_credit_ledgers')->where('id', $row->id)->update([
                'entry_hash' => str_pad($row->entry_hash, 64),
                'prev_hash' => str_pad((string) $row->prev_hash, 64),
            ]);
        }

        $this->assertTrue($this->service->verify($member->fresh()));
        $this->assertSame(3000, $this->service->getBalance($member->fresh()));
    }

    public function test_ledger_entries_are_immutable(): void
    {
        $member = $this->member();
        $entry = $this->service->credit($member, 1000, description: 'A');

        $this->expectException(RuntimeException::class);
        $entry->update(['description' => 'changed']);
    }

    public function test_settle_payment_fully_covered_marks_paid(): void
    {
        $member = $this->member();
        $this->service->credit($member, 10000, description: 'Aufladung');

        $payment = Payment::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'amount' => 49.90,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'due_date' => now(),
            'status' => 'pending',
        ]);

        $result = $this->service->settlePayment($payment);

        $this->assertTrue($result['fully_covered']);
        $this->assertSame(4990, $result['redeemed_cents']);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('credit', $payment->fresh()->payment_method);
        $this->assertSame(5010, $this->service->getBalance($member));
    }

    public function test_settle_payment_partial_reduces_remaining_amount(): void
    {
        $member = $this->member();
        $this->service->credit($member, 300, description: 'Aufladung'); // 3,00 €

        $payment = Payment::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'amount' => 49.90,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'due_date' => now(),
            'status' => 'pending',
        ]);

        $result = $this->service->settlePayment($payment);

        $this->assertFalse($result['fully_covered']);
        $this->assertSame(300, $result['redeemed_cents']);
        $this->assertSame(4690, $result['remaining_cents']);
        // The payment now only collects the remaining 46,90 € over the method.
        $this->assertEquals(46.90, (float) $payment->fresh()->amount);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, $this->service->getBalance($member));

        // The redemption references the actual ledger row id.
        $ledgerId = MemberCreditLedger::where('payment_id', $payment->id)
            ->where('type', MemberCreditLedger::TYPE_REDEMPTION)
            ->value('id');
        $this->assertNotNull($ledgerId);
        $this->assertSame($ledgerId, $payment->fresh()->metadata['credit_ledger_id']);
    }

    public function test_settle_payment_is_idempotent(): void
    {
        $member = $this->member();
        $this->service->credit($member, 10000, description: 'Aufladung');

        $payment = Payment::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'amount' => 20.00,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'due_date' => now(),
            'status' => 'pending',
        ]);

        $this->service->settlePayment($payment);
        // A retry must not redeem a second time.
        $this->service->settlePayment($payment->fresh());

        $this->assertSame(8000, $this->service->getBalance($member));
        $this->assertSame(1, MemberCreditLedger::where('member_id', $member->id)
            ->where('type', MemberCreditLedger::TYPE_REDEMPTION)->count());
    }

    public function test_credit_from_paid_topup_credits_once_and_is_idempotent(): void
    {
        $member = $this->member();

        $payment = Payment::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'amount' => 300.00,
            'currency' => 'EUR',
            'description' => 'Guthaben-Aufladung',
            'due_date' => now(),
            'payment_method' => 'mollie_paymentlink',
            'is_credit_topup' => true,
            'status' => 'paid',
        ]);

        $entry = $this->service->creditFromPaidTopup($payment);

        $this->assertNotNull($entry);
        $this->assertSame(30000, $this->service->getBalance($member));
        $this->assertTrue((bool) $payment->fresh()->metadata['credit_credited']);
        $this->assertSame($entry->id, $payment->fresh()->metadata['credit_ledger_id']);

        // A repeated webhook must not credit again.
        $this->assertNull($this->service->creditFromPaidTopup($payment->fresh()));
        $this->assertSame(30000, $this->service->getBalance($member));
        $this->assertSame(1, MemberCreditLedger::where('member_id', $member->id)->count());
    }

    public function test_credit_from_paid_topup_ignores_unpaid_or_non_topup_payments(): void
    {
        $member = $this->member();

        $pendingTopup = Payment::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'amount' => 100.00,
            'currency' => 'EUR',
            'description' => 'Ausstehende Aufladung',
            'due_date' => now(),
            'payment_method' => 'mollie_paymentlink',
            'is_credit_topup' => true,
            'status' => 'pending',
        ]);

        $regularPaid = Payment::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'amount' => 100.00,
            'currency' => 'EUR',
            'description' => 'Reguläre Zahlung',
            'due_date' => now(),
            'payment_method' => 'mollie_directdebit',
            'is_credit_topup' => false,
            'status' => 'paid',
        ]);

        $this->assertNull($this->service->creditFromPaidTopup($pendingTopup));
        $this->assertNull($this->service->creditFromPaidTopup($regularPaid));
        $this->assertSame(0, $this->service->getBalance($member));
    }

    /**
     * @dataProvider centsProvider
     */
    public function test_to_cents_parses_various_formats(string $input, int $expected): void
    {
        $this->assertSame($expected, $this->service->toCents($input));
    }

    public static function centsProvider(): array
    {
        return [
            ['300,00', 30000],
            ['300.00', 30000],
            ['49,90', 4990],
            ['1.234,50', 123450],
            ['1,234.50', 123450],
            ['3', 300],
            ['0,01', 1],
        ];
    }
}
