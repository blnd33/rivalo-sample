// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'RIVALO';

  @override
  String get tagline => 'Play. Win. Rise.';

  @override
  String get actionContinue => 'Continue';

  @override
  String get actionCancel => 'Cancel';

  @override
  String get actionSave => 'Save';

  @override
  String get actionRetry => 'Try again';

  @override
  String get actionDone => 'Done';

  @override
  String get actionClose => 'Close';

  @override
  String get actionConfirm => 'Confirm';

  @override
  String get actionEdit => 'Edit';

  @override
  String get actionSeeAll => 'See all';

  @override
  String get loading => 'Loading';

  @override
  String get sampleData => 'Sample data';

  @override
  String get comingSoonTitle => 'Coming in a future update';

  @override
  String get errorGenericTitle => 'Something went wrong';

  @override
  String get errorGenericMessage =>
      'Please try again. If the problem continues, contact support.';

  @override
  String get errorOfflineTitle => 'You\'re offline';

  @override
  String get errorOfflineMessage => 'Check your connection and try again.';

  @override
  String get errorRateLimited =>
      'Too many attempts. Wait a minute and try again.';

  @override
  String get errorNotFound => 'This item is no longer available.';

  @override
  String get offlineBanner =>
      'You\'re offline. Some information may be out of date.';

  @override
  String get tabHome => 'Home';

  @override
  String get tabCompete => 'Compete';

  @override
  String get tabRankings => 'Rankings';

  @override
  String get tabNews => 'News';

  @override
  String get tabProfile => 'Profile';

  @override
  String get welcomeHeadline => 'Your game. Your rival.';

  @override
  String get welcomeBody =>
      'Join local tournaments in football, padel, cue sports and e-sports. Points come only from approved RIVALO results.';

  @override
  String get welcomeCreateAccount => 'Create account';

  @override
  String get welcomeSignIn => 'I already have an account';

  @override
  String get welcomeBrowseGuest => 'Browse as guest';

  @override
  String get guestHomeTitle => 'Welcome to RIVALO';

  @override
  String get guestTitle => 'You\'re browsing as a guest';

  @override
  String get guestBody =>
      'Look around tournaments, rankings and player profiles. Create a free account to register, join teams and earn points.';

  @override
  String get guestBenefitRegister => 'Register for tournaments';

  @override
  String get guestBenefitTeams => 'Create and join teams';

  @override
  String get guestBenefitPoints => 'Earn points from approved results';

  @override
  String get guestBenefitProfile => 'Build your public player profile';

  @override
  String get guestLeave => 'Back to welcome screen';

  @override
  String get homeUpcoming => 'Upcoming tournaments';

  @override
  String get accountRequiredTitle => 'Create a free account';

  @override
  String get accountRequiredRegister =>
      'You need an account to register for tournaments.';

  @override
  String get accountRequiredTeams =>
      'You need an account to create or join teams.';

  @override
  String get accountRequiredInbox =>
      'You need an account to receive team invitations.';

  @override
  String get accountRequiredGeneric => 'You need an account to do this.';

  @override
  String get accountRequiredNotNow => 'Not now';

  @override
  String get detailSignUpToRegister => 'Create an account to register';

  @override
  String get signInTitle => 'Sign in';

  @override
  String get loginFieldLabel => 'Email or username';

  @override
  String get passwordLabel => 'Password';

  @override
  String get showPassword => 'Show password';

  @override
  String get hidePassword => 'Hide password';

  @override
  String get forgotPasswordLink => 'Forgot password?';

  @override
  String get signInButton => 'Sign in';

  @override
  String get noAccountPrompt => 'New to RIVALO? Create an account';

  @override
  String get createAccountTitle => 'Create account';

  @override
  String get usernameLabel => 'Username';

  @override
  String get usernameHelper =>
      '3–30 characters: letters, numbers, _ and . This is public.';

  @override
  String get emailLabel => 'Email';

  @override
  String get emailHelper =>
      'Private. Used for sign-in and important account messages.';

  @override
  String get displayNameLabel => 'Display name (optional)';

  @override
  String get displayNameHelper =>
      'Shown on your public profile if you allow it.';

  @override
  String get passwordHelper =>
      'At least 10 characters, with letters and numbers.';

  @override
  String get createAccountButton => 'Create account';

  @override
  String get registerLegalNotice =>
      'Before you sign up, you can read our Terms of use, Privacy policy and Community guidelines. Terms and Privacy are drafts until their legal review is complete.';

  @override
  String get haveAccountPrompt => 'Already have an account? Sign in';

  @override
  String get validationRequired => 'Required';

  @override
  String get validationEmail => 'Enter a valid email address';

  @override
  String get validationUsername => 'Use 3–30 letters, numbers, _ or .';

  @override
  String get validationPassword =>
      'Use at least 10 characters with letters and numbers';

  @override
  String get verifyEmailTitle => 'Check your email';

  @override
  String verifyEmailBody(String email) {
    return 'We sent a verification link to $email. Open it on any device. A verified email is needed to join teams and register for tournaments.';
  }

  @override
  String get verifyResend => 'Resend email';

  @override
  String get verifyResent => 'Verification email sent.';

  @override
  String get verifyCheck => 'I\'ve verified my email';

  @override
  String get verifyLater => 'Do this later';

  @override
  String get verifyStillPending =>
      'Your email isn\'t verified yet. Open the link in the email, then try again.';

  @override
  String get verifyBannerTitle => 'Verify your email';

  @override
  String get verifyBannerBody =>
      'Needed to join teams and register for tournaments.';

  @override
  String get forgotTitle => 'Reset password';

  @override
  String get forgotBody =>
      'Enter your account email. If an account exists, we\'ll send a link to choose a new password.';

  @override
  String get forgotSend => 'Send reset link';

  @override
  String get forgotSent =>
      'If an account exists for that email, a reset link is on its way.';

  @override
  String get onboardingTitle => 'What do you play?';

  @override
  String get onboardingBody =>
      'Choose your sports and games to see relevant tournaments. You can change this anytime.';

  @override
  String get onboardingSave => 'Save and continue';

  @override
  String get onboardingSkip => 'Skip for now';

  @override
  String onboardingSelected(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count selected',
      one: '1 selected',
      zero: 'Nothing selected',
    );
    return '$_temp0';
  }

  @override
  String homeGreeting(String name) {
    return 'Hi, $name';
  }

  @override
  String get homeUpcomingForYou => 'Upcoming for you';

  @override
  String get homeMyRegistrations => 'My registrations';

  @override
  String get homeNoDisciplinesTitle => 'Pick your sports';

  @override
  String get homeNoDisciplinesBody =>
      'Select what you play to see relevant tournaments.';

  @override
  String get homeChooseSports => 'Choose sports';

  @override
  String get homeEmptyUpcomingTitle => 'Nothing scheduled yet';

  @override
  String get homeEmptyUpcomingBody =>
      'New tournaments for your sports will appear here.';

  @override
  String get homeNoRegistrations =>
      'You haven\'t registered for a tournament yet.';

  @override
  String homeInvitations(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count pending invitations',
      one: '1 pending invitation',
    );
    return '$_temp0';
  }

  @override
  String get homeReview => 'Review';

  @override
  String get competeTitle => 'Compete';

  @override
  String get competeTournaments => 'Tournaments';

  @override
  String get competeMyTeams => 'My teams';

  @override
  String get filterAll => 'All';

  @override
  String get tournamentsEmptyTitle => 'No tournaments yet';

  @override
  String get tournamentsEmptyBody =>
      'Published tournaments appear here. Check back soon.';

  @override
  String placesLeft(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count places left',
      one: '1 place left',
      zero: 'Full',
    );
    return '$_temp0';
  }

  @override
  String waitlistCount(int count) {
    return '$count on waitlist';
  }

  @override
  String get free => 'Free';

  @override
  String feeInPerson(String fee) {
    return '$fee · paid in person';
  }

  @override
  String get dateTba => 'Date to be announced';

  @override
  String get statusDraft => 'Draft';

  @override
  String get statusPublished => 'Published';

  @override
  String get statusRegistrationOpen => 'Registration open';

  @override
  String get statusRegistrationClosed => 'Registration closed';

  @override
  String get statusInProgress => 'In progress';

  @override
  String get statusCompleted => 'Completed';

  @override
  String get statusPostponed => 'Postponed';

  @override
  String get statusCancelled => 'Cancelled';

  @override
  String get regIndividual => 'Individual';

  @override
  String get regPair => 'Pair';

  @override
  String get regTeam => 'Team';

  @override
  String get formatSingleElimination => 'Single elimination';

  @override
  String get formatRoundRobin => 'Round robin';

  @override
  String get formatGroupsKnockout => 'Groups + knockout';

  @override
  String get detailStarts => 'Starts';

  @override
  String get detailEnds => 'Ends';

  @override
  String get detailVenue => 'Venue';

  @override
  String get detailOnline => 'Online region';

  @override
  String get detailRegistration => 'Registration';

  @override
  String detailRegistrationCloses(String date) {
    return 'Closes $date';
  }

  @override
  String detailRegistrationOpens(String date) {
    return 'Opens $date';
  }

  @override
  String get detailCheckInCloses => 'Check-in closes';

  @override
  String get detailFormat => 'Format';

  @override
  String get detailEntry => 'Entry';

  @override
  String get detailEntrants => 'Entrants';

  @override
  String detailEntrantsValue(int reserved, int max) {
    return '$reserved of $max places taken';
  }

  @override
  String get detailRoster => 'Roster';

  @override
  String detailRosterValue(int min, int max, int subs) {
    return '$min–$max players, up to $subs substitutes';
  }

  @override
  String get detailAbout => 'About';

  @override
  String get detailRules => 'Rules';

  @override
  String get detailEligibility => 'Eligibility';

  @override
  String get detailPrizes => 'Prizes and deductions';

  @override
  String get detailRulesLocked =>
      'These rules are locked. Any correction is published with a reason.';

  @override
  String get detailPaymentNotice =>
      'Entry fees are paid in person at the venue to authorized staff. There is no payment in the app.';

  @override
  String get detailRankingEligible =>
      'Counts toward rankings once points rules are published';

  @override
  String get detailRankingNotEligible => 'Does not count toward rankings';

  @override
  String get detailFixturesComing =>
      'Fixtures and the bracket will appear here after the draw.';

  @override
  String get detailLobby => 'Lobby details';

  @override
  String get detailTimesLocal => 'Times are shown in your device\'s time zone.';

  @override
  String get detailStatusReason => 'Update from the organizers';

  @override
  String get detailRegister => 'Register';

  @override
  String get detailJoinWaitlist => 'Join waitlist';

  @override
  String get detailRegistrationNotOpen => 'Registration isn\'t open';

  @override
  String get detailVerifyToRegister => 'Verify your email to register';

  @override
  String get detailViewEntrants => 'Entrants';

  @override
  String get myEntryTitle => 'Your registration';

  @override
  String get entryRegistration => 'Registration';

  @override
  String get entryPayment => 'Payment';

  @override
  String get entryCheckIn => 'Check-in';

  @override
  String get entryEligibility => 'Eligibility';

  @override
  String entryWaitlistPosition(int position) {
    return 'Waitlist #$position';
  }

  @override
  String get entryCancel => 'Cancel registration';

  @override
  String get entryCancelConfirmTitle => 'Cancel registration?';

  @override
  String get entryCancelConfirmBody =>
      'Your place will be offered to the next entrant on the waitlist.';

  @override
  String get entryCancelled => 'Registration cancelled.';

  @override
  String get entryRosterLocked => 'Roster locked';

  @override
  String get entryNextStepFree =>
      'Next: check in with staff at the venue before check-in closes.';

  @override
  String entryNextStepPaid(String fee) {
    return 'Next: pay $fee in person and check in with staff before check-in closes.';
  }

  @override
  String get regStatusReserved => 'Reserved';

  @override
  String get regStatusWaitlisted => 'Waitlisted';

  @override
  String get regStatusCancelled => 'Cancelled';

  @override
  String get regStatusWithdrawn => 'Withdrawn';

  @override
  String get regStatusRejected => 'Rejected';

  @override
  String get regStatusNoShow => 'No-show';

  @override
  String get payNotRequired => 'Not required';

  @override
  String get payUnpaid => 'Pay at venue';

  @override
  String get payPaid => 'Paid';

  @override
  String get payRefunded => 'Refunded';

  @override
  String get payPartiallyRefunded => 'Partially refunded';

  @override
  String get checkInNot => 'Not checked in';

  @override
  String get checkInDone => 'Checked in';

  @override
  String get eligPending => 'Pending check-in';

  @override
  String get eligEligible => 'Eligible';

  @override
  String get eligIneligible => 'Not eligible';

  @override
  String get registerTitle => 'Register';

  @override
  String registerIndividualBody(String tournament) {
    return 'You\'re registering as an individual for $tournament.';
  }

  @override
  String get registerChooseTeam => 'Choose your team';

  @override
  String get registerNoTeamsTitle => 'No eligible team';

  @override
  String registerNoTeamsBody(String sport) {
    return 'You need to captain a $sport team to register. Pairs register as two-player teams.';
  }

  @override
  String get registerCreateTeam => 'Create a team';

  @override
  String get registerChoosePlayers => 'Players';

  @override
  String registerPlayersRange(int min, int max) {
    return 'Choose $min–$max players';
  }

  @override
  String registerPlayersExact(int count) {
    return 'Choose $count players';
  }

  @override
  String registerSubstitutes(int count) {
    return 'Substitutes (up to $count)';
  }

  @override
  String registerSelectedCount(int selected) {
    return '$selected selected';
  }

  @override
  String get registerConfirm => 'Confirm registration';

  @override
  String get registerSuccessReserved => 'You\'re in. Your place is reserved.';

  @override
  String get registerSuccessWaitlisted =>
      'The tournament is full. You\'re on the waitlist.';

  @override
  String get registerOnlyAccepted =>
      'Only members who accepted your invitation can be selected.';

  @override
  String get roleCaptain => 'Captain';

  @override
  String get roleMember => 'Member';

  @override
  String get roleSubstitute => 'Substitute';

  @override
  String get rolePlayer => 'Player';

  @override
  String get teamsTitle => 'My teams';

  @override
  String get teamsEmptyTitle => 'No teams yet';

  @override
  String get teamsEmptyBody =>
      'Create a team or accept an invitation to play in team and pair tournaments.';

  @override
  String get teamsCreate => 'Create team';

  @override
  String get teamCreateTitle => 'New team';

  @override
  String get teamNameLabel => 'Team name';

  @override
  String get teamSportLabel => 'Sport';

  @override
  String get teamCreateHelper =>
      'You\'ll be the captain. Pairs, such as padel doubles, are two-player teams.';

  @override
  String get teamMembersHeader => 'Members';

  @override
  String teamMembersCount(int count, int limit) {
    return '$count of $limit';
  }

  @override
  String get teamInvitesHeader => 'Pending invitations';

  @override
  String get teamInvite => 'Invite player';

  @override
  String get teamInviteTitle => 'Invite a player';

  @override
  String get teamInviteSearchLabel => 'Username';

  @override
  String get teamInviteSearchHint => 'Search by username';

  @override
  String get teamInviteAs => 'Invite as';

  @override
  String get teamInviteSend => 'Send invitation';

  @override
  String teamInviteSent(String username) {
    return 'Invitation sent to $username.';
  }

  @override
  String get teamInviteNoResults => 'No players found.';

  @override
  String get teamRevoke => 'Revoke';

  @override
  String get teamRemoveMember => 'Remove from team';

  @override
  String teamRemoveConfirm(String name) {
    return 'Remove $name from the team?';
  }

  @override
  String get teamMakeSub => 'Make substitute';

  @override
  String get teamMakeMember => 'Make regular member';

  @override
  String get teamTransferCaptain => 'Nominate as captain';

  @override
  String teamTransferConfirm(String name) {
    return '$name must accept before becoming captain. You\'ll become a regular member.';
  }

  @override
  String teamTransferPending(String name) {
    return 'Waiting for $name to accept captaincy.';
  }

  @override
  String get teamLeave => 'Leave team';

  @override
  String teamLeaveConfirm(String team) {
    return 'Leave $team? Rosters already submitted to tournaments don\'t change.';
  }

  @override
  String get teamDisband => 'Disband team';

  @override
  String teamDisbandConfirm(String team) {
    return 'Disband $team? All members are released. Tournament history is kept.';
  }

  @override
  String get teamFull => 'This team is full.';

  @override
  String get teamRosterHistoryNote =>
      'Changes here never alter rosters already submitted to tournaments.';

  @override
  String teamMemberActions(String name) {
    return 'Actions for $name';
  }

  @override
  String get inboxTitle => 'Invitations';

  @override
  String get inboxEmptyTitle => 'No pending invitations';

  @override
  String get inboxEmptyBody =>
      'Team invitations and captaincy nominations appear here.';

  @override
  String inboxInvitedBy(String name, String sport) {
    return 'Invited by $name · $sport';
  }

  @override
  String get inboxAccept => 'Accept';

  @override
  String get inboxDecline => 'Decline';

  @override
  String inboxJoined(String team) {
    return 'You joined $team.';
  }

  @override
  String inboxNominationBody(String name, String team) {
    return '$name nominated you as captain of $team.';
  }

  @override
  String get inboxBecomeCaptain => 'Become captain';

  @override
  String get rankingsTitle => 'Rankings';

  @override
  String get rankingsComingBody =>
      'Leaderboards for each discipline arrive once results are approved under published points rules. No points have been awarded yet.';

  @override
  String get rankingsPrinciple =>
      'Points come only from approved RIVALO tournament results. They can\'t be bought, and outside results don\'t count.';

  @override
  String get newsTitle => 'News';

  @override
  String get newsComingBody =>
      'Tournament recaps, announcements and community stories will appear here.';

  @override
  String get profileTitle => 'Profile';

  @override
  String get profileEdit => 'Edit profile';

  @override
  String get profileSports => 'Sports & games';

  @override
  String get profileTeams => 'Teams';

  @override
  String get profileHistory => 'Tournament history';

  @override
  String get profileHistoryEmpty => 'Completed tournaments will appear here.';

  @override
  String get profilePoints => 'Points';

  @override
  String get profilePointsEmpty =>
      'No points yet. Points are awarded only for approved results.';

  @override
  String get profileBadges => 'Achievements';

  @override
  String get profileBadgesEmpty =>
      'No badges yet. Badges come from approved results, final placements and check-ins.';

  @override
  String get profileViewPublic => 'View public profile';

  @override
  String get profileSettings => 'Settings';

  @override
  String profileMemberSince(String date) {
    return 'Member since $date';
  }

  @override
  String get profileHidden => 'Hidden by this player\'s privacy settings.';

  @override
  String get profileNoSports => 'No sports selected yet.';

  @override
  String get publicProfileTitle => 'Player';

  @override
  String get editProfileTitle => 'Edit profile';

  @override
  String get changePhoto => 'Change photo';

  @override
  String get choosePhoto => 'Choose from library';

  @override
  String get takePhoto => 'Take photo';

  @override
  String get removePhoto => 'Remove photo';

  @override
  String get cityLabel => 'City (optional)';

  @override
  String get phoneLabel => 'Phone (private, optional)';

  @override
  String get phoneHelper =>
      'Visible only to event staff for operational contact. Never public.';

  @override
  String get profileSaved => 'Profile saved.';

  @override
  String get photoUpdated => 'Photo updated.';

  @override
  String get settingsTitle => 'Settings';

  @override
  String get settingsAccount => 'Account';

  @override
  String get settingsPrivacy => 'Privacy';

  @override
  String get settingsNotifications => 'Notifications';

  @override
  String get settingsLanguage => 'Language';

  @override
  String get settingsDevices => 'Signed-in devices';

  @override
  String get settingsChangePassword => 'Change password';

  @override
  String get settingsSignOut => 'Sign out';

  @override
  String get settingsSignOutAll => 'Sign out of all devices';

  @override
  String get settingsDeleteAccount => 'Delete account';

  @override
  String get settingsSupport => 'Support';

  @override
  String get settingsSupportContact => 'Contact support';

  @override
  String get settingsSupportUnavailable =>
      'The support contact will be published before launch.';

  @override
  String get settingsAbout => 'About';

  @override
  String get settingsDeveloper => 'Developer';

  @override
  String get settingsDesignGallery => 'Design system gallery';

  @override
  String get signOutConfirm => 'Sign out of RIVALO on this device?';

  @override
  String get signOutAllConfirm =>
      'Sign out on every device, including this one?';

  @override
  String get privacyTitle => 'Privacy';

  @override
  String get privacyShowName => 'Show display name';

  @override
  String get privacyShowNameSub => 'When off, only your username is shown.';

  @override
  String get privacyShowCity => 'Show city';

  @override
  String get privacyShowTeams => 'Show teams';

  @override
  String get privacyShowHistory => 'Show tournament history';

  @override
  String get privacyFooter =>
      'Your email, phone and payment records are always private.';

  @override
  String get notificationsTitle => 'Notifications';

  @override
  String get notifNewTournaments => 'New tournaments for my sports';

  @override
  String get notifTeamInvitations => 'Team invitations';

  @override
  String get notifRegistration => 'Registration updates';

  @override
  String get notifCheckIn => 'Check-in reminders';

  @override
  String get notifMatch => 'Match reminders';

  @override
  String get notifVenue => 'Venue and schedule changes';

  @override
  String get notifResults => 'Results';

  @override
  String get notifPoints => 'Points and achievements';

  @override
  String get notifNews => 'News';

  @override
  String get notificationsFooter =>
      'Everything also arrives in your in-app inbox. These switches control phone notifications only.';

  @override
  String get languageTitle => 'Language';

  @override
  String get languageEnglish => 'English';

  @override
  String get languageArabic => 'العربية';

  @override
  String get languageKurdish => 'Kurdî (Kurmancî)';

  @override
  String get languageKurdishReview =>
      'Kurdish (Kurmanji) translation is a draft pending native-speaker review.';

  @override
  String get languageArabicReview =>
      'Arabic translation is a draft pending native-speaker review.';

  @override
  String get devicesTitle => 'Signed-in devices';

  @override
  String get deviceThis => 'This device';

  @override
  String deviceLastUsed(String date) {
    return 'Last used $date';
  }

  @override
  String get deviceNeverUsed => 'Not used yet';

  @override
  String get deviceSignOut => 'Sign out';

  @override
  String get passwordTitle => 'Change password';

  @override
  String get currentPasswordLabel => 'Current password';

  @override
  String get newPasswordLabel => 'New password';

  @override
  String get passwordChanged =>
      'Password changed. Other devices were signed out.';

  @override
  String get deleteTitle => 'Delete account';

  @override
  String deleteBody(int days) {
    return 'Your account will be deleted after a $days-day grace period. Sign in and cancel anytime before then. Personal data is removed; approved tournament results remain and show \"Deleted player\".';
  }

  @override
  String get deleteReasonLabel => 'Reason (optional)';

  @override
  String get deletePasswordLabel => 'Confirm with your password';

  @override
  String get deleteButton => 'Schedule deletion';

  @override
  String deleteScheduled(String date) {
    return 'Deletion scheduled for $date.';
  }

  @override
  String get deleteCancel => 'Keep my account';

  @override
  String get deleteCancelled =>
      'Deletion cancelled. Your account stays active.';

  @override
  String get galleryTitle => 'Design system';

  @override
  String get checkInShowCode => 'Show check-in code';

  @override
  String get checkInPassTitle => 'Check-in pass';

  @override
  String get checkInPassInstruction =>
      'Show this code at the venue. Staff confirm your identity, record any entry fee in person, and check you in.';

  @override
  String get checkInPassOffline =>
      'Showing the copy saved on this device. Your status may be out of date.';

  @override
  String checkInPassQrLabel(String name) {
    return 'Check-in QR code for $name';
  }

  @override
  String get checkInPassBrightness =>
      'If the code won\'t scan, turn up your screen brightness.';

  @override
  String checkInPassCode(String code) {
    return 'Code $code';
  }

  @override
  String get checkInPassDone => 'You\'re checked in.';

  @override
  String checkedInAt(String time) {
    return 'Checked in at $time';
  }

  @override
  String checkInClosesAt(String time) {
    return 'Check-in closes $time';
  }

  @override
  String get receiptsTitle => 'Receipts';

  @override
  String receiptPayment(String amount, String reference) {
    return 'Paid $amount · Receipt $reference';
  }

  @override
  String receiptRefund(String amount, String reference) {
    return 'Refunded $amount · Receipt $reference';
  }

  @override
  String get featuredTitle => 'Featured';

  @override
  String get carouselLabel => 'Featured';

  @override
  String get homeBrowseBySport => 'Browse by sport';

  @override
  String get homeLatestNews => 'Latest news';

  @override
  String get homeNextUp => 'Your next tournament';

  @override
  String get homeOpenNow => 'Registration open';

  @override
  String get homeOpenNowSubtitle => 'Places are going. Grab yours.';

  @override
  String countdownStartsIn(String time) {
    return 'Starts in $time';
  }

  @override
  String countdownClosesIn(String time) {
    return 'Closes in $time';
  }

  @override
  String get countdownToday => 'Today';

  @override
  String get countdownLive => 'Under way';

  @override
  String durationDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count days',
      one: '1 day',
    );
    return '$_temp0';
  }

  @override
  String durationHours(int count) {
    return '$count h';
  }

  @override
  String durationMinutes(int count) {
    return '$count min';
  }

  @override
  String capacityTaken(int taken, int max) {
    return '$taken of $max places taken';
  }

  @override
  String get capacityFull => 'Full';

  @override
  String get statEntrants => 'Entrants';

  @override
  String get statEntry => 'Entry';

  @override
  String get statFormat => 'Format';

  @override
  String get statStarts => 'Starts';

  @override
  String get newsEmptyTitle => 'No news yet';

  @override
  String get newsEmptyBody =>
      'Announcements and tournament updates will appear here.';

  @override
  String newsReadingTime(int minutes) {
    return '$minutes min read';
  }

  @override
  String get newsOpenTournament => 'View tournament';

  @override
  String get newsCategoryAnnouncement => 'Announcement';

  @override
  String get newsCategoryTournament => 'Tournament';

  @override
  String get newsCategoryResults => 'Results';

  @override
  String get newsCategoryCommunity => 'Community';

  @override
  String get newsCategoryGuide => 'Guide';

  @override
  String get newsArticleTitle => 'Article';

  @override
  String get rankingsPodiumAwaiting => 'Awaiting approved results';

  @override
  String get rankingsHowTitle => 'How rankings work';

  @override
  String get rankingsHowVerifiedTitle => 'Verified results only';

  @override
  String get rankingsHowVerifiedBody =>
      'Points come only from results approved by a referee.';

  @override
  String get rankingsHowHistoryTitle => 'Every point explained';

  @override
  String get rankingsHowHistoryBody =>
      'Your history will show which match each point came from.';

  @override
  String get rankingsHowSeasonTitle => 'Per discipline and season';

  @override
  String get rankingsHowSeasonBody =>
      'Each discipline gets its own leaderboard for the season.';

  @override
  String get rankingsBySport => 'Leaderboards';

  @override
  String get rankingsSportPending => 'No approved results yet';

  @override
  String get artworkNote => 'Illustration';

  @override
  String get profileStatTeams => 'Teams';

  @override
  String get profileStatDisciplines => 'Disciplines';

  @override
  String get profileStatEntries => 'Active entries';

  @override
  String get profileStatPoints => 'Points';

  @override
  String get prizesTitle => 'Prizes';

  @override
  String prizePlace(int place) {
    return 'Place $place';
  }

  @override
  String get prizeEstimated => 'Estimated';

  @override
  String get prizeFinalized => 'Finalized';

  @override
  String get prizeGuaranteed => 'Guaranteed';

  @override
  String get prizeTba => 'To be announced';

  @override
  String get prizeLabelsNote =>
      'Estimated amounts can change. Guaranteed prizes are backed by sponsor money already received.';

  @override
  String get sponsorsTitle => 'Sponsors';

  @override
  String get fixturesTitle => 'Fixtures';

  @override
  String get fixturesEmpty => 'Fixtures appear here after the draw.';

  @override
  String get fixturesBracket => 'Bracket';

  @override
  String get fixturesMatches => 'Matches';

  @override
  String get fixturesTable => 'Table';

  @override
  String fixturesGroup(String name) {
    return 'Group $name';
  }

  @override
  String fixturesRound(int round) {
    return 'Round $round';
  }

  @override
  String get fixturesTbd => 'To be decided';

  @override
  String get fixturesBye => 'Bye';

  @override
  String get fixturesYourMatch => 'Your match';

  @override
  String get fixturesFinalStandings => 'Final standings';

  @override
  String fixturesPointsTable(String table) {
    return 'Ranking points: $table';
  }

  @override
  String get fixturesNoPoints =>
      'No ranking points are awarded in this tournament.';

  @override
  String fixturesAdvance(int count) {
    return 'Top $count go through';
  }

  @override
  String get tableP => 'P';

  @override
  String get tableW => 'W';

  @override
  String get tableD => 'D';

  @override
  String get tableL => 'L';

  @override
  String get tablePts => 'Pts';

  @override
  String get matchTitle => 'Match';

  @override
  String get matchStatusReady => 'Ready to play';

  @override
  String get matchStatusPending => 'Waiting for opponents';

  @override
  String get matchStatusCompleted => 'Final';

  @override
  String get matchStatusCancelled => 'Cancelled';

  @override
  String get matchStatusBye => 'Bye';

  @override
  String get matchLive => 'Result pending';

  @override
  String get matchSubmitResult => 'Submit result';

  @override
  String matchPendingMine(String score) {
    return 'Result sent: $score. Waiting for the referee.';
  }

  @override
  String matchPendingOther(String score) {
    return 'A result was sent: $score. A referee will review it.';
  }

  @override
  String get matchDispute => 'Dispute this result';

  @override
  String get matchDisputeReason => 'What is wrong with the result?';

  @override
  String get matchDisputeSent => 'Dispute sent. A referee will review it.';

  @override
  String get matchDisputeOpen => 'Your dispute is being reviewed by a referee.';

  @override
  String matchDisputeWindow(int hours) {
    return 'You can dispute within $hours hours of approval.';
  }

  @override
  String get matchApprovedNote => 'Approved by a referee.';

  @override
  String get resultOutcome => 'How did it end?';

  @override
  String get outcomeNormal => 'Played';

  @override
  String get outcomeWalkover => 'Walkover';

  @override
  String get outcomeForfeit => 'Forfeit';

  @override
  String get outcomeNoShow => 'No-show';

  @override
  String get resultWinner => 'Winner';

  @override
  String get resultWinnerTiebreak =>
      'Level score? Choose who won the tiebreak.';

  @override
  String get resultUseSets => 'Score by sets';

  @override
  String resultSet(int n) {
    return 'Set $n';
  }

  @override
  String get resultAddSet => 'Add set';

  @override
  String get resultSent => 'Result sent to the referee.';

  @override
  String get resultNote => 'Note (optional)';

  @override
  String get rankingsSeason => 'This season';

  @override
  String get rankingsLifetime => 'All time';

  @override
  String get rankingsEmptyTitle => 'No points yet';

  @override
  String get rankingsEmptyBody =>
      'Points appear once referees approve results in ranking tournaments.';

  @override
  String get rankingsNoTable =>
      'No points table is published for this discipline yet. No points have been awarded.';

  @override
  String rankingsPointsValue(int points) {
    return '$points pts';
  }

  @override
  String rankingsWinsValue(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count wins',
      one: '1 win',
    );
    return '$_temp0';
  }

  @override
  String get rankingsYou => 'You';

  @override
  String get pointsHistoryTitle => 'Points history';

  @override
  String get pointsHistoryEmpty =>
      'No points yet. Points come only from approved results in ranking tournaments.';

  @override
  String get pointsTotal => 'Total points';

  @override
  String get awardMatchWin => 'Match win';

  @override
  String get awardMatchDraw => 'Draw';

  @override
  String get awardMatchLoss => 'Match played';

  @override
  String get awardParticipation => 'Participation';

  @override
  String awardPlace(int place) {
    return 'Place $place';
  }

  @override
  String get awardReversal => 'Correction';

  @override
  String get inboxNotificationsTitle => 'Notifications';

  @override
  String inboxUnread(int count) {
    return '$count unread';
  }

  @override
  String get inboxUnreadLabel => 'Unread';

  @override
  String get inboxMarkAllRead => 'Mark all read';

  @override
  String get timeNow => 'now';

  @override
  String get badgeEarned => 'Earned';

  @override
  String get badgeLocked => 'Locked';

  @override
  String badgeProgress(int current, int target) {
    return '$current/$target';
  }

  @override
  String badgeEarnedOn(String date) {
    return 'Earned $date';
  }

  @override
  String badgesEarnedCount(int earned, int total) {
    return '$earned of $total badges';
  }

  @override
  String get badgesExplainer =>
      'Badges come only from approved results, final placements and venue check-ins.';

  @override
  String get notificationsEmptyTitle => 'You\'re all caught up';

  @override
  String get notificationsEmptyBody =>
      'Invitations, results, reminders and announcements will appear here.';

  @override
  String get settingsSafety => 'Safety';

  @override
  String get policyTerms => 'Terms of use';

  @override
  String get policyPrivacy => 'Privacy policy';

  @override
  String get policyGuidelines => 'Community guidelines';

  @override
  String get policyNotPublishedTitle => 'Not published yet';

  @override
  String get policyNotPublishedBody =>
      'The RIVALO team has not published this page yet. Check back soon.';

  @override
  String get policyPendingTitle => 'Draft: pending legal review';

  @override
  String get policyPendingBody =>
      'This text has not been reviewed by a lawyer yet and may change before RIVALO launches publicly.';

  @override
  String get policyTranslationPending =>
      'Not translated yet. Showing the English text.';

  @override
  String policyVersion(int version, String date) {
    return 'Version $version · $date';
  }

  @override
  String get moreActions => 'More';

  @override
  String get reportPlayerAction => 'Report player';

  @override
  String get reportTeamAction => 'Report team';

  @override
  String get reportAccountRequired =>
      'Create a free account to report players or teams.';

  @override
  String reportPlayerTitle(String name) {
    return 'Report $name';
  }

  @override
  String reportTeamTitle(String name) {
    return 'Report $name';
  }

  @override
  String get reportAppTitle => 'Report a problem or send feedback';

  @override
  String get reportAppIntro =>
      'Tell the RIVALO team what went wrong or what would make the app better. We add your app version automatically.';

  @override
  String get reportPrivateNote =>
      'Reports are private. We never tell the other player who reported them.';

  @override
  String get reportReasonHeader => 'What is wrong?';

  @override
  String get reportReasonOffensiveName => 'Offensive name';

  @override
  String get reportReasonOffensivePhoto => 'Offensive photo or logo';

  @override
  String get reportReasonHarassment => 'Harassment or threats';

  @override
  String get reportReasonCheating => 'Cheating or fake results';

  @override
  String get reportReasonImpersonation => 'Pretending to be someone else';

  @override
  String get reportReasonSpam => 'Spam, scams or betting';

  @override
  String get reportReasonOther => 'Something else';

  @override
  String get reportReasonAppProblem => 'Something isn\'t working';

  @override
  String get reportReasonSuggestion => 'Suggestion';

  @override
  String get reportDetailsLabel => 'Details (optional)';

  @override
  String get reportDetailsLabelRequired => 'Details';

  @override
  String get reportDetailsHint => 'What happened, and when?';

  @override
  String get reportAppHint => 'What were you doing when it happened?';

  @override
  String get reportChooseReason => 'Choose what is wrong.';

  @override
  String get reportDetailsRequired => 'Tell us briefly what happened.';

  @override
  String get reportSend => 'Send report';

  @override
  String get reportSendFeedback => 'Send';

  @override
  String get reportThanksTitle => 'Thanks for telling us';

  @override
  String get reportThanksBody =>
      'Our team will review it. You\'ll get a message in your inbox when they have.';

  @override
  String get reportFeedbackThanks => 'Thanks for your feedback';

  @override
  String get reportFeedbackThanksBody => 'The RIVALO team reads every message.';

  @override
  String blockAction(String name) {
    return 'Block $name';
  }

  @override
  String unblockAction(String name) {
    return 'Unblock $name';
  }

  @override
  String blockConfirmTitle(String name) {
    return 'Block $name?';
  }

  @override
  String get blockConfirmBody =>
      'You won\'t find each other in search and can\'t invite each other to teams. They are not told. You can still play in the same tournaments.';

  @override
  String get blockButton => 'Block';

  @override
  String blockedToast(String name) {
    return '$name is blocked.';
  }

  @override
  String unblockedToast(String name) {
    return '$name is unblocked.';
  }

  @override
  String get blockedNotice => 'You blocked this player.';

  @override
  String get blockedTitle => 'Blocked players';

  @override
  String get blockedEmptyTitle => 'No one blocked';

  @override
  String get blockedExplainer =>
      'Blocked players don\'t see you in search and can\'t invite you to teams. They are not told.';

  @override
  String get unblockButton => 'Unblock';

  @override
  String get tabStore => 'Store';

  @override
  String get storeTitle => 'Store';

  @override
  String get storeFeatured => 'Featured';

  @override
  String get storeCatJerseys => 'Jerseys';

  @override
  String get storeCatApparel => 'Apparel';

  @override
  String get storeCatAccessories => 'Accessories';

  @override
  String get storeCatEquipment => 'Equipment';

  @override
  String get storeEmptyTitle => 'Nothing here yet';

  @override
  String get storeEmptyBody => 'Official RIVALO products will appear here.';

  @override
  String storePriceFrom(String price) {
    return 'From $price';
  }

  @override
  String get storeSoldOut => 'Sold out';

  @override
  String storeOnlyLeft(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Only $count left',
      one: 'Only 1 left',
    );
    return '$_temp0';
  }

  @override
  String get storeChooseSize => 'Choose a size';

  @override
  String get storeOption => 'Option';

  @override
  String get storeAbout => 'About this item';

  @override
  String get storeAddToBag => 'Add to bag';

  @override
  String get storeAddedToBag => 'Added to your bag.';

  @override
  String get storeViewBag => 'View bag';

  @override
  String get storeAccountRequired =>
      'Create a free account to buy from the RIVALO store.';

  @override
  String get storeCashNotice =>
      'Pay in cash when you collect your order or when it is delivered. There is no card payment in the app.';

  @override
  String get quantityLabel => 'Quantity';

  @override
  String quantityFor(String name) {
    return 'Quantity of $name';
  }

  @override
  String get quantityDecrease => 'Fewer';

  @override
  String get quantityIncrease => 'More';

  @override
  String get bagTitle => 'Bag';

  @override
  String bagWithCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Bag, $count items',
      one: 'Bag, 1 item',
    );
    return '$_temp0';
  }

  @override
  String get bagEmptyTitle => 'Your bag is empty';

  @override
  String get bagEmptyBody =>
      'Add jerseys, apparel or equipment from the store.';

  @override
  String get bagBrowse => 'Browse the store';

  @override
  String get bagSubtotal => 'Subtotal';

  @override
  String get bagCheckout => 'Checkout';

  @override
  String get bagFixFirst =>
      'Something in your bag changed. Update or remove it first.';

  @override
  String bagOnlyLeft(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Only $count left: lower the quantity',
      one: 'Only 1 left: lower the quantity',
      zero: 'Sold out',
    );
    return '$_temp0';
  }

  @override
  String get bagUnavailable => 'No longer available';

  @override
  String get bagRemove => 'Remove';

  @override
  String get checkoutTitle => 'Checkout';

  @override
  String get checkoutPickup => 'Pickup';

  @override
  String get checkoutDelivery => 'Delivery';

  @override
  String get checkoutNoDelivery =>
      'Delivery is not available yet. Choose pickup.';

  @override
  String get checkoutPickupWhere => 'Where to collect';

  @override
  String get checkoutPickupFooter =>
      'We tell you in your inbox when your order is ready.';

  @override
  String get checkoutDeliveryArea => 'Delivery area';

  @override
  String get checkoutDeliveryFooter =>
      'We deliver only to these areas. The fee is added to your total.';

  @override
  String get checkoutFree => 'Free';

  @override
  String get checkoutRecipient => 'Recipient name';

  @override
  String get checkoutPhone => 'Phone number';

  @override
  String get checkoutAddress => 'Address';

  @override
  String get checkoutAddressHelper =>
      'Street, building, landmark. Only the store team sees it.';

  @override
  String get checkoutNote => 'Note for the store (optional)';

  @override
  String get checkoutSummary => 'Summary';

  @override
  String get checkoutDeliveryFee => 'Delivery';

  @override
  String get checkoutTotal => 'Total';

  @override
  String get checkoutPayOnPickup => 'Pay in cash on pickup';

  @override
  String get checkoutPayOnDelivery => 'Pay in cash on delivery';

  @override
  String checkoutPayBody(String amount) {
    return 'You pay $amount in cash when you receive your order. Nothing is charged now.';
  }

  @override
  String get checkoutChoosePickup => 'Choose where to collect your order.';

  @override
  String get checkoutChooseArea => 'Choose a delivery area.';

  @override
  String get checkoutDeliveryDetails =>
      'Enter the name, phone number and address for delivery.';

  @override
  String get checkoutPlace => 'Place order';

  @override
  String get ordersTitle => 'My orders';

  @override
  String get ordersEmptyTitle => 'No orders yet';

  @override
  String get ordersEmptyBody => 'Orders you place in the store appear here.';

  @override
  String ordersItems(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count items',
      one: '1 item',
    );
    return '$_temp0';
  }

  @override
  String get orderStatusPlaced => 'Placed';

  @override
  String get orderStatusConfirmed => 'Confirmed';

  @override
  String get orderStatusReady => 'Ready for pickup';

  @override
  String get orderStatusOutForDelivery => 'Out for delivery';

  @override
  String get orderStatusCompleted => 'Completed';

  @override
  String get orderStatusCancelled => 'Cancelled';

  @override
  String get orderPlacedTitle => 'Order placed';

  @override
  String get orderPlacedPickup =>
      'We will tell you in your inbox when it is ready to collect. Pay in cash on pickup.';

  @override
  String get orderPlacedDelivery =>
      'We will tell you in your inbox when it is on its way. Pay in cash on delivery.';

  @override
  String orderPlacedOn(String date) {
    return 'Placed $date';
  }

  @override
  String get orderCancelledByYou => 'You cancelled this order.';

  @override
  String get orderCancelledByStore => 'The store cancelled this order.';

  @override
  String get orderItems => 'Items';

  @override
  String orderReturned(int count) {
    return '$count returned';
  }

  @override
  String get orderPayment => 'Payment';

  @override
  String get orderPaid => 'Paid in cash';

  @override
  String get orderRefunded => 'Refunded';

  @override
  String orderPartlyRefunded(String amount) {
    return 'Paid · $amount refunded';
  }

  @override
  String orderReceipt(String reference) {
    return 'Receipt $reference';
  }

  @override
  String get orderCancel => 'Cancel order';

  @override
  String get orderCancelTitle => 'Cancel this order?';

  @override
  String get orderCancelBody =>
      'The items go back on sale. You can order again later.';

  @override
  String get orderCancelled => 'Order cancelled.';

  @override
  String get notifOrders => 'Store orders';

  @override
  String storeSoldBy(String name) {
    return 'Sold by $name';
  }

  @override
  String get storeOfficialProduct => 'Official RIVALO product';

  @override
  String get storeApprovedSeller => 'Approved seller';

  @override
  String get storeSellerNote =>
      'Sold by an approved seller. RIVALO keeps the stock, hands it over and takes the payment, so pickup, delivery and returns work as for official products.';

  @override
  String get storeShopsTitle => 'Shops';

  @override
  String get storeShopProducts => 'On sale';

  @override
  String get storeShopEmpty => 'Nothing on sale right now';

  @override
  String get storeShopEmptyBody => 'Check back soon.';

  @override
  String get storeShopUnavailableTitle => 'This shop is not available';

  @override
  String get storeShopUnavailableBody => 'It may be paused or closed.';

  @override
  String get sellTitle => 'Sell on RIVALO';

  @override
  String get sellTeaser =>
      'Run a sports shop or make gear? Apply to sell to RIVALO players.';

  @override
  String get sellAccountRequired => 'Create a free account to apply to sell.';

  @override
  String get sellIntro =>
      'Approved shops and makers can sell their products in the RIVALO store.';

  @override
  String get sellHowTitle => 'How it works';

  @override
  String get sellHow1 =>
      'Apply here. RIVALO staff check who you are and sign an agreement with you, including the commission.';

  @override
  String get sellHow2 =>
      'Add your products in the Seller Hub on a computer. Staff review each one before it goes on sale.';

  @override
  String get sellHow3 =>
      'Bring your stock to RIVALO. We hand it to customers, take the cash and pay you what you earned, minus the commission.';

  @override
  String get sellApply => 'Apply to sell';

  @override
  String get sellApplyAgain => 'Apply again';

  @override
  String get sellApplyTitle => 'Apply to sell';

  @override
  String get sellBusinessName => 'Shop or business name';

  @override
  String get sellPhone => 'Phone number';

  @override
  String get sellPhoneHelper => 'Only RIVALO staff see it.';

  @override
  String get sellPhoneInvalid =>
      'Enter a phone number (digits, spaces, + and - only).';

  @override
  String get sellCity => 'City';

  @override
  String get sellCategories => 'What would you sell?';

  @override
  String get sellCategoriesRequired => 'Choose at least one.';

  @override
  String get sellDescription => 'Tell us about your products';

  @override
  String get sellDescriptionHelper =>
      'Brands, what you make, where you sell today.';

  @override
  String get sellDescriptionTooShort => 'Write at least 20 characters.';

  @override
  String get sellLink => 'Shop page or social profile (optional)';

  @override
  String get sellTermsNotice =>
      'Before you apply, read how selling on RIVALO works.';

  @override
  String get sellReadTerms => 'Selling on RIVALO: terms';

  @override
  String get sellSubmit => 'Send application';

  @override
  String get sellSubmitted => 'Application sent.';

  @override
  String get sellPendingPill => 'Under review';

  @override
  String get sellPendingTitle => 'Your application is being reviewed';

  @override
  String get sellPendingBody =>
      'RIVALO staff will contact you about the seller agreement. You will get a message in your inbox when they decide.';

  @override
  String sellSentOn(String date) {
    return 'Sent $date';
  }

  @override
  String get sellWithdraw => 'Withdraw application';

  @override
  String get sellWithdrawConfirm => 'Withdraw your application?';

  @override
  String get sellRejectedTitle => 'Your last application was not approved';

  @override
  String get sellRejectedNoReason =>
      'No reason was given. You can contact support from Settings.';

  @override
  String get sellClosedTitle => 'Selling is not open yet';

  @override
  String get sellClosedBody => 'RIVALO is not accepting new sellers right now.';

  @override
  String sellApprovedTitle(String shop) {
    return 'You sell on RIVALO as $shop';
  }

  @override
  String get sellShopPaused => 'Shop paused';

  @override
  String get sellShopClosed => 'Shop closed';

  @override
  String get sellHubTitle => 'Seller Hub';

  @override
  String get sellHubBody =>
      'Manage your products, sales and statement in the Seller Hub. Sign in on a computer with your RIVALO account and your authenticator app.';

  @override
  String get sellHubAddress => 'Seller Hub address';

  @override
  String get sellCopyAddress => 'Copy address';

  @override
  String get sellCopied => 'Address copied.';

  @override
  String get sellViewShop => 'See your shop in the store';
}
