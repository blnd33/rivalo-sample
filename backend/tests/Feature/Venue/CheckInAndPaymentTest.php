<?php

namespace Tests\Feature\Venue;

use App\Enums\RoleName;
use App\Exceptions\DomainRuleException;
use App\Filament\Pages\VenueCheckIn;
use App\Models\EntryPayment;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\VenueService;
use App\Support\CheckInCode;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Livewire\Livewire;
use Tests\TestCase;

class CheckInAndPaymentTest extends TestCase
{
    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = $this->staff(RoleName::TournamentOrganizer);
    }

    private function paidTournament(array $overrides = []): Tournament
    {
        $this->enablePaidEntry(true);

        return $this->tournament(array_merge([
            'entry_fee_minor' => 25000,
            'funding_model' => 'player_funded',
            'prize_rules' => 'Published prize rules.',
        ], $overrides), organizer: $this->organizer);
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

    private function venue(): VenueService
    {
        return app(VenueService::class);
    }

    // ---------------------------------------------------------------- Codes

    public function test_players_get_their_own_signed_check_in_code(): void
    {
        $tournament = $this->tournament(organizer: $this->organizer);
        $player = $this->player();
        $entry = app(RegistrationService::class)->register($tournament, $player);

        $response = $this->actingAs($player, 'sanctum')
            ->getJson("/api/v1/registrations/{$entry->id}/check-in-code")
            ->assertOk();

        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^\d+-[A-Za-z0-9_-]{16}$/', $code);
        $this->assertStringContainsString('/admin/check-in?code=', $response->json('data.qr_payload'));
        $this->assertSame($player->id, CheckInCode::resolve($code)->user_id);

        // Someone else cannot fetch this player's code.
        $this->actingAs($this->player(), 'sanctum')
            ->getJson("/api/v1/registrations/{$entry->id}/check-in-code")
            ->assertNotFound();
    }

    public function test_tampered_or_stale_codes_are_rejected(): void
    {
        $tournament = $this->tournament(organizer: $this->organizer);
        $player = $this->player();
        $entry = app(RegistrationService::class)->register($tournament, $player);
        $code = CheckInCode::for($entry->activeMembers()->first());

        $tampered = substr($code, 0, -1).(str_ends_with($code, 'A') ? 'B' : 'A');
        $this->expectRule('invalid_code', fn () => $this->venue()->resolveCode($tampered, $this->organizer));

        // Scanned URLs work as well as bare codes.
        $this->assertSame($entry->id, $this->venue()->resolveCode(CheckInCode::url($code), $this->organizer)->tournament_entry_id);

        app(RegistrationService::class)->cancel($entry, $player);
        $this->expectRule('code_inactive', fn () => $this->venue()->resolveCode($code, $this->organizer));
    }

    public function test_only_assigned_organizers_can_use_codes_and_record_payments(): void
    {
        $tournament = $this->paidTournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());
        $code = CheckInCode::for($entry->activeMembers()->first());
        $otherOrganizer = $this->staff(RoleName::TournamentOrganizer);

        $this->expectRule('not_assigned', fn () => $this->venue()->resolveCode($code, $otherOrganizer));
        $this->expectRule('not_assigned', fn () => $this->venue()->recordPayment($entry, $otherOrganizer));
        $this->expectRule('not_assigned', fn () => $this->venue()->checkIn($entry, $this->staff(RoleName::Referee)));
    }

    // ---------------------------------------------------------------- Payments

    public function test_a_player_cannot_mark_their_own_entry_fee_as_paid(): void
    {
        $tournament = $this->paidTournament();
        $player = $this->player();
        $entry = app(RegistrationService::class)->register($tournament, $player);

        $this->assertFalse($player->can('recordPayments', $tournament));
        $this->expectRule('not_assigned', fn () => $this->venue()->recordPayment($entry, $player));
        $this->assertSame('unpaid', $entry->fresh()->payment_status->value);
    }

    public function test_recording_the_exact_fee_marks_paid_and_issues_a_receipt(): void
    {
        $tournament = $this->paidTournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());

        $this->expectRule('amount_mismatch', fn () => $this->venue()->recordPayment($entry, $this->organizer, 20000));

        $payment = $this->venue()->recordPayment($entry, $this->organizer);

        $this->assertSame(25000, $payment->amount_minor);
        $this->assertSame('IQD', $payment->currency);
        $this->assertSame("RV-{$tournament->id}-0001", $payment->reference);
        $this->assertSame('paid', $entry->fresh()->payment_status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.recorded', 'actor_id' => $this->organizer->id]);
    }

    public function test_duplicate_payments_are_prevented(): void
    {
        $tournament = $this->paidTournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());
        $this->venue()->recordPayment($entry, $this->organizer);

        $this->expectRule('already_paid', fn () => $this->venue()->recordPayment($entry, $this->organizer));
        $this->assertSame(1, EntryPayment::where('tournament_entry_id', $entry->id)->count());
    }

    public function test_waitlisted_and_free_entries_cannot_pay(): void
    {
        $tournament = $this->paidTournament(['max_entrants' => 2]);
        $service = app(RegistrationService::class);
        $service->register($tournament, $this->player());
        $service->register($tournament, $this->player());
        $waitlisted = $service->register($tournament, $this->player());
        $this->expectRule('entry_not_reserved', fn () => $this->venue()->recordPayment($waitlisted, $this->organizer));

        $free = $this->tournament(organizer: $this->organizer);
        $freeEntry = $service->register($free, $this->player());
        $this->expectRule('free_entry', fn () => $this->venue()->recordPayment($freeEntry, $this->organizer));
    }

    public function test_payments_stop_when_paid_entry_is_switched_off_but_refunds_still_work(): void
    {
        $tournament = $this->paidTournament();
        $service = app(RegistrationService::class);
        $paidEntry = $service->register($tournament, $this->player());
        $this->venue()->recordPayment($paidEntry, $this->organizer);
        $unpaidEntry = $service->register($tournament, $this->player());

        $this->enablePaidEntry(false);

        $this->expectRule('paid_entry_disabled', fn () => $this->venue()->recordPayment($unpaidEntry, $this->organizer));
        $refund = $this->venue()->recordRefund($paidEntry, $this->organizer, 25000, 'Event changed; refund requested.');
        $this->assertSame('RF', substr($refund->reference, 0, 2));
        $this->assertSame('refunded', $paidEntry->fresh()->payment_status->value);
    }

    public function test_voiding_a_mistaken_payment_keeps_history_and_resets_status(): void
    {
        $tournament = $this->paidTournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());
        $payment = $this->venue()->recordPayment($entry, $this->organizer);

        $this->expectRule('reason_required', fn () => $this->venue()->voidPayment($payment, $this->organizer, ' '));
        $this->venue()->voidPayment($payment, $this->organizer, 'Recorded on the wrong entry.');

        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertSame('unpaid', $entry->fresh()->payment_status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.voided', 'reason' => 'Recorded on the wrong entry.']);

        // The correct payment can now be recorded, with a new receipt number.
        $again = $this->venue()->recordPayment($entry, $this->organizer);
        $this->assertNotSame($payment->reference, $again->reference);
    }

    public function test_payment_rows_cannot_be_edited_or_deleted(): void
    {
        $tournament = $this->paidTournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());
        $payment = $this->venue()->recordPayment($entry, $this->organizer);

        $this->expectException(\LogicException::class);
        $payment->update(['amount_minor' => 1]);
    }

    public function test_refunds_cannot_exceed_what_was_received_and_set_partial_status(): void
    {
        $tournament = $this->paidTournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());
        $this->venue()->recordPayment($entry, $this->organizer);

        $this->expectRule('invalid_refund', fn () => $this->venue()->recordRefund($entry, $this->organizer, 30000, 'Too much'));

        $this->venue()->recordRefund($entry, $this->organizer, 10000, 'Partial refund agreed with organizer.');
        $this->assertSame('partially_refunded', $entry->fresh()->payment_status->value);
        $this->assertSame(15000, $entry->fresh()->netPaidMinor());

        $this->expectRule('refunds_exist', fn () => $this->venue()->voidPayment($entry->payments()->where('kind', 'payment')->first(), $this->organizer, 'x'));
    }

    // ---------------------------------------------------------------- Check-in and eligibility

    public function test_check_in_requires_payment_and_then_makes_the_entry_eligible(): void
    {
        $tournament = $this->paidTournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());

        $this->expectRule('payment_required', fn () => $this->venue()->checkIn($entry, $this->organizer));

        $this->venue()->recordPayment($entry, $this->organizer);
        $checkedIn = $this->venue()->checkIn($entry, $this->organizer, $entry->activeMembers()->first());

        $this->assertSame('checked_in', $checkedIn->check_in_status->value);
        $this->assertSame('eligible', $checkedIn->eligibility_status->value);
        $this->assertSame($this->organizer->id, $checkedIn->checked_in_by_id);

        $this->expectRule('already_checked_in', fn () => $this->venue()->checkIn($entry, $this->organizer));
    }

    public function test_free_entries_check_in_without_payment(): void
    {
        $tournament = $this->tournament(organizer: $this->organizer);
        $entry = app(RegistrationService::class)->register($tournament, $this->player());

        $this->assertSame('eligible', $this->venue()->checkIn($entry, $this->organizer)->eligibility_status->value);
    }

    public function test_late_check_in_needs_a_reason(): void
    {
        $tournament = $this->tournament(['check_in_closes_at' => now()->addHour()], organizer: $this->organizer);
        $entry = app(RegistrationService::class)->register($tournament, $this->player());

        $this->travel(2)->hours();

        $this->expectRule('check_in_closed', fn () => $this->venue()->checkIn($entry, $this->organizer));
        $this->venue()->checkIn($entry, $this->organizer, null, 'Arrived late with the organizer\'s approval.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'checkin.recorded_late']);
    }

    public function test_voiding_payment_after_check_in_removes_eligibility(): void
    {
        $tournament = $this->paidTournament();
        $entry = app(RegistrationService::class)->register($tournament, $this->player());
        $payment = $this->venue()->recordPayment($entry, $this->organizer);
        $this->venue()->checkIn($entry, $this->organizer);

        $this->venue()->voidPayment($payment, $this->organizer, 'Counterfeit note.');

        $fresh = $entry->fresh();
        $this->assertSame('checked_in', $fresh->check_in_status->value);
        $this->assertSame('pending', $fresh->eligibility_status->value);
    }

    public function test_manual_ineligibility_sticks_until_cleared(): void
    {
        $tournament = $this->tournament(organizer: $this->organizer);
        $entry = app(RegistrationService::class)->register($tournament, $this->player());

        $this->venue()->markIneligible($entry, $this->organizer, 'Identity did not match the account photo.');
        $this->venue()->checkIn($entry, $this->organizer);
        $this->assertSame('ineligible', $entry->fresh()->eligibility_status->value);

        $this->venue()->clearIneligible($entry, $this->organizer, 'Identity confirmed with ID card.');
        $this->assertSame('eligible', $entry->fresh()->eligibility_status->value);
    }

    public function test_player_sees_receipts_and_check_in_on_their_entry(): void
    {
        $tournament = $this->paidTournament();
        $player = $this->player();
        $entry = app(RegistrationService::class)->register($tournament, $player);
        $payment = $this->venue()->recordPayment($entry, $this->organizer);
        $this->venue()->checkIn($entry, $this->organizer);

        $this->actingAs($player, 'sanctum')->getJson("/api/v1/tournaments/{$tournament->slug}/my-entry")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.check_in_status', 'checked_in')
            ->assertJsonPath('data.eligibility_status', 'eligible')
            ->assertJsonPath('data.payments.0.reference', $payment->reference)
            ->assertJsonPath('data.payments.0.formatted', '25,000 IQD')
            ->assertJsonMissingPath('data.payments.0.recorded_by_id');
    }

    // ---------------------------------------------------------------- Admin page

    public function test_scanning_a_code_opens_the_entrant_on_the_check_in_page(): void
    {
        $tournament = $this->paidTournament();
        $player = $this->player(['username' => 'scan_me']);
        $entry = app(RegistrationService::class)->register($tournament, $player);
        $code = CheckInCode::for($entry->activeMembers()->first());
        $this->organizer->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        $this->actingAs($this->organizer)->get(CheckInCode::url($code))
            ->assertOk()
            ->assertSee('scan_me')
            ->assertSee(__('Scanned code'));

        Livewire::test(VenueCheckIn::class, ['code' => $code])
            ->assertSet('entryId', $entry->id)
            ->callAction('recordPayment')
            ->assertHasNoActionErrors()
            ->callAction('checkIn')
            ->assertHasNoActionErrors();

        $this->assertSame('eligible', TournamentEntry::find($entry->id)->eligibility_status->value);
    }

    public function test_check_in_page_only_shows_actions_that_apply(): void
    {
        $tournament = $this->tournament(organizer: $this->organizer); // free entry
        $entry = app(RegistrationService::class)->register($tournament, $this->player());
        $code = CheckInCode::for($entry->activeMembers()->first());
        $this->organizer->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());

        $this->actingAs($this->organizer)->get(CheckInCode::url($code))
            ->assertOk()
            ->assertSee(__('Confirm check-in'))
            ->assertDontSee(__('Record cash payment'))
            ->assertDontSee(__('Undo check-in'))
            ->assertDontSee(__('Record refund'))
            ->assertDontSee(__('Void a payment'))
            ->assertDontSee(__('Remove not-eligible decision'));
    }

    public function test_players_cannot_open_the_check_in_page(): void
    {
        $this->actingAs($this->player())->get('/admin/check-in')->assertForbidden();
    }
}
