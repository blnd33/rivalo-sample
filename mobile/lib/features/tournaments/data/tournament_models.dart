import '../../../core/utils/json.dart';

class Tournament {
  const Tournament({
    required this.id,
    required this.slug,
    required this.name,
    required this.status,
    required this.sportId,
    required this.sportName,
    this.sportIcon,
    required this.disciplineId,
    required this.disciplineName,
    required this.registrationType,
    required this.format,
    required this.registrationIsOpen,
    required this.minEntrants,
    required this.maxEntrants,
    required this.reserved,
    required this.waitlisted,
    required this.placesLeft,
    required this.entryFeeMinor,
    required this.currency,
    required this.entryFeeFormatted,
    required this.isPaid,
    required this.rankingEligible,
    required this.isSampleData,
    this.coverUrl,
    this.statusReason,
    this.city,
    this.venueName,
    this.venueAddress,
    this.onlineRegion,
    this.startsAt,
    this.endsAt,
    this.registrationOpensAt,
    this.registrationClosesAt,
    this.checkInClosesAt,
    this.description,
    this.eligibilityNotes,
    this.rules,
    this.prizeRules,
    this.thirdPlaceMethod,
    this.rosterMin = 1,
    this.rosterMax = 1,
    this.substitutesMax = 0,
    this.lobbyDetails,
    this.rulesLocked = false,
    this.seasonName,
    this.prizes = const [],
    this.sponsors = const [],
  });

  final int id;
  final String slug;
  final String name;
  final String? coverUrl;
  final String status;
  final String? statusReason;
  final int sportId;
  final String sportName;

  /// Sport icon key (football | padel | cue | controller) for glyphs and cover artwork.
  final String? sportIcon;
  final int disciplineId;
  final String disciplineName;
  final String registrationType;
  final String format;
  final String? city;
  final String? venueName;
  final String? venueAddress;
  final String? onlineRegion;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final DateTime? registrationOpensAt;
  final DateTime? registrationClosesAt;
  final DateTime? checkInClosesAt;
  final bool registrationIsOpen;
  final int minEntrants;
  final int maxEntrants;
  final int reserved;
  final int waitlisted;
  final int placesLeft;
  final int entryFeeMinor;
  final String currency;
  final String entryFeeFormatted;
  final bool isPaid;
  final bool rankingEligible;
  final bool isSampleData;

  // Detail-only
  final String? description;
  final String? eligibilityNotes;
  final String? rules;
  final String? prizeRules;
  final String? thirdPlaceMethod;
  final int rosterMin;
  final int rosterMax;
  final int substitutesMax;
  final String? lobbyDetails;
  final bool rulesLocked;
  final String? seasonName;
  final List<PrizeTier> prizes;
  final List<SponsorRef> sponsors;

  bool get isFull => placesLeft <= 0;
  bool get isTeamBased => registrationType != 'individual';

  factory Tournament.fromJson(Json j) {
    final capacity = j.obj('capacity');
    final fee = j.obj('entry_fee');
    final roster = j.obj('roster');
    return Tournament(
      id: j.integer('id'),
      slug: j.str('slug'),
      name: j.str('name'),
      coverUrl: j.strOrNull('cover_url'),
      status: j.str('status'),
      statusReason: j.strOrNull('status_reason'),
      sportId: j.obj('sport').integer('id'),
      sportName: j.obj('sport').str('name'),
      sportIcon: j.obj('sport').strOrNull('icon'),
      disciplineId: j.obj('discipline').integer('id'),
      disciplineName: j.obj('discipline').str('name'),
      registrationType: j.str('registration_type'),
      format: j.str('format'),
      city: j.strOrNull('city'),
      venueName: j.strOrNull('venue_name'),
      venueAddress: j.strOrNull('venue_address'),
      onlineRegion: j.strOrNull('online_region'),
      startsAt: j.date('starts_at'),
      endsAt: j.date('ends_at'),
      registrationOpensAt: j.date('registration_opens_at'),
      registrationClosesAt: j.date('registration_closes_at'),
      checkInClosesAt: j.date('check_in_closes_at'),
      registrationIsOpen: j.boolean('registration_is_open'),
      minEntrants: capacity.integer('min_entrants'),
      maxEntrants: capacity.integer('max_entrants'),
      reserved: capacity.integer('reserved'),
      waitlisted: capacity.integer('waitlisted'),
      placesLeft: capacity.integer('places_left'),
      entryFeeMinor: fee.integer('amount_minor'),
      currency: fee.str('currency', 'IQD'),
      entryFeeFormatted: fee.str('formatted'),
      isPaid: fee.boolean('is_paid'),
      rankingEligible: j.boolean('ranking_eligible'),
      isSampleData: j.boolean('is_sample_data'),
      description: j.strOrNull('description'),
      eligibilityNotes: j.strOrNull('eligibility_notes'),
      rules: j.strOrNull('rules'),
      prizeRules: j.strOrNull('prize_rules'),
      thirdPlaceMethod: j.strOrNull('third_place_method'),
      rosterMin: roster.integer('min', 1),
      rosterMax: roster.integer('max', 1),
      substitutesMax: roster.integer('substitutes_max'),
      lobbyDetails: j.strOrNull('lobby_details'),
      rulesLocked: j.boolean('rules_locked'),
      seasonName: j.objOrNull('season')?.str('name'),
      prizes: j.list('prizes', PrizeTier.fromJson),
      sponsors: j.list('sponsors', SponsorRef.fromJson),
    );
  }
}

class EntryMember {
  const EntryMember({required this.userId, required this.username, required this.role, this.displayName});

  final int userId;
  final String username;
  final String? displayName;
  final String role;

  factory EntryMember.fromJson(Json j) => EntryMember(
        userId: j.integer('user_id'),
        username: j.str('username'),
        displayName: j.strOrNull('display_name'),
        role: j.str('role'),
      );
}

/// An in-person payment or refund receipt recorded by staff at the venue.
class Receipt {
  const Receipt({required this.kind, required this.formatted, required this.reference, this.recordedAt});

  final String kind; // payment | refund
  final String formatted;
  final String reference;
  final DateTime? recordedAt;

  bool get isRefund => kind == 'refund';

  factory Receipt.fromJson(Json j) =>
      Receipt(kind: j.str('kind'), formatted: j.str('formatted'), reference: j.str('reference'), recordedAt: j.date('recorded_at'));
}

/// A registration with its four independent status tracks.
class TournamentEntry {
  const TournamentEntry({
    required this.id,
    required this.entryType,
    required this.displayName,
    required this.registrationStatus,
    required this.paymentStatus,
    required this.checkInStatus,
    required this.eligibilityStatus,
    required this.rosterLocked,
    required this.members,
    required this.canManage,
    this.receipts = const [],
    this.checkedInAt,
    this.eligibilityReason,
    this.waitlistPosition,
    this.teamSlug,
    this.teamName,
    this.tournamentSlug,
    this.tournamentName,
    this.tournamentStartsAt,
    this.tournamentCoverUrl,
    this.tournamentSportIcon,
    this.tournamentStatus,
    this.disciplineName,
  });

  final int id;
  final String entryType;
  final String displayName;
  final String registrationStatus;
  final String paymentStatus;
  final String checkInStatus;
  final String eligibilityStatus;
  final int? waitlistPosition;
  final bool rosterLocked;
  final List<EntryMember> members;
  final bool canManage;
  final List<Receipt> receipts;
  final DateTime? checkedInAt;
  final String? eligibilityReason;
  final String? teamSlug;
  final String? teamName;
  final String? tournamentSlug;
  final String? tournamentName;
  final DateTime? tournamentStartsAt;
  final String? tournamentCoverUrl;
  final String? tournamentSportIcon;
  final String? tournamentStatus;
  final String? disciplineName;

  bool get isActive => registrationStatus == 'reserved' || registrationStatus == 'waitlisted';
  bool get isCheckedIn => checkInStatus == 'checked_in';
  bool get canShowCheckInCode => registrationStatus == 'reserved' && !isCheckedIn;

  factory TournamentEntry.fromJson(Json j) {
    final t = j.objOrNull('tournament');
    final team = j.objOrNull('team');
    return TournamentEntry(
      id: j.integer('id'),
      entryType: j.str('entry_type'),
      displayName: j.str('display_name'),
      registrationStatus: j.str('registration_status'),
      paymentStatus: j.str('payment_status'),
      checkInStatus: j.str('check_in_status'),
      eligibilityStatus: j.str('eligibility_status'),
      waitlistPosition: j.intOrNull('waitlist_position'),
      rosterLocked: j.boolean('roster_locked'),
      members: j.list('members', EntryMember.fromJson),
      canManage: j.boolean('can_manage'),
      receipts: j.list('payments', Receipt.fromJson),
      checkedInAt: j.date('checked_in_at'),
      eligibilityReason: j.strOrNull('eligibility_reason'),
      teamSlug: team?.str('slug'),
      teamName: team?.str('name'),
      tournamentSlug: t?.str('slug'),
      tournamentName: t?.str('name'),
      tournamentStartsAt: t?.date('starts_at'),
      tournamentCoverUrl: t?.strOrNull('cover_url'),
      tournamentSportIcon: t?.strOrNull('sport_icon'),
      tournamentStatus: t?.str('status'),
      disciplineName: t?.str('discipline'),
    );
  }
}

/// A prize tier. [amountFormatted] is null when the amount is not announced yet (for example a
/// share of the prize pool while the finance rules are still a draft). Never guessed.
class PrizeTier {
  const PrizeTier({required this.place, required this.label, this.title, this.amountFormatted, this.inKind});

  final int place;
  final String? title;

  /// estimated | finalized | guaranteed
  final String label;
  final String? amountFormatted;
  final String? inKind;

  factory PrizeTier.fromJson(Json j) => PrizeTier(
        place: j.integer('place'),
        title: j.strOrNull('title'),
        label: j.str('label', 'estimated'),
        amountFormatted: j.strOrNull('amount_formatted'),
        inKind: j.strOrNull('in_kind'),
      );
}

/// A sponsor who agreed to be shown. The app never receives sponsorship amounts.
class SponsorRef {
  const SponsorRef({required this.name, required this.isSampleData, this.logoUrl, this.websiteUrl});

  final String name;
  final String? logoUrl;
  final String? websiteUrl;
  final bool isSampleData;

  factory SponsorRef.fromJson(Json j) => SponsorRef(
        name: j.str('name'),
        logoUrl: j.strOrNull('logo_url'),
        websiteUrl: j.strOrNull('website_url'),
        isSampleData: j.boolean('is_sample_data'),
      );
}
