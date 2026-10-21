<?php

namespace App\Enums;

/**
 * Global roles. "Team captain" is intentionally NOT a global role: captaincy is a
 * relationship scoped to one team (teams.captain_id) and is checked by TeamPolicy.
 */
enum RoleName: string
{
    use EnumHelpers;

    case Player = 'player';
    case TournamentOrganizer = 'tournament_organizer';
    case Referee = 'referee';
    case ContentEditor = 'content_editor';
    case SupportStaff = 'support_staff';
    case StoreManager = 'store_manager';
    case Seller = 'seller';
    case SuperAdmin = 'super_admin';

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Player => [],
            self::TournamentOrganizer => [
                Permission::AdminAccess,
                Permission::TournamentsManageAssigned,
                Permission::RegistrationsManageAssigned,
                Permission::CheckInRecord,
                Permission::PaymentsRecord,
            ],
            self::Referee => [
                Permission::AdminAccess,
                Permission::ResultsReviewAssigned,
            ],
            self::ContentEditor => [
                Permission::AdminAccess,
                Permission::ContentManage,
                Permission::AnnouncementsSend,
            ],
            self::SupportStaff => [
                Permission::AdminAccess,
                Permission::UsersView,
                Permission::TeamsView,
                Permission::TeamsModerate,
                Permission::ModerationManage,
            ],
            self::StoreManager => [
                Permission::AdminAccess,
                Permission::StoreManage,
                Permission::SellersManage,
            ],
            self::Seller => [
                Permission::SellerManageOwn,
            ],
            self::SuperAdmin => Permission::cases(),
        };
    }

    /** Staff roles must use multi-factor authentication. */
    public function isStaff(): bool
    {
        return in_array(Permission::AdminAccess, $this->permissions(), true);
    }
}
