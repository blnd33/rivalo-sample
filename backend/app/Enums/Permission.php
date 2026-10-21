<?php

namespace App\Enums;

/**
 * Explicit permission names. Roles are bundles of these (see RoleName::permissions()).
 * Policies combine a permission with a scope check (e.g. "assigned to this tournament").
 */
enum Permission: string
{
    use EnumHelpers;

    case AdminAccess = 'admin.access';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case UsersAssignRoles = 'users.assign_roles';

    case CatalogManage = 'catalog.manage';

    case TeamsView = 'teams.view';
    case TeamsModerate = 'teams.moderate';

    case TournamentsCreate = 'tournaments.create';
    case TournamentsManageAll = 'tournaments.manage_all';
    case TournamentsManageAssigned = 'tournaments.manage_assigned';
    case RegistrationsManageAssigned = 'registrations.manage_assigned';

    // Milestone 4
    case CheckInRecord = 'checkin.record';
    case PaymentsRecord = 'payments.record';
    case FinanceManage = 'finance.manage';

    // Milestone 5
    case ResultsReviewAssigned = 'results.review_assigned';
    case PointsManage = 'points.manage';

    // Milestone 6
    case ContentManage = 'content.manage';
    case AnnouncementsSend = 'announcements.send';

    // Milestone 7
    case ModerationManage = 'moderation.manage';
    // Records that a qualified reviewer approved a policy page version. Super admin only.
    case LegalRecord = 'legal.record';

    // Milestone 8
    case StoreManage = 'store.manage';

    // Milestone 9. Sellers use the Seller Hub (/seller), never the admin panel.
    case SellerManageOwn = 'seller.manage_own';
    // Review seller applications and listings, suspend sellers.
    case SellersManage = 'sellers.manage';

    case FeatureFlagsManage = 'feature_flags.manage';
    case AuditView = 'audit.view';
}
