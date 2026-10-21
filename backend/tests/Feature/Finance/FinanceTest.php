<?php

namespace Tests\Feature\Finance;

use App\Enums\LedgerFund;
use App\Enums\LedgerType;
use App\Enums\RoleName;
use App\Enums\TournamentStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Pages\FinanceReport;
use App\Filament\Pages\TournamentFinance;
use App\Filament\Resources\Sponsors\SponsorResource;
use App\Models\AuditLog;
use App\Models\Sponsor;
use App\Models\SponsorContribution;
use App\Models\Tournament;
use App\Models\TournamentLedgerEntry;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\RegistrationService;
use App\Services\TournamentService;
use App\Services\VenueService;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = $this->staff(RoleName::SuperAdmin);
        $this->finance->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
    }

    private function svc(): FinanceService
    {
        return app(FinanceService::class);
    }

    private function expectRule(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected domain rule [{$code}].");
        } catch (DomainRuleException $e) {
            $this->assertSame($code, $e->errorCode, $e->getMessage());
        }
    }

    private function sponsor(array $attrs = []): Sponsor
    {
        return Sponsor::create(array_merge(['name' => 'Acme '.uniqid(), 'is_public' => true], $attrs));
    }

    private function cash(Tournament $t, string $restriction, int $committed, int $received = 0, ?Sponsor $sponsor = null): SponsorContribution
    {
        $c = $this->svc()->addContribution($t, $sponsor ?? $this->sponsor(), $this->finance, [
            'type' => 'cash', 'restriction' => $restriction, 'committed_minor' => $committed,
        ]);
        if ($received > 0) {
            $this->svc()->receiveContribution($c, $this->finance, $received);
        }

        return $c->fresh();
    }

    /** Paid tournament with $n players who paid the 10,000 IQD fee at the venue. */
    private function paidTournamentWithPayments(int $n, ?User $organizer = null): Tournament
    {
        $this->enablePaidEntry();
        $organizer ??= $this->staff(RoleName::TournamentOrganizer);
        $t = $this->tournament(['entry_fee_minor' => 10000, 'funding_model' => 'player_funded', 'prize_rules' => 'Published.'], organizer: $organizer);
        for ($i = 0; $i < $n; $i++) {
            $entry = app(RegistrationService::class)->register($t->fresh(), $this->player());
            app(VenueService::class)->recordPayment($entry, $organizer);
        }

        return $t->fresh();
    }

    private function complete(Tournament $t): Tournament
    {
        $svc = app(TournamentService::class);
        foreach ([TournamentStatus::RegistrationClosed, TournamentStatus::InProgress, TournamentStatus::Completed] as $step) {
            if ($t->status !== $step) {
                $t = $svc->transition($t->fresh(), $this->finance, $step);
            }
        }

        return $t->fresh();
    }

    // ---------------------------------------------------------------- Venue receipts → ledger

    public function test_venue_payments_refunds_and_voids_flow_into_the_ledger(): void
    {
        $organizer = $this->staff(RoleName::TournamentOrganizer);
        $t = $this->paidTournamentWithPayments(3, $organizer);

        $this->assertSame(30000, $this->svc()->summary($t)['net_entry']);
        $this->assertSame(30000, $this->svc()->balances($t)['unrestricted']);

        $entry = $t->entries()->first();
        app(VenueService::class)->recordRefund($entry, $organizer, 4000, 'Partial refund agreed');
        $payment = $t->entries()->skip(1)->first()->payments()->first();
        app(VenueService::class)->voidPayment($payment, $organizer, 'Recorded on the wrong entrant');

        $summary = $this->svc()->summary($t);
        $this->assertSame(20000, $summary['gross_entry']); // 30,000 minus the voided 10,000
        $this->assertSame(4000, $summary['refunds']);
        $this->assertSame(16000, $summary['net_entry']);
        $this->assertSame(1, TournamentLedgerEntry::where('type', LedgerType::Reversal->value)->count());
        // One ledger row per receipt: syncing again changes nothing.
        $this->svc()->syncEntryPayment($entry->payments()->first());
        $this->assertSame(5, TournamentLedgerEntry::where('tournament_id', $t->id)->count());
    }

    public function test_ledger_rows_are_append_only(): void
    {
        $t = $this->paidTournamentWithPayments(1);
        $row = TournamentLedgerEntry::where('tournament_id', $t->id)->first();

        $this->expectException(LogicException::class);
        $row->update(['amount_minor' => 1]);
    }

    public function test_ledger_rows_cannot_be_deleted(): void
    {
        $t = $this->paidTournamentWithPayments(1);

        $this->expectException(LogicException::class);
        TournamentLedgerEntry::where('tournament_id', $t->id)->first()->delete();
    }

    // ---------------------------------------------------------------- Sponsor restrictions

    public function test_sponsor_cash_lands_in_the_fund_its_restriction_requires(): void
    {
        $t = $this->tournament();
        $this->cash($t, 'prize_only', 50000, 50000);
        $this->cash($t, 'expenses_only', 20000, 15000);
        $this->cash($t, 'unrestricted', 10000, 10000);

        $b = $this->svc()->balances($t);
        $this->assertSame(50000, $b['prize']);
        $this->assertSame(15000, $b['expense']);
        $this->assertSame(10000, $b['unrestricted']);
        $this->assertSame(5000, $this->svc()->summary($t)['pledged_outstanding']);
    }

    public function test_prize_only_money_can_never_pay_the_organizer_or_expenses(): void
    {
        $t = $this->tournament();
        $this->cash($t, 'prize_only', 50000, 50000);
        $this->svc()->saveRules($t, $this->finance, 5000, 2000, null, 'Owner decision for test');
        $this->svc()->approveRules($t, $this->finance, 'Owner approved');

        // The organizer can't be paid from the prize fund, and the other funds are empty.
        $this->expectRule('fund_not_allowed', fn () => $this->svc()->recordOrganizerPayout($t, $this->finance, 1000, LedgerFund::Prize, 'Organizer'));
        $this->expectRule('fund_not_allowed', fn () => $this->svc()->recordExpense($t, $this->finance, LedgerType::VenueExpense, 1000, LedgerFund::Prize, 'Venue', 'Hall'));
        $this->expectRule('fund_not_allowed', fn () => $this->svc()->allocate($t, $this->finance, LedgerFund::Unrestricted, 1000, 'x'));
        // Restricted money cannot be moved: allocation only draws from unrestricted, which is empty.
        $this->expectRule('insufficient_funds', fn () => $this->svc()->allocate($t, $this->finance, LedgerFund::Organizer, 1000, 'Move prize money'));
        $this->assertSame(50000, $this->svc()->balances($t)['prize']);
    }

    public function test_receipts_cannot_exceed_the_pledge_and_cancelled_pledges_take_no_cash(): void
    {
        $t = $this->tournament();
        $c = $this->cash($t, 'unrestricted', 10000, 6000);
        $this->assertSame('partially_received', $c->status->value);

        $this->expectRule('invalid_amount', fn () => $this->svc()->receiveContribution($c, $this->finance, 5000));
        $this->svc()->receiveContribution($c, $this->finance, 4000);
        $this->assertSame('received', $c->fresh()->status->value);

        $pledge = $this->cash($t, 'unrestricted', 3000);
        $this->expectRule('cash_received', fn () => $this->svc()->cancelContribution($c->fresh(), $this->finance, 'x'));
        $this->svc()->cancelContribution($pledge, $this->finance, 'Sponsor withdrew');
        $this->expectRule('contribution_cancelled', fn () => $this->svc()->receiveContribution($pledge->fresh(), $this->finance, 1000));
    }

    // ---------------------------------------------------------------- Draft rules (D4)

    public function test_draft_rules_only_project_and_block_formula_payouts(): void
    {
        $t = $this->paidTournamentWithPayments(4); // 40,000 net
        $this->svc()->saveRules($t, $this->finance, 6000, 2500, null, 'Draft for discussion');

        $p = $this->svc()->projection($t);
        $this->assertFalse($p['approved']);
        $this->assertSame(24000, $p['prize_pool']);
        $this->assertSame(10000, $p['organizer_share']);

        $this->expectRule('rules_not_approved', fn () => $this->svc()->recordOrganizerPayout($t, $this->finance, 1000, LedgerFund::Unrestricted, 'Organizer'));

        $this->svc()->approveRules($t, $this->finance, 'Owner decided on 18 Oct, minutes in drive');
        $this->expectRule('over_share', fn () => $this->svc()->recordOrganizerPayout($t, $this->finance, 10001, LedgerFund::Unrestricted, 'Organizer'));
        $entry = $this->svc()->recordOrganizerPayout($t, $this->finance, 10000, LedgerFund::Unrestricted, 'Organizer Ltd');
        $this->assertSame(-10000, $entry->amount_minor);
        $this->assertSame(10000, $this->svc()->summary($t)['organizer_paid']);
    }

    public function test_rules_cannot_exceed_100_percent_and_approval_needs_both_shares(): void
    {
        $t = $this->tournament(status: TournamentStatus::Published);
        $this->expectRule('shares_over_100', fn () => $this->svc()->saveRules($t, $this->finance, 7000, 4000));
        $this->svc()->saveRules($t, $this->finance, 7000, null);
        $this->expectRule('rules_incomplete', fn () => $this->svc()->approveRules($t, $this->finance, 'ok'));
    }

    public function test_locked_rule_changes_need_a_reason_reset_approval_and_are_audited(): void
    {
        $t = $this->tournament(); // registration open → financial rules locked
        $this->assertNotNull($t->financial_rules_locked_at);

        $this->expectRule('reason_required', fn () => $this->svc()->saveRules($t, $this->finance, 5000, 2000));
        $this->svc()->saveRules($t, $this->finance, 5000, 2000, null, 'Initial values agreed late');
        $this->svc()->approveRules($t, $this->finance, 'Approved');
        $this->svc()->saveRules($t, $this->finance, 5500, 2000, null, 'Correction: venue discount');

        $this->assertFalse($this->svc()->rules($t)->isApproved());
        $this->assertTrue(AuditLog::where('action', 'finance.rules_corrected')->where('reason', 'Correction: venue discount')->exists());
    }

    public function test_rules_cannot_change_after_formula_payouts(): void
    {
        $t = $this->paidTournamentWithPayments(2);
        $this->svc()->saveRules($t, $this->finance, 5000, 2000, null, 'r');
        $this->svc()->approveRules($t, $this->finance, 'ok');
        $this->svc()->recordOrganizerPayout($t, $this->finance, 1000, LedgerFund::Unrestricted, 'Org');

        $this->expectRule('payouts_exist', fn () => $this->svc()->saveRules($t, $this->finance, 4000, 2000, null, 'change'));
    }

    // ---------------------------------------------------------------- Prizes

    public function test_guaranteed_prizes_need_received_sponsor_cash(): void
    {
        $t = $this->tournament(status: TournamentStatus::Published);
        $pledged = $this->cash($t, 'prize_only', 100000);
        $expenses = $this->cash($t, 'expenses_only', 50000, 50000);

        $data = ['place' => 1, 'basis' => 'fixed', 'amount_minor' => 60000, 'label' => 'guaranteed'];
        $this->expectRule('guarantee_needs_backing', fn () => $this->svc()->savePrize($t, $this->finance, $data));
        $this->expectRule('guarantee_needs_backing', fn () => $this->svc()->savePrize($t, $this->finance, $data + ['backing_contribution_id' => $expenses->id]));
        $this->expectRule('guarantee_not_received', fn () => $this->svc()->savePrize($t, $this->finance, $data + ['backing_contribution_id' => $pledged->id]));

        $this->svc()->receiveContribution($pledged, $this->finance, 60000);
        $prize = $this->svc()->savePrize($t, $this->finance, $data + ['backing_contribution_id' => $pledged->id]);
        $this->assertSame('guaranteed', $prize->label->value);

        // The second guarantee would exceed what was received.
        $this->expectRule('guarantee_not_received', fn () => $this->svc()->savePrize($t, $this->finance, ['place' => 2, 'basis' => 'fixed', 'amount_minor' => 1, 'label' => 'guaranteed', 'backing_contribution_id' => $pledged->id]));
        // Reversing the receipt would break the guarantee.
        $receipt = TournamentLedgerEntry::where('sponsor_contribution_id', $pledged->id)->first();
        $this->expectRule('guarantee_would_break', fn () => $this->svc()->reverse($receipt, $this->finance, 'Mistake'));
        $this->expectRule('cash_received', fn () => $this->svc()->cancelContribution($pledged->fresh(), $this->finance, 'x'));
    }

    public function test_pool_share_amounts_stay_hidden_until_rules_are_approved(): void
    {
        $t = $this->paidTournamentWithPayments(4); // 40,000
        $this->svc()->saveRules($t, $this->finance, 5000, 2000, null, 'draft'); // pool 20,000
        $first = $this->svc()->savePrize($t, $this->finance, ['place' => 1, 'basis' => 'pool_share', 'pool_share_bp' => 6000, 'label' => 'estimated'], null, 'add');
        $this->svc()->savePrize($t, $this->finance, ['place' => 2, 'basis' => 'fixed', 'in_kind_description' => 'Medals', 'label' => 'finalized'], null, 'add');

        $this->assertNull($this->svc()->prizeAmount($first));
        $this->getJson("/api/v1/tournaments/{$t->slug}")
            ->assertOk()
            ->assertJsonPath('data.prizes.0.amount_formatted', null)
            ->assertJsonPath('data.prizes.0.label', 'estimated')
            ->assertJsonPath('data.prizes.1.in_kind', 'Medals');

        $this->expectRule('pool_over_100', fn () => $this->svc()->savePrize($t, $this->finance, ['place' => 3, 'basis' => 'pool_share', 'pool_share_bp' => 4001, 'label' => 'estimated'], null, 'add'));
        // Finalizing a pool share needs approved rules and closed registration.
        $this->expectRule('cannot_finalize', fn () => $this->svc()->savePrize($t, $this->finance, ['place' => 1, 'basis' => 'pool_share', 'pool_share_bp' => 6000, 'label' => 'finalized'], $first));

        $this->svc()->approveRules($t, $this->finance, 'ok');
        $this->assertSame(12000, $this->svc()->prizeAmount($first->fresh()));
        $this->getJson("/api/v1/tournaments/{$t->slug}")->assertJsonPath('data.prizes.0.amount_formatted', '12,000 IQD');
    }

    public function test_locked_prize_changes_need_a_reason_except_making_them_more_certain(): void
    {
        $t = $this->tournament(status: TournamentStatus::Published);
        $prize = $this->svc()->savePrize($t, $this->finance, ['place' => 1, 'basis' => 'fixed', 'amount_minor' => 5000, 'label' => 'estimated']);
        app(TournamentService::class)->transition($t, $this->finance, TournamentStatus::RegistrationOpen);
        $t = $t->fresh();

        $this->svc()->savePrize($t, $this->finance, ['place' => 1, 'basis' => 'fixed', 'amount_minor' => 5000, 'label' => 'finalized'], $prize);
        $this->expectRule('reason_required', fn () => $this->svc()->savePrize($t, $this->finance, ['place' => 1, 'basis' => 'fixed', 'amount_minor' => 4000, 'label' => 'finalized'], $prize->fresh()));
        $this->svc()->savePrize($t, $this->finance, ['place' => 1, 'basis' => 'fixed', 'amount_minor' => 4000, 'label' => 'finalized'], $prize->fresh(), 'Sponsor reduced support');
        $this->assertTrue(AuditLog::where('action', 'prize.corrected')->exists());
    }

    public function test_prize_payouts_need_a_completed_tournament_and_a_final_amount(): void
    {
        $t = $this->tournament();
        $c = $this->cash($t, 'prize_only', 30000, 30000);
        $estimated = $this->svc()->savePrize($t, $this->finance, ['place' => 2, 'basis' => 'fixed', 'amount_minor' => 5000, 'label' => 'estimated'], null, 'add');
        $prize = $this->svc()->savePrize($t, $this->finance, ['place' => 1, 'basis' => 'fixed', 'amount_minor' => 20000, 'label' => 'guaranteed', 'backing_contribution_id' => $c->id], null, 'add');

        $this->expectRule('not_completed', fn () => $this->svc()->recordPrizePayout($prize, $this->finance, 20000, LedgerFund::Prize, 'Winner'));
        $t = $this->complete($t);

        $this->expectRule('prize_estimated', fn () => $this->svc()->recordPrizePayout($estimated, $this->finance, 5000, LedgerFund::Prize, 'Runner-up'));
        $this->expectRule('over_prize', fn () => $this->svc()->recordPrizePayout($prize, $this->finance, 20001, LedgerFund::Prize, 'Winner'));
        $this->expectRule('fund_not_allowed', fn () => $this->svc()->recordPrizePayout($prize, $this->finance, 100, LedgerFund::Organizer, 'Winner'));
        $this->svc()->recordPrizePayout($prize, $this->finance, 20000, LedgerFund::Prize, 'sample_player01');

        $this->assertSame(20000, $this->svc()->prizePaid($prize));
        $this->assertSame(10000, $this->svc()->balances($t)['prize']);
    }

    // ---------------------------------------------------------------- Expenses, allocation, reversal

    public function test_expenses_allocations_and_reversals(): void
    {
        $t = $this->paidTournamentWithPayments(3); // 30,000 unrestricted

        $this->expectRule('insufficient_funds', fn () => $this->svc()->recordExpense($t, $this->finance, LedgerType::VenueExpense, 1000, LedgerFund::Expense, 'Hall', 'Rent'));
        [$out, $in] = $this->svc()->allocate($t, $this->finance, LedgerFund::Expense, 12000, 'Venue budget');
        $expense = $this->svc()->recordExpense($t, $this->finance, LedgerType::VenueExpense, 8000, LedgerFund::Expense, 'Sample Hall', 'Court rental');

        $b = $this->svc()->balances($t);
        $this->assertSame(18000, $b['unrestricted']);
        $this->assertSame(4000, $b['expense']);

        // The allocation's money is partly spent, so it can't be undone before the expense.
        $this->expectRule('insufficient_funds', fn () => $this->svc()->reverse($in, $this->finance, 'Undo'));
        $this->svc()->reverse($expense, $this->finance, 'Paid twice by mistake');
        $this->expectRule('already_reversed', fn () => $this->svc()->reverse($expense, $this->finance, 'again'));
        $this->svc()->reverse($out, $this->finance, 'Budget no longer needed'); // reverses the pair

        $b = $this->svc()->balances($t);
        $this->assertSame(30000, $b['unrestricted']);
        $this->assertSame(0, $b['expense']);

        $revenue = TournamentLedgerEntry::where('tournament_id', $t->id)->where('type', 'entry_revenue')->first();
        $this->expectRule('automatic_entry', fn () => $this->svc()->reverse($revenue, $this->finance, 'x'));
    }

    // ---------------------------------------------------------------- Access and privacy

    public function test_only_finance_managers_can_change_money_records(): void
    {
        $organizer = $this->staff(RoleName::TournamentOrganizer);
        $t = $this->paidTournamentWithPayments(1, $organizer);

        $this->expectRule('forbidden', fn () => $this->svc()->recordExpense($t, $organizer, LedgerType::OtherExpense, 100, LedgerFund::Unrestricted, 'x', 'y'));
        $this->expectRule('forbidden', fn () => $this->svc()->saveRules($t, $organizer, 1000, 1000, null, 'r'));

        $organizer->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
        $this->actingAs($organizer)->get(TournamentFinance::getUrl())->assertForbidden();
        $this->actingAs($organizer)->get(FinanceReport::getUrl())->assertForbidden();
        $this->actingAs($organizer)->get(SponsorResource::getUrl('index'))->assertForbidden();
    }

    public function test_public_api_shows_consenting_sponsors_without_money_or_contacts(): void
    {
        $t = $this->tournament();
        $this->cash($t, 'prize_only', 99000, 0, $this->sponsor(['name' => 'Shown Co', 'contact_email' => 'secret@example.com']));
        $this->cash($t, 'unrestricted', 5000, 0, $this->sponsor(['name' => 'Hidden Co', 'is_public' => false]));
        $cancelled = $this->cash($t, 'unrestricted', 5000, 0, $this->sponsor(['name' => 'Gone Co']));
        $this->svc()->cancelContribution($cancelled, $this->finance, 'Withdrew');

        $json = $this->getJson("/api/v1/tournaments/{$t->slug}")->assertOk()->json('data.sponsors');
        $this->assertSame(['Shown Co'], array_column($json, 'name'));
        $body = json_encode($json);
        $this->assertStringNotContainsString('secret@example.com', $body);
        $this->assertStringNotContainsString('99000', $body);
    }

    // ---------------------------------------------------------------- Admin pages

    public function test_finance_page_renders_and_records_an_expense(): void
    {
        $t = $this->paidTournamentWithPayments(2);
        $this->actingAs($this->finance);

        Livewire::test(TournamentFinance::class, ['tournamentId' => $t->id])
            ->assertSee(__('Draft: projections only, no formula-based payouts'))
            ->assertSee('20,000 IQD')
            ->callAction('recordExpense', [
                'type' => 'referee_expense', 'fund' => 'unrestricted', 'amount_minor' => 3000,
                'payee' => 'Sample Referee', 'note' => 'Match fees',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(17000, $this->svc()->balances($t)['unrestricted']);
        $this->actingAs($this->finance)->get(FinanceReport::getUrl())->assertOk()->assertSee($t->name);
    }

    public function test_finance_page_shows_a_refusal_instead_of_failing(): void
    {
        $t = $this->tournament();
        $this->actingAs($this->finance);

        Livewire::test(TournamentFinance::class, ['tournamentId' => $t->id])
            ->callAction('recordExpense', [
                'type' => 'venue_expense', 'fund' => 'expense', 'amount_minor' => 3000, 'payee' => 'Hall', 'note' => 'Rent',
            ])
            ->assertNotified(__('Not allowed'));

        $this->assertSame(0, TournamentLedgerEntry::where('tournament_id', $t->id)->count());
    }
}
