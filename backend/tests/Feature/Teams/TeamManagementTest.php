<?php

namespace Tests\Feature\Teams;

use App\Enums\RoleName;
use App\Exceptions\DomainRuleException;
use App\Models\TeamMembership;
use App\Services\TeamService;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    public function test_creating_a_team_makes_the_creator_captain(): void
    {
        $captain = $this->player();

        $response = $this->actingAs($captain, 'sanctum')
            ->postJson('/api/v1/teams', ['sport_id' => $this->sport('football')->id, 'name' => 'Night Owls FC'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Night Owls FC')
            ->assertJsonPath('data.viewer.is_captain', true);

        $this->assertCount(1, $response->json('data.members'));
        $this->assertSame('captain', $response->json('data.members.0.role'));
    }

    public function test_team_names_are_unique_per_sport_ignoring_case(): void
    {
        $this->actingAs($this->player(), 'sanctum')
            ->postJson('/api/v1/teams', ['sport_id' => $this->sport('football')->id, 'name' => 'Lions'])->assertCreated();

        $this->actingAs($this->player(), 'sanctum')
            ->postJson('/api/v1/teams', ['sport_id' => $this->sport('football')->id, 'name' => 'LIONS'])
            ->assertStatus(409)->assertJsonPath('code', 'team_name_taken');
    }

    public function test_teams_cannot_be_created_for_individual_only_sports(): void
    {
        $this->actingAs($this->player(), 'sanctum')
            ->postJson('/api/v1/teams', ['sport_id' => $this->sport('cue-sports')->id, 'name' => 'Cue Crew'])
            ->assertUnprocessable()->assertJsonPath('code', 'sport_not_team_based');
    }

    public function test_invitation_accept_flow(): void
    {
        $team = $this->teamWithMembers('football', 0);
        $invitee = $this->player(['username' => 'new_signing']);

        $invitationId = $this->actingAs($team->captain, 'sanctum')
            ->postJson("/api/v1/teams/{$team->slug}/invitations", ['username' => 'new_signing'])
            ->assertCreated()->json('data.id');

        // The invitee sees it in their inbox and accepts.
        $this->actingAs($invitee, 'sanctum')->getJson('/api/v1/me/invitations')
            ->assertOk()->assertJsonPath('invitations.0.id', $invitationId);

        $this->actingAs($invitee, 'sanctum')->postJson("/api/v1/invitations/{$invitationId}/accept")->assertOk();

        $this->assertTrue($team->fresh()->hasActiveMember($invitee));
    }

    public function test_only_the_invitee_can_accept_and_declined_invitations_cannot_be_accepted(): void
    {
        $team = $this->teamWithMembers('football', 0);
        $invitee = $this->player();
        $stranger = $this->player();
        $invitation = app(TeamService::class)->invite($team, $team->captain, $invitee);

        $this->actingAs($stranger, 'sanctum')->postJson("/api/v1/invitations/{$invitation->id}/accept")
            ->assertForbidden()->assertJsonPath('code', 'not_invitee');

        $this->actingAs($invitee, 'sanctum')->postJson("/api/v1/invitations/{$invitation->id}/decline")->assertOk();
        $this->actingAs($invitee, 'sanctum')->postJson("/api/v1/invitations/{$invitation->id}/accept")
            ->assertStatus(409)->assertJsonPath('code', 'invitation_closed');

        $this->assertFalse($team->fresh()->hasActiveMember($invitee));
    }

    public function test_expired_invitations_cannot_be_accepted(): void
    {
        $team = $this->teamWithMembers('football', 0);
        $invitee = $this->player();
        $invitation = app(TeamService::class)->invite($team, $team->captain, $invitee);

        $this->travel(TeamService::INVITATION_TTL_DAYS + 1)->days();

        $this->actingAs($invitee, 'sanctum')->postJson("/api/v1/invitations/{$invitation->id}/accept")
            ->assertStatus(409)->assertJsonPath('code', 'invitation_closed');
    }

    public function test_non_captains_cannot_invite_or_remove_members(): void
    {
        $team = $this->teamWithMembers('football', 1);
        $member = $team->activeMemberships()->where('user_id', '!=', $team->captain_id)->first()->user;
        $this->player(['username' => 'target_player']);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/teams/{$team->slug}/invitations", ['username' => 'target_player'])
            ->assertForbidden();

        $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/v1/teams/{$team->slug}/members/{$team->captain_id}")
            ->assertForbidden();
    }

    public function test_sport_member_limit_is_enforced(): void
    {
        // Padel teams are limited to 4 members by the seeded catalog.
        $team = $this->teamWithMembers('padel', 3);
        $this->assertSame(4, $team->activeMemberships()->count());

        $this->expectException(DomainRuleException::class);
        app(TeamService::class)->invite($team, $team->captain, $this->player());
    }

    public function test_captain_transfer_requires_nominee_acceptance(): void
    {
        $team = $this->teamWithMembers('football', 1);
        $oldCaptain = $team->captain;
        $nominee = $team->activeMemberships()->where('user_id', '!=', $oldCaptain->id)->first()->user;

        $transferId = $this->actingAs($oldCaptain, 'sanctum')
            ->postJson("/api/v1/teams/{$team->slug}/captain-transfer", ['username' => $nominee->username])
            ->assertCreated()->json('id');

        $this->assertSame($oldCaptain->id, $team->fresh()->captain_id, 'Nothing changes until the nominee accepts.');

        $this->actingAs($nominee, 'sanctum')->postJson("/api/v1/captain-transfers/{$transferId}/respond", ['accept' => true])
            ->assertOk()->assertJsonPath('status', 'accepted');

        $team->refresh();
        $this->assertSame($nominee->id, $team->captain_id);
        $this->assertSame('captain', $team->activeMemberships()->where('user_id', $nominee->id)->first()->role->value);
        $this->assertSame('member', $team->activeMemberships()->where('user_id', $oldCaptain->id)->first()->role->value);
    }

    public function test_captain_cannot_leave_without_transferring(): void
    {
        $team = $this->teamWithMembers('football', 1);

        $this->actingAs($team->captain, 'sanctum')->postJson("/api/v1/teams/{$team->slug}/leave")
            ->assertStatus(409)->assertJsonPath('code', 'captain_cannot_leave');
    }

    public function test_forced_captain_transfer_requires_a_reason_and_is_audited(): void
    {
        $team = $this->teamWithMembers('football', 1);
        $support = $this->staff(RoleName::SupportStaff);
        $newCaptain = $team->activeMemberships()->where('user_id', '!=', $team->captain_id)->first()->user;

        try {
            app(TeamService::class)->forceCaptainTransfer($team, $support, $newCaptain, '  ');
            $this->fail('A forced transfer without a reason must be refused.');
        } catch (DomainRuleException $e) {
            $this->assertSame('reason_required', $e->errorCode);
        }

        app(TeamService::class)->forceCaptainTransfer($team, $support, $newCaptain, 'Captain unreachable for 30 days (ticket #12).');
        $this->assertSame($newCaptain->id, $team->fresh()->captain_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'team.captain_forced', 'actor_id' => $support->id]);
    }

    public function test_my_teams_lists_active_teams_with_members(): void
    {
        $team = $this->teamWithMembers('football', 2);

        $this->actingAs($team->captain, 'sanctum')->getJson('/api/v1/me/teams')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $team->slug)
            ->assertJsonCount(3, 'data.0.members')
            ->assertJsonMissingPath('data.0.members.0.user.email');
    }

    public function test_leaving_keeps_membership_history(): void
    {
        $team = $this->teamWithMembers('football', 1);
        $member = $team->activeMemberships()->where('user_id', '!=', $team->captain_id)->first()->user;

        $this->actingAs($member, 'sanctum')->postJson("/api/v1/teams/{$team->slug}/leave")->assertOk();

        $history = TeamMembership::where('team_id', $team->id)->where('user_id', $member->id)->first();
        $this->assertNotNull($history);
        $this->assertSame('left', $history->status->value);
        $this->assertNotNull($history->left_at);
        $this->assertFalse($team->fresh()->hasActiveMember($member));
    }
}
