<?php

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use Tests\TestCase;

/**
 * Guests (no account, no token) may browse public content but cannot act.
 */
class GuestAccessTest extends TestCase
{
    public function test_guests_can_browse_public_content(): void
    {
        $tournament = $this->tournament();
        $team = $this->teamWithMembers('padel', 1);
        $player = $this->player(['username' => 'public_player']);

        $this->getJson('/api/v1/config')->assertOk();
        $this->getJson('/api/v1/catalog/sports')->assertOk();
        $this->getJson('/api/v1/tournaments')->assertOk()->assertJsonFragment(['slug' => $tournament->slug]);
        $this->getJson("/api/v1/tournaments/{$tournament->slug}")->assertOk()->assertJsonPath('data.lobby_details', null);
        $this->getJson("/api/v1/tournaments/{$tournament->slug}/entries")->assertOk();
        $this->getJson("/api/v1/teams/{$team->slug}")->assertOk()->assertJsonPath('data.viewer.is_member', false);
        $this->getJson('/api/v1/users/public_player')->assertOk()->assertJsonMissingPath('data.email');
    }

    public function test_guests_cannot_see_drafts_or_personal_lists(): void
    {
        $draft = $this->tournament(status: TournamentStatus::Draft);

        $this->getJson("/api/v1/tournaments/{$draft->slug}")->assertNotFound();
        $this->getJson('/api/v1/tournaments?scope=for_me')->assertUnauthorized();
        $this->getJson('/api/v1/tournaments?scope=mine')->assertUnauthorized();
    }

    public function test_guests_cannot_register_join_or_create(): void
    {
        $tournament = $this->tournament();
        $team = $this->teamWithMembers('padel', 0);

        $this->postJson("/api/v1/tournaments/{$tournament->slug}/registrations")->assertUnauthorized();
        $this->postJson('/api/v1/teams', ['sport_id' => $this->sport('padel')->id, 'name' => 'Guests'])->assertUnauthorized();
        $this->postJson("/api/v1/teams/{$team->slug}/invitations", ['username' => 'x'])->assertUnauthorized();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/users?q=pl')->assertUnauthorized();
    }
}
