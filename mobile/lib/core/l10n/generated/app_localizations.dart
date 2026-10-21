import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';
import 'app_localizations_kmr.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'generated/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
    Locale('kmr'),
  ];

  /// No description provided for @appName.
  ///
  /// In en, this message translates to:
  /// **'RIVALO'**
  String get appName;

  /// No description provided for @tagline.
  ///
  /// In en, this message translates to:
  /// **'Play. Win. Rise.'**
  String get tagline;

  /// No description provided for @actionContinue.
  ///
  /// In en, this message translates to:
  /// **'Continue'**
  String get actionContinue;

  /// No description provided for @actionCancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get actionCancel;

  /// No description provided for @actionSave.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get actionSave;

  /// No description provided for @actionRetry.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get actionRetry;

  /// No description provided for @actionDone.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get actionDone;

  /// No description provided for @actionClose.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get actionClose;

  /// No description provided for @actionConfirm.
  ///
  /// In en, this message translates to:
  /// **'Confirm'**
  String get actionConfirm;

  /// No description provided for @actionEdit.
  ///
  /// In en, this message translates to:
  /// **'Edit'**
  String get actionEdit;

  /// No description provided for @actionSeeAll.
  ///
  /// In en, this message translates to:
  /// **'See all'**
  String get actionSeeAll;

  /// No description provided for @loading.
  ///
  /// In en, this message translates to:
  /// **'Loading'**
  String get loading;

  /// No description provided for @sampleData.
  ///
  /// In en, this message translates to:
  /// **'Sample data'**
  String get sampleData;

  /// No description provided for @comingSoonTitle.
  ///
  /// In en, this message translates to:
  /// **'Coming in a future update'**
  String get comingSoonTitle;

  /// No description provided for @errorGenericTitle.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong'**
  String get errorGenericTitle;

  /// No description provided for @errorGenericMessage.
  ///
  /// In en, this message translates to:
  /// **'Please try again. If the problem continues, contact support.'**
  String get errorGenericMessage;

  /// No description provided for @errorOfflineTitle.
  ///
  /// In en, this message translates to:
  /// **'You\'re offline'**
  String get errorOfflineTitle;

  /// No description provided for @errorOfflineMessage.
  ///
  /// In en, this message translates to:
  /// **'Check your connection and try again.'**
  String get errorOfflineMessage;

  /// No description provided for @errorRateLimited.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Wait a minute and try again.'**
  String get errorRateLimited;

  /// No description provided for @errorNotFound.
  ///
  /// In en, this message translates to:
  /// **'This item is no longer available.'**
  String get errorNotFound;

  /// No description provided for @offlineBanner.
  ///
  /// In en, this message translates to:
  /// **'You\'re offline. Some information may be out of date.'**
  String get offlineBanner;

  /// No description provided for @tabHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get tabHome;

  /// No description provided for @tabCompete.
  ///
  /// In en, this message translates to:
  /// **'Compete'**
  String get tabCompete;

  /// No description provided for @tabRankings.
  ///
  /// In en, this message translates to:
  /// **'Rankings'**
  String get tabRankings;

  /// No description provided for @tabNews.
  ///
  /// In en, this message translates to:
  /// **'News'**
  String get tabNews;

  /// No description provided for @tabProfile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get tabProfile;

  /// No description provided for @welcomeHeadline.
  ///
  /// In en, this message translates to:
  /// **'Your game. Your rival.'**
  String get welcomeHeadline;

  /// No description provided for @welcomeBody.
  ///
  /// In en, this message translates to:
  /// **'Join local tournaments in football, padel, cue sports and e-sports. Points come only from approved RIVALO results.'**
  String get welcomeBody;

  /// No description provided for @welcomeCreateAccount.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get welcomeCreateAccount;

  /// No description provided for @welcomeSignIn.
  ///
  /// In en, this message translates to:
  /// **'I already have an account'**
  String get welcomeSignIn;

  /// No description provided for @welcomeBrowseGuest.
  ///
  /// In en, this message translates to:
  /// **'Browse as guest'**
  String get welcomeBrowseGuest;

  /// No description provided for @guestHomeTitle.
  ///
  /// In en, this message translates to:
  /// **'Welcome to RIVALO'**
  String get guestHomeTitle;

  /// No description provided for @guestTitle.
  ///
  /// In en, this message translates to:
  /// **'You\'re browsing as a guest'**
  String get guestTitle;

  /// No description provided for @guestBody.
  ///
  /// In en, this message translates to:
  /// **'Look around tournaments, rankings and player profiles. Create a free account to register, join teams and earn points.'**
  String get guestBody;

  /// No description provided for @guestBenefitRegister.
  ///
  /// In en, this message translates to:
  /// **'Register for tournaments'**
  String get guestBenefitRegister;

  /// No description provided for @guestBenefitTeams.
  ///
  /// In en, this message translates to:
  /// **'Create and join teams'**
  String get guestBenefitTeams;

  /// No description provided for @guestBenefitPoints.
  ///
  /// In en, this message translates to:
  /// **'Earn points from approved results'**
  String get guestBenefitPoints;

  /// No description provided for @guestBenefitProfile.
  ///
  /// In en, this message translates to:
  /// **'Build your public player profile'**
  String get guestBenefitProfile;

  /// No description provided for @guestLeave.
  ///
  /// In en, this message translates to:
  /// **'Back to welcome screen'**
  String get guestLeave;

  /// No description provided for @homeUpcoming.
  ///
  /// In en, this message translates to:
  /// **'Upcoming tournaments'**
  String get homeUpcoming;

  /// No description provided for @accountRequiredTitle.
  ///
  /// In en, this message translates to:
  /// **'Create a free account'**
  String get accountRequiredTitle;

  /// No description provided for @accountRequiredRegister.
  ///
  /// In en, this message translates to:
  /// **'You need an account to register for tournaments.'**
  String get accountRequiredRegister;

  /// No description provided for @accountRequiredTeams.
  ///
  /// In en, this message translates to:
  /// **'You need an account to create or join teams.'**
  String get accountRequiredTeams;

  /// No description provided for @accountRequiredInbox.
  ///
  /// In en, this message translates to:
  /// **'You need an account to receive team invitations.'**
  String get accountRequiredInbox;

  /// No description provided for @accountRequiredGeneric.
  ///
  /// In en, this message translates to:
  /// **'You need an account to do this.'**
  String get accountRequiredGeneric;

  /// No description provided for @accountRequiredNotNow.
  ///
  /// In en, this message translates to:
  /// **'Not now'**
  String get accountRequiredNotNow;

  /// No description provided for @detailSignUpToRegister.
  ///
  /// In en, this message translates to:
  /// **'Create an account to register'**
  String get detailSignUpToRegister;

  /// No description provided for @signInTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signInTitle;

  /// No description provided for @loginFieldLabel.
  ///
  /// In en, this message translates to:
  /// **'Email or username'**
  String get loginFieldLabel;

  /// No description provided for @passwordLabel.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get passwordLabel;

  /// No description provided for @showPassword.
  ///
  /// In en, this message translates to:
  /// **'Show password'**
  String get showPassword;

  /// No description provided for @hidePassword.
  ///
  /// In en, this message translates to:
  /// **'Hide password'**
  String get hidePassword;

  /// No description provided for @forgotPasswordLink.
  ///
  /// In en, this message translates to:
  /// **'Forgot password?'**
  String get forgotPasswordLink;

  /// No description provided for @signInButton.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signInButton;

  /// No description provided for @noAccountPrompt.
  ///
  /// In en, this message translates to:
  /// **'New to RIVALO? Create an account'**
  String get noAccountPrompt;

  /// No description provided for @createAccountTitle.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get createAccountTitle;

  /// No description provided for @usernameLabel.
  ///
  /// In en, this message translates to:
  /// **'Username'**
  String get usernameLabel;

  /// No description provided for @usernameHelper.
  ///
  /// In en, this message translates to:
  /// **'3–30 characters: letters, numbers, _ and . This is public.'**
  String get usernameHelper;

  /// No description provided for @emailLabel.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get emailLabel;

  /// No description provided for @emailHelper.
  ///
  /// In en, this message translates to:
  /// **'Private. Used for sign-in and important account messages.'**
  String get emailHelper;

  /// No description provided for @displayNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Display name (optional)'**
  String get displayNameLabel;

  /// No description provided for @displayNameHelper.
  ///
  /// In en, this message translates to:
  /// **'Shown on your public profile if you allow it.'**
  String get displayNameHelper;

  /// No description provided for @passwordHelper.
  ///
  /// In en, this message translates to:
  /// **'At least 10 characters, with letters and numbers.'**
  String get passwordHelper;

  /// No description provided for @createAccountButton.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get createAccountButton;

  /// No description provided for @registerLegalNotice.
  ///
  /// In en, this message translates to:
  /// **'Before you sign up, you can read our Terms of use, Privacy policy and Community guidelines. Terms and Privacy are drafts until their legal review is complete.'**
  String get registerLegalNotice;

  /// No description provided for @haveAccountPrompt.
  ///
  /// In en, this message translates to:
  /// **'Already have an account? Sign in'**
  String get haveAccountPrompt;

  /// No description provided for @validationRequired.
  ///
  /// In en, this message translates to:
  /// **'Required'**
  String get validationRequired;

  /// No description provided for @validationEmail.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid email address'**
  String get validationEmail;

  /// No description provided for @validationUsername.
  ///
  /// In en, this message translates to:
  /// **'Use 3–30 letters, numbers, _ or .'**
  String get validationUsername;

  /// No description provided for @validationPassword.
  ///
  /// In en, this message translates to:
  /// **'Use at least 10 characters with letters and numbers'**
  String get validationPassword;

  /// No description provided for @verifyEmailTitle.
  ///
  /// In en, this message translates to:
  /// **'Check your email'**
  String get verifyEmailTitle;

  /// No description provided for @verifyEmailBody.
  ///
  /// In en, this message translates to:
  /// **'We sent a verification link to {email}. Open it on any device. A verified email is needed to join teams and register for tournaments.'**
  String verifyEmailBody(String email);

  /// No description provided for @verifyResend.
  ///
  /// In en, this message translates to:
  /// **'Resend email'**
  String get verifyResend;

  /// No description provided for @verifyResent.
  ///
  /// In en, this message translates to:
  /// **'Verification email sent.'**
  String get verifyResent;

  /// No description provided for @verifyCheck.
  ///
  /// In en, this message translates to:
  /// **'I\'ve verified my email'**
  String get verifyCheck;

  /// No description provided for @verifyLater.
  ///
  /// In en, this message translates to:
  /// **'Do this later'**
  String get verifyLater;

  /// No description provided for @verifyStillPending.
  ///
  /// In en, this message translates to:
  /// **'Your email isn\'t verified yet. Open the link in the email, then try again.'**
  String get verifyStillPending;

  /// No description provided for @verifyBannerTitle.
  ///
  /// In en, this message translates to:
  /// **'Verify your email'**
  String get verifyBannerTitle;

  /// No description provided for @verifyBannerBody.
  ///
  /// In en, this message translates to:
  /// **'Needed to join teams and register for tournaments.'**
  String get verifyBannerBody;

  /// No description provided for @forgotTitle.
  ///
  /// In en, this message translates to:
  /// **'Reset password'**
  String get forgotTitle;

  /// No description provided for @forgotBody.
  ///
  /// In en, this message translates to:
  /// **'Enter your account email. If an account exists, we\'ll send a link to choose a new password.'**
  String get forgotBody;

  /// No description provided for @forgotSend.
  ///
  /// In en, this message translates to:
  /// **'Send reset link'**
  String get forgotSend;

  /// No description provided for @forgotSent.
  ///
  /// In en, this message translates to:
  /// **'If an account exists for that email, a reset link is on its way.'**
  String get forgotSent;

  /// No description provided for @onboardingTitle.
  ///
  /// In en, this message translates to:
  /// **'What do you play?'**
  String get onboardingTitle;

  /// No description provided for @onboardingBody.
  ///
  /// In en, this message translates to:
  /// **'Choose your sports and games to see relevant tournaments. You can change this anytime.'**
  String get onboardingBody;

  /// No description provided for @onboardingSave.
  ///
  /// In en, this message translates to:
  /// **'Save and continue'**
  String get onboardingSave;

  /// No description provided for @onboardingSkip.
  ///
  /// In en, this message translates to:
  /// **'Skip for now'**
  String get onboardingSkip;

  /// No description provided for @onboardingSelected.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =0{Nothing selected} =1{1 selected} other{{count} selected}}'**
  String onboardingSelected(int count);

  /// No description provided for @homeGreeting.
  ///
  /// In en, this message translates to:
  /// **'Hi, {name}'**
  String homeGreeting(String name);

  /// No description provided for @homeUpcomingForYou.
  ///
  /// In en, this message translates to:
  /// **'Upcoming for you'**
  String get homeUpcomingForYou;

  /// No description provided for @homeMyRegistrations.
  ///
  /// In en, this message translates to:
  /// **'My registrations'**
  String get homeMyRegistrations;

  /// No description provided for @homeNoDisciplinesTitle.
  ///
  /// In en, this message translates to:
  /// **'Pick your sports'**
  String get homeNoDisciplinesTitle;

  /// No description provided for @homeNoDisciplinesBody.
  ///
  /// In en, this message translates to:
  /// **'Select what you play to see relevant tournaments.'**
  String get homeNoDisciplinesBody;

  /// No description provided for @homeChooseSports.
  ///
  /// In en, this message translates to:
  /// **'Choose sports'**
  String get homeChooseSports;

  /// No description provided for @homeEmptyUpcomingTitle.
  ///
  /// In en, this message translates to:
  /// **'Nothing scheduled yet'**
  String get homeEmptyUpcomingTitle;

  /// No description provided for @homeEmptyUpcomingBody.
  ///
  /// In en, this message translates to:
  /// **'New tournaments for your sports will appear here.'**
  String get homeEmptyUpcomingBody;

  /// No description provided for @homeNoRegistrations.
  ///
  /// In en, this message translates to:
  /// **'You haven\'t registered for a tournament yet.'**
  String get homeNoRegistrations;

  /// No description provided for @homeInvitations.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 pending invitation} other{{count} pending invitations}}'**
  String homeInvitations(int count);

  /// No description provided for @homeReview.
  ///
  /// In en, this message translates to:
  /// **'Review'**
  String get homeReview;

  /// No description provided for @competeTitle.
  ///
  /// In en, this message translates to:
  /// **'Compete'**
  String get competeTitle;

  /// No description provided for @competeTournaments.
  ///
  /// In en, this message translates to:
  /// **'Tournaments'**
  String get competeTournaments;

  /// No description provided for @competeMyTeams.
  ///
  /// In en, this message translates to:
  /// **'My teams'**
  String get competeMyTeams;

  /// No description provided for @filterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get filterAll;

  /// No description provided for @tournamentsEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No tournaments yet'**
  String get tournamentsEmptyTitle;

  /// No description provided for @tournamentsEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Published tournaments appear here. Check back soon.'**
  String get tournamentsEmptyBody;

  /// No description provided for @placesLeft.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =0{Full} =1{1 place left} other{{count} places left}}'**
  String placesLeft(int count);

  /// No description provided for @waitlistCount.
  ///
  /// In en, this message translates to:
  /// **'{count} on waitlist'**
  String waitlistCount(int count);

  /// No description provided for @free.
  ///
  /// In en, this message translates to:
  /// **'Free'**
  String get free;

  /// No description provided for @feeInPerson.
  ///
  /// In en, this message translates to:
  /// **'{fee} · paid in person'**
  String feeInPerson(String fee);

  /// No description provided for @dateTba.
  ///
  /// In en, this message translates to:
  /// **'Date to be announced'**
  String get dateTba;

  /// No description provided for @statusDraft.
  ///
  /// In en, this message translates to:
  /// **'Draft'**
  String get statusDraft;

  /// No description provided for @statusPublished.
  ///
  /// In en, this message translates to:
  /// **'Published'**
  String get statusPublished;

  /// No description provided for @statusRegistrationOpen.
  ///
  /// In en, this message translates to:
  /// **'Registration open'**
  String get statusRegistrationOpen;

  /// No description provided for @statusRegistrationClosed.
  ///
  /// In en, this message translates to:
  /// **'Registration closed'**
  String get statusRegistrationClosed;

  /// No description provided for @statusInProgress.
  ///
  /// In en, this message translates to:
  /// **'In progress'**
  String get statusInProgress;

  /// No description provided for @statusCompleted.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get statusCompleted;

  /// No description provided for @statusPostponed.
  ///
  /// In en, this message translates to:
  /// **'Postponed'**
  String get statusPostponed;

  /// No description provided for @statusCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get statusCancelled;

  /// No description provided for @regIndividual.
  ///
  /// In en, this message translates to:
  /// **'Individual'**
  String get regIndividual;

  /// No description provided for @regPair.
  ///
  /// In en, this message translates to:
  /// **'Pair'**
  String get regPair;

  /// No description provided for @regTeam.
  ///
  /// In en, this message translates to:
  /// **'Team'**
  String get regTeam;

  /// No description provided for @formatSingleElimination.
  ///
  /// In en, this message translates to:
  /// **'Single elimination'**
  String get formatSingleElimination;

  /// No description provided for @formatRoundRobin.
  ///
  /// In en, this message translates to:
  /// **'Round robin'**
  String get formatRoundRobin;

  /// No description provided for @formatGroupsKnockout.
  ///
  /// In en, this message translates to:
  /// **'Groups + knockout'**
  String get formatGroupsKnockout;

  /// No description provided for @detailStarts.
  ///
  /// In en, this message translates to:
  /// **'Starts'**
  String get detailStarts;

  /// No description provided for @detailEnds.
  ///
  /// In en, this message translates to:
  /// **'Ends'**
  String get detailEnds;

  /// No description provided for @detailVenue.
  ///
  /// In en, this message translates to:
  /// **'Venue'**
  String get detailVenue;

  /// No description provided for @detailOnline.
  ///
  /// In en, this message translates to:
  /// **'Online region'**
  String get detailOnline;

  /// No description provided for @detailRegistration.
  ///
  /// In en, this message translates to:
  /// **'Registration'**
  String get detailRegistration;

  /// No description provided for @detailRegistrationCloses.
  ///
  /// In en, this message translates to:
  /// **'Closes {date}'**
  String detailRegistrationCloses(String date);

  /// No description provided for @detailRegistrationOpens.
  ///
  /// In en, this message translates to:
  /// **'Opens {date}'**
  String detailRegistrationOpens(String date);

  /// No description provided for @detailCheckInCloses.
  ///
  /// In en, this message translates to:
  /// **'Check-in closes'**
  String get detailCheckInCloses;

  /// No description provided for @detailFormat.
  ///
  /// In en, this message translates to:
  /// **'Format'**
  String get detailFormat;

  /// No description provided for @detailEntry.
  ///
  /// In en, this message translates to:
  /// **'Entry'**
  String get detailEntry;

  /// No description provided for @detailEntrants.
  ///
  /// In en, this message translates to:
  /// **'Entrants'**
  String get detailEntrants;

  /// No description provided for @detailEntrantsValue.
  ///
  /// In en, this message translates to:
  /// **'{reserved} of {max} places taken'**
  String detailEntrantsValue(int reserved, int max);

  /// No description provided for @detailRoster.
  ///
  /// In en, this message translates to:
  /// **'Roster'**
  String get detailRoster;

  /// No description provided for @detailRosterValue.
  ///
  /// In en, this message translates to:
  /// **'{min}–{max} players, up to {subs} substitutes'**
  String detailRosterValue(int min, int max, int subs);

  /// No description provided for @detailAbout.
  ///
  /// In en, this message translates to:
  /// **'About'**
  String get detailAbout;

  /// No description provided for @detailRules.
  ///
  /// In en, this message translates to:
  /// **'Rules'**
  String get detailRules;

  /// No description provided for @detailEligibility.
  ///
  /// In en, this message translates to:
  /// **'Eligibility'**
  String get detailEligibility;

  /// No description provided for @detailPrizes.
  ///
  /// In en, this message translates to:
  /// **'Prizes and deductions'**
  String get detailPrizes;

  /// No description provided for @detailRulesLocked.
  ///
  /// In en, this message translates to:
  /// **'These rules are locked. Any correction is published with a reason.'**
  String get detailRulesLocked;

  /// No description provided for @detailPaymentNotice.
  ///
  /// In en, this message translates to:
  /// **'Entry fees are paid in person at the venue to authorized staff. There is no payment in the app.'**
  String get detailPaymentNotice;

  /// No description provided for @detailRankingEligible.
  ///
  /// In en, this message translates to:
  /// **'Counts toward rankings once points rules are published'**
  String get detailRankingEligible;

  /// No description provided for @detailRankingNotEligible.
  ///
  /// In en, this message translates to:
  /// **'Does not count toward rankings'**
  String get detailRankingNotEligible;

  /// No description provided for @detailFixturesComing.
  ///
  /// In en, this message translates to:
  /// **'Fixtures and the bracket will appear here after the draw.'**
  String get detailFixturesComing;

  /// No description provided for @detailLobby.
  ///
  /// In en, this message translates to:
  /// **'Lobby details'**
  String get detailLobby;

  /// No description provided for @detailTimesLocal.
  ///
  /// In en, this message translates to:
  /// **'Times are shown in your device\'s time zone.'**
  String get detailTimesLocal;

  /// No description provided for @detailStatusReason.
  ///
  /// In en, this message translates to:
  /// **'Update from the organizers'**
  String get detailStatusReason;

  /// No description provided for @detailRegister.
  ///
  /// In en, this message translates to:
  /// **'Register'**
  String get detailRegister;

  /// No description provided for @detailJoinWaitlist.
  ///
  /// In en, this message translates to:
  /// **'Join waitlist'**
  String get detailJoinWaitlist;

  /// No description provided for @detailRegistrationNotOpen.
  ///
  /// In en, this message translates to:
  /// **'Registration isn\'t open'**
  String get detailRegistrationNotOpen;

  /// No description provided for @detailVerifyToRegister.
  ///
  /// In en, this message translates to:
  /// **'Verify your email to register'**
  String get detailVerifyToRegister;

  /// No description provided for @detailViewEntrants.
  ///
  /// In en, this message translates to:
  /// **'Entrants'**
  String get detailViewEntrants;

  /// No description provided for @myEntryTitle.
  ///
  /// In en, this message translates to:
  /// **'Your registration'**
  String get myEntryTitle;

  /// No description provided for @entryRegistration.
  ///
  /// In en, this message translates to:
  /// **'Registration'**
  String get entryRegistration;

  /// No description provided for @entryPayment.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get entryPayment;

  /// No description provided for @entryCheckIn.
  ///
  /// In en, this message translates to:
  /// **'Check-in'**
  String get entryCheckIn;

  /// No description provided for @entryEligibility.
  ///
  /// In en, this message translates to:
  /// **'Eligibility'**
  String get entryEligibility;

  /// No description provided for @entryWaitlistPosition.
  ///
  /// In en, this message translates to:
  /// **'Waitlist #{position}'**
  String entryWaitlistPosition(int position);

  /// No description provided for @entryCancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel registration'**
  String get entryCancel;

  /// No description provided for @entryCancelConfirmTitle.
  ///
  /// In en, this message translates to:
  /// **'Cancel registration?'**
  String get entryCancelConfirmTitle;

  /// No description provided for @entryCancelConfirmBody.
  ///
  /// In en, this message translates to:
  /// **'Your place will be offered to the next entrant on the waitlist.'**
  String get entryCancelConfirmBody;

  /// No description provided for @entryCancelled.
  ///
  /// In en, this message translates to:
  /// **'Registration cancelled.'**
  String get entryCancelled;

  /// No description provided for @entryRosterLocked.
  ///
  /// In en, this message translates to:
  /// **'Roster locked'**
  String get entryRosterLocked;

  /// No description provided for @entryNextStepFree.
  ///
  /// In en, this message translates to:
  /// **'Next: check in with staff at the venue before check-in closes.'**
  String get entryNextStepFree;

  /// No description provided for @entryNextStepPaid.
  ///
  /// In en, this message translates to:
  /// **'Next: pay {fee} in person and check in with staff before check-in closes.'**
  String entryNextStepPaid(String fee);

  /// No description provided for @regStatusReserved.
  ///
  /// In en, this message translates to:
  /// **'Reserved'**
  String get regStatusReserved;

  /// No description provided for @regStatusWaitlisted.
  ///
  /// In en, this message translates to:
  /// **'Waitlisted'**
  String get regStatusWaitlisted;

  /// No description provided for @regStatusCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get regStatusCancelled;

  /// No description provided for @regStatusWithdrawn.
  ///
  /// In en, this message translates to:
  /// **'Withdrawn'**
  String get regStatusWithdrawn;

  /// No description provided for @regStatusRejected.
  ///
  /// In en, this message translates to:
  /// **'Rejected'**
  String get regStatusRejected;

  /// No description provided for @regStatusNoShow.
  ///
  /// In en, this message translates to:
  /// **'No-show'**
  String get regStatusNoShow;

  /// No description provided for @payNotRequired.
  ///
  /// In en, this message translates to:
  /// **'Not required'**
  String get payNotRequired;

  /// No description provided for @payUnpaid.
  ///
  /// In en, this message translates to:
  /// **'Pay at venue'**
  String get payUnpaid;

  /// No description provided for @payPaid.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get payPaid;

  /// No description provided for @payRefunded.
  ///
  /// In en, this message translates to:
  /// **'Refunded'**
  String get payRefunded;

  /// No description provided for @payPartiallyRefunded.
  ///
  /// In en, this message translates to:
  /// **'Partially refunded'**
  String get payPartiallyRefunded;

  /// No description provided for @checkInNot.
  ///
  /// In en, this message translates to:
  /// **'Not checked in'**
  String get checkInNot;

  /// No description provided for @checkInDone.
  ///
  /// In en, this message translates to:
  /// **'Checked in'**
  String get checkInDone;

  /// No description provided for @eligPending.
  ///
  /// In en, this message translates to:
  /// **'Pending check-in'**
  String get eligPending;

  /// No description provided for @eligEligible.
  ///
  /// In en, this message translates to:
  /// **'Eligible'**
  String get eligEligible;

  /// No description provided for @eligIneligible.
  ///
  /// In en, this message translates to:
  /// **'Not eligible'**
  String get eligIneligible;

  /// No description provided for @registerTitle.
  ///
  /// In en, this message translates to:
  /// **'Register'**
  String get registerTitle;

  /// No description provided for @registerIndividualBody.
  ///
  /// In en, this message translates to:
  /// **'You\'re registering as an individual for {tournament}.'**
  String registerIndividualBody(String tournament);

  /// No description provided for @registerChooseTeam.
  ///
  /// In en, this message translates to:
  /// **'Choose your team'**
  String get registerChooseTeam;

  /// No description provided for @registerNoTeamsTitle.
  ///
  /// In en, this message translates to:
  /// **'No eligible team'**
  String get registerNoTeamsTitle;

  /// No description provided for @registerNoTeamsBody.
  ///
  /// In en, this message translates to:
  /// **'You need to captain a {sport} team to register. Pairs register as two-player teams.'**
  String registerNoTeamsBody(String sport);

  /// No description provided for @registerCreateTeam.
  ///
  /// In en, this message translates to:
  /// **'Create a team'**
  String get registerCreateTeam;

  /// No description provided for @registerChoosePlayers.
  ///
  /// In en, this message translates to:
  /// **'Players'**
  String get registerChoosePlayers;

  /// No description provided for @registerPlayersRange.
  ///
  /// In en, this message translates to:
  /// **'Choose {min}–{max} players'**
  String registerPlayersRange(int min, int max);

  /// No description provided for @registerPlayersExact.
  ///
  /// In en, this message translates to:
  /// **'Choose {count} players'**
  String registerPlayersExact(int count);

  /// No description provided for @registerSubstitutes.
  ///
  /// In en, this message translates to:
  /// **'Substitutes (up to {count})'**
  String registerSubstitutes(int count);

  /// No description provided for @registerSelectedCount.
  ///
  /// In en, this message translates to:
  /// **'{selected} selected'**
  String registerSelectedCount(int selected);

  /// No description provided for @registerConfirm.
  ///
  /// In en, this message translates to:
  /// **'Confirm registration'**
  String get registerConfirm;

  /// No description provided for @registerSuccessReserved.
  ///
  /// In en, this message translates to:
  /// **'You\'re in. Your place is reserved.'**
  String get registerSuccessReserved;

  /// No description provided for @registerSuccessWaitlisted.
  ///
  /// In en, this message translates to:
  /// **'The tournament is full. You\'re on the waitlist.'**
  String get registerSuccessWaitlisted;

  /// No description provided for @registerOnlyAccepted.
  ///
  /// In en, this message translates to:
  /// **'Only members who accepted your invitation can be selected.'**
  String get registerOnlyAccepted;

  /// No description provided for @roleCaptain.
  ///
  /// In en, this message translates to:
  /// **'Captain'**
  String get roleCaptain;

  /// No description provided for @roleMember.
  ///
  /// In en, this message translates to:
  /// **'Member'**
  String get roleMember;

  /// No description provided for @roleSubstitute.
  ///
  /// In en, this message translates to:
  /// **'Substitute'**
  String get roleSubstitute;

  /// No description provided for @rolePlayer.
  ///
  /// In en, this message translates to:
  /// **'Player'**
  String get rolePlayer;

  /// No description provided for @teamsTitle.
  ///
  /// In en, this message translates to:
  /// **'My teams'**
  String get teamsTitle;

  /// No description provided for @teamsEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No teams yet'**
  String get teamsEmptyTitle;

  /// No description provided for @teamsEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Create a team or accept an invitation to play in team and pair tournaments.'**
  String get teamsEmptyBody;

  /// No description provided for @teamsCreate.
  ///
  /// In en, this message translates to:
  /// **'Create team'**
  String get teamsCreate;

  /// No description provided for @teamCreateTitle.
  ///
  /// In en, this message translates to:
  /// **'New team'**
  String get teamCreateTitle;

  /// No description provided for @teamNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Team name'**
  String get teamNameLabel;

  /// No description provided for @teamSportLabel.
  ///
  /// In en, this message translates to:
  /// **'Sport'**
  String get teamSportLabel;

  /// No description provided for @teamCreateHelper.
  ///
  /// In en, this message translates to:
  /// **'You\'ll be the captain. Pairs, such as padel doubles, are two-player teams.'**
  String get teamCreateHelper;

  /// No description provided for @teamMembersHeader.
  ///
  /// In en, this message translates to:
  /// **'Members'**
  String get teamMembersHeader;

  /// No description provided for @teamMembersCount.
  ///
  /// In en, this message translates to:
  /// **'{count} of {limit}'**
  String teamMembersCount(int count, int limit);

  /// No description provided for @teamInvitesHeader.
  ///
  /// In en, this message translates to:
  /// **'Pending invitations'**
  String get teamInvitesHeader;

  /// No description provided for @teamInvite.
  ///
  /// In en, this message translates to:
  /// **'Invite player'**
  String get teamInvite;

  /// No description provided for @teamInviteTitle.
  ///
  /// In en, this message translates to:
  /// **'Invite a player'**
  String get teamInviteTitle;

  /// No description provided for @teamInviteSearchLabel.
  ///
  /// In en, this message translates to:
  /// **'Username'**
  String get teamInviteSearchLabel;

  /// No description provided for @teamInviteSearchHint.
  ///
  /// In en, this message translates to:
  /// **'Search by username'**
  String get teamInviteSearchHint;

  /// No description provided for @teamInviteAs.
  ///
  /// In en, this message translates to:
  /// **'Invite as'**
  String get teamInviteAs;

  /// No description provided for @teamInviteSend.
  ///
  /// In en, this message translates to:
  /// **'Send invitation'**
  String get teamInviteSend;

  /// No description provided for @teamInviteSent.
  ///
  /// In en, this message translates to:
  /// **'Invitation sent to {username}.'**
  String teamInviteSent(String username);

  /// No description provided for @teamInviteNoResults.
  ///
  /// In en, this message translates to:
  /// **'No players found.'**
  String get teamInviteNoResults;

  /// No description provided for @teamRevoke.
  ///
  /// In en, this message translates to:
  /// **'Revoke'**
  String get teamRevoke;

  /// No description provided for @teamRemoveMember.
  ///
  /// In en, this message translates to:
  /// **'Remove from team'**
  String get teamRemoveMember;

  /// No description provided for @teamRemoveConfirm.
  ///
  /// In en, this message translates to:
  /// **'Remove {name} from the team?'**
  String teamRemoveConfirm(String name);

  /// No description provided for @teamMakeSub.
  ///
  /// In en, this message translates to:
  /// **'Make substitute'**
  String get teamMakeSub;

  /// No description provided for @teamMakeMember.
  ///
  /// In en, this message translates to:
  /// **'Make regular member'**
  String get teamMakeMember;

  /// No description provided for @teamTransferCaptain.
  ///
  /// In en, this message translates to:
  /// **'Nominate as captain'**
  String get teamTransferCaptain;

  /// No description provided for @teamTransferConfirm.
  ///
  /// In en, this message translates to:
  /// **'{name} must accept before becoming captain. You\'ll become a regular member.'**
  String teamTransferConfirm(String name);

  /// No description provided for @teamTransferPending.
  ///
  /// In en, this message translates to:
  /// **'Waiting for {name} to accept captaincy.'**
  String teamTransferPending(String name);

  /// No description provided for @teamLeave.
  ///
  /// In en, this message translates to:
  /// **'Leave team'**
  String get teamLeave;

  /// No description provided for @teamLeaveConfirm.
  ///
  /// In en, this message translates to:
  /// **'Leave {team}? Rosters already submitted to tournaments don\'t change.'**
  String teamLeaveConfirm(String team);

  /// No description provided for @teamDisband.
  ///
  /// In en, this message translates to:
  /// **'Disband team'**
  String get teamDisband;

  /// No description provided for @teamDisbandConfirm.
  ///
  /// In en, this message translates to:
  /// **'Disband {team}? All members are released. Tournament history is kept.'**
  String teamDisbandConfirm(String team);

  /// No description provided for @teamFull.
  ///
  /// In en, this message translates to:
  /// **'This team is full.'**
  String get teamFull;

  /// No description provided for @teamRosterHistoryNote.
  ///
  /// In en, this message translates to:
  /// **'Changes here never alter rosters already submitted to tournaments.'**
  String get teamRosterHistoryNote;

  /// No description provided for @teamMemberActions.
  ///
  /// In en, this message translates to:
  /// **'Actions for {name}'**
  String teamMemberActions(String name);

  /// No description provided for @inboxTitle.
  ///
  /// In en, this message translates to:
  /// **'Invitations'**
  String get inboxTitle;

  /// No description provided for @inboxEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No pending invitations'**
  String get inboxEmptyTitle;

  /// No description provided for @inboxEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Team invitations and captaincy nominations appear here.'**
  String get inboxEmptyBody;

  /// No description provided for @inboxInvitedBy.
  ///
  /// In en, this message translates to:
  /// **'Invited by {name} · {sport}'**
  String inboxInvitedBy(String name, String sport);

  /// No description provided for @inboxAccept.
  ///
  /// In en, this message translates to:
  /// **'Accept'**
  String get inboxAccept;

  /// No description provided for @inboxDecline.
  ///
  /// In en, this message translates to:
  /// **'Decline'**
  String get inboxDecline;

  /// No description provided for @inboxJoined.
  ///
  /// In en, this message translates to:
  /// **'You joined {team}.'**
  String inboxJoined(String team);

  /// No description provided for @inboxNominationBody.
  ///
  /// In en, this message translates to:
  /// **'{name} nominated you as captain of {team}.'**
  String inboxNominationBody(String name, String team);

  /// No description provided for @inboxBecomeCaptain.
  ///
  /// In en, this message translates to:
  /// **'Become captain'**
  String get inboxBecomeCaptain;

  /// No description provided for @rankingsTitle.
  ///
  /// In en, this message translates to:
  /// **'Rankings'**
  String get rankingsTitle;

  /// No description provided for @rankingsComingBody.
  ///
  /// In en, this message translates to:
  /// **'Leaderboards for each discipline arrive once results are approved under published points rules. No points have been awarded yet.'**
  String get rankingsComingBody;

  /// No description provided for @rankingsPrinciple.
  ///
  /// In en, this message translates to:
  /// **'Points come only from approved RIVALO tournament results. They can\'t be bought, and outside results don\'t count.'**
  String get rankingsPrinciple;

  /// No description provided for @newsTitle.
  ///
  /// In en, this message translates to:
  /// **'News'**
  String get newsTitle;

  /// No description provided for @newsComingBody.
  ///
  /// In en, this message translates to:
  /// **'Tournament recaps, announcements and community stories will appear here.'**
  String get newsComingBody;

  /// No description provided for @profileTitle.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profileTitle;

  /// No description provided for @profileEdit.
  ///
  /// In en, this message translates to:
  /// **'Edit profile'**
  String get profileEdit;

  /// No description provided for @profileSports.
  ///
  /// In en, this message translates to:
  /// **'Sports & games'**
  String get profileSports;

  /// No description provided for @profileTeams.
  ///
  /// In en, this message translates to:
  /// **'Teams'**
  String get profileTeams;

  /// No description provided for @profileHistory.
  ///
  /// In en, this message translates to:
  /// **'Tournament history'**
  String get profileHistory;

  /// No description provided for @profileHistoryEmpty.
  ///
  /// In en, this message translates to:
  /// **'Completed tournaments will appear here.'**
  String get profileHistoryEmpty;

  /// No description provided for @profilePoints.
  ///
  /// In en, this message translates to:
  /// **'Points'**
  String get profilePoints;

  /// No description provided for @profilePointsEmpty.
  ///
  /// In en, this message translates to:
  /// **'No points yet. Points are awarded only for approved results.'**
  String get profilePointsEmpty;

  /// No description provided for @profileBadges.
  ///
  /// In en, this message translates to:
  /// **'Achievements'**
  String get profileBadges;

  /// No description provided for @profileBadgesEmpty.
  ///
  /// In en, this message translates to:
  /// **'No badges yet. Badges come from approved results, final placements and check-ins.'**
  String get profileBadgesEmpty;

  /// No description provided for @profileViewPublic.
  ///
  /// In en, this message translates to:
  /// **'View public profile'**
  String get profileViewPublic;

  /// No description provided for @profileSettings.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get profileSettings;

  /// No description provided for @profileMemberSince.
  ///
  /// In en, this message translates to:
  /// **'Member since {date}'**
  String profileMemberSince(String date);

  /// No description provided for @profileHidden.
  ///
  /// In en, this message translates to:
  /// **'Hidden by this player\'s privacy settings.'**
  String get profileHidden;

  /// No description provided for @profileNoSports.
  ///
  /// In en, this message translates to:
  /// **'No sports selected yet.'**
  String get profileNoSports;

  /// No description provided for @publicProfileTitle.
  ///
  /// In en, this message translates to:
  /// **'Player'**
  String get publicProfileTitle;

  /// No description provided for @editProfileTitle.
  ///
  /// In en, this message translates to:
  /// **'Edit profile'**
  String get editProfileTitle;

  /// No description provided for @changePhoto.
  ///
  /// In en, this message translates to:
  /// **'Change photo'**
  String get changePhoto;

  /// No description provided for @choosePhoto.
  ///
  /// In en, this message translates to:
  /// **'Choose from library'**
  String get choosePhoto;

  /// No description provided for @takePhoto.
  ///
  /// In en, this message translates to:
  /// **'Take photo'**
  String get takePhoto;

  /// No description provided for @removePhoto.
  ///
  /// In en, this message translates to:
  /// **'Remove photo'**
  String get removePhoto;

  /// No description provided for @cityLabel.
  ///
  /// In en, this message translates to:
  /// **'City (optional)'**
  String get cityLabel;

  /// No description provided for @phoneLabel.
  ///
  /// In en, this message translates to:
  /// **'Phone (private, optional)'**
  String get phoneLabel;

  /// No description provided for @phoneHelper.
  ///
  /// In en, this message translates to:
  /// **'Visible only to event staff for operational contact. Never public.'**
  String get phoneHelper;

  /// No description provided for @profileSaved.
  ///
  /// In en, this message translates to:
  /// **'Profile saved.'**
  String get profileSaved;

  /// No description provided for @photoUpdated.
  ///
  /// In en, this message translates to:
  /// **'Photo updated.'**
  String get photoUpdated;

  /// No description provided for @settingsTitle.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settingsTitle;

  /// No description provided for @settingsAccount.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get settingsAccount;

  /// No description provided for @settingsPrivacy.
  ///
  /// In en, this message translates to:
  /// **'Privacy'**
  String get settingsPrivacy;

  /// No description provided for @settingsNotifications.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get settingsNotifications;

  /// No description provided for @settingsLanguage.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get settingsLanguage;

  /// No description provided for @settingsDevices.
  ///
  /// In en, this message translates to:
  /// **'Signed-in devices'**
  String get settingsDevices;

  /// No description provided for @settingsChangePassword.
  ///
  /// In en, this message translates to:
  /// **'Change password'**
  String get settingsChangePassword;

  /// No description provided for @settingsSignOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get settingsSignOut;

  /// No description provided for @settingsSignOutAll.
  ///
  /// In en, this message translates to:
  /// **'Sign out of all devices'**
  String get settingsSignOutAll;

  /// No description provided for @settingsDeleteAccount.
  ///
  /// In en, this message translates to:
  /// **'Delete account'**
  String get settingsDeleteAccount;

  /// No description provided for @settingsSupport.
  ///
  /// In en, this message translates to:
  /// **'Support'**
  String get settingsSupport;

  /// No description provided for @settingsSupportContact.
  ///
  /// In en, this message translates to:
  /// **'Contact support'**
  String get settingsSupportContact;

  /// No description provided for @settingsSupportUnavailable.
  ///
  /// In en, this message translates to:
  /// **'The support contact will be published before launch.'**
  String get settingsSupportUnavailable;

  /// No description provided for @settingsAbout.
  ///
  /// In en, this message translates to:
  /// **'About'**
  String get settingsAbout;

  /// No description provided for @settingsDeveloper.
  ///
  /// In en, this message translates to:
  /// **'Developer'**
  String get settingsDeveloper;

  /// No description provided for @settingsDesignGallery.
  ///
  /// In en, this message translates to:
  /// **'Design system gallery'**
  String get settingsDesignGallery;

  /// No description provided for @signOutConfirm.
  ///
  /// In en, this message translates to:
  /// **'Sign out of RIVALO on this device?'**
  String get signOutConfirm;

  /// No description provided for @signOutAllConfirm.
  ///
  /// In en, this message translates to:
  /// **'Sign out on every device, including this one?'**
  String get signOutAllConfirm;

  /// No description provided for @privacyTitle.
  ///
  /// In en, this message translates to:
  /// **'Privacy'**
  String get privacyTitle;

  /// No description provided for @privacyShowName.
  ///
  /// In en, this message translates to:
  /// **'Show display name'**
  String get privacyShowName;

  /// No description provided for @privacyShowNameSub.
  ///
  /// In en, this message translates to:
  /// **'When off, only your username is shown.'**
  String get privacyShowNameSub;

  /// No description provided for @privacyShowCity.
  ///
  /// In en, this message translates to:
  /// **'Show city'**
  String get privacyShowCity;

  /// No description provided for @privacyShowTeams.
  ///
  /// In en, this message translates to:
  /// **'Show teams'**
  String get privacyShowTeams;

  /// No description provided for @privacyShowHistory.
  ///
  /// In en, this message translates to:
  /// **'Show tournament history'**
  String get privacyShowHistory;

  /// No description provided for @privacyFooter.
  ///
  /// In en, this message translates to:
  /// **'Your email, phone and payment records are always private.'**
  String get privacyFooter;

  /// No description provided for @notificationsTitle.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notificationsTitle;

  /// No description provided for @notifNewTournaments.
  ///
  /// In en, this message translates to:
  /// **'New tournaments for my sports'**
  String get notifNewTournaments;

  /// No description provided for @notifTeamInvitations.
  ///
  /// In en, this message translates to:
  /// **'Team invitations'**
  String get notifTeamInvitations;

  /// No description provided for @notifRegistration.
  ///
  /// In en, this message translates to:
  /// **'Registration updates'**
  String get notifRegistration;

  /// No description provided for @notifCheckIn.
  ///
  /// In en, this message translates to:
  /// **'Check-in reminders'**
  String get notifCheckIn;

  /// No description provided for @notifMatch.
  ///
  /// In en, this message translates to:
  /// **'Match reminders'**
  String get notifMatch;

  /// No description provided for @notifVenue.
  ///
  /// In en, this message translates to:
  /// **'Venue and schedule changes'**
  String get notifVenue;

  /// No description provided for @notifResults.
  ///
  /// In en, this message translates to:
  /// **'Results'**
  String get notifResults;

  /// No description provided for @notifPoints.
  ///
  /// In en, this message translates to:
  /// **'Points and achievements'**
  String get notifPoints;

  /// No description provided for @notifNews.
  ///
  /// In en, this message translates to:
  /// **'News'**
  String get notifNews;

  /// No description provided for @notificationsFooter.
  ///
  /// In en, this message translates to:
  /// **'Everything also arrives in your in-app inbox. These switches control phone notifications only.'**
  String get notificationsFooter;

  /// No description provided for @languageTitle.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get languageTitle;

  /// No description provided for @languageEnglish.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get languageEnglish;

  /// No description provided for @languageArabic.
  ///
  /// In en, this message translates to:
  /// **'العربية'**
  String get languageArabic;

  /// No description provided for @languageKurdish.
  ///
  /// In en, this message translates to:
  /// **'Kurdî (Kurmancî)'**
  String get languageKurdish;

  /// No description provided for @languageKurdishReview.
  ///
  /// In en, this message translates to:
  /// **'Kurdish (Kurmanji) translation is a draft pending native-speaker review.'**
  String get languageKurdishReview;

  /// No description provided for @languageArabicReview.
  ///
  /// In en, this message translates to:
  /// **'Arabic translation is a draft pending native-speaker review.'**
  String get languageArabicReview;

  /// No description provided for @devicesTitle.
  ///
  /// In en, this message translates to:
  /// **'Signed-in devices'**
  String get devicesTitle;

  /// No description provided for @deviceThis.
  ///
  /// In en, this message translates to:
  /// **'This device'**
  String get deviceThis;

  /// No description provided for @deviceLastUsed.
  ///
  /// In en, this message translates to:
  /// **'Last used {date}'**
  String deviceLastUsed(String date);

  /// No description provided for @deviceNeverUsed.
  ///
  /// In en, this message translates to:
  /// **'Not used yet'**
  String get deviceNeverUsed;

  /// No description provided for @deviceSignOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get deviceSignOut;

  /// No description provided for @passwordTitle.
  ///
  /// In en, this message translates to:
  /// **'Change password'**
  String get passwordTitle;

  /// No description provided for @currentPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'Current password'**
  String get currentPasswordLabel;

  /// No description provided for @newPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'New password'**
  String get newPasswordLabel;

  /// No description provided for @passwordChanged.
  ///
  /// In en, this message translates to:
  /// **'Password changed. Other devices were signed out.'**
  String get passwordChanged;

  /// No description provided for @deleteTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete account'**
  String get deleteTitle;

  /// No description provided for @deleteBody.
  ///
  /// In en, this message translates to:
  /// **'Your account will be deleted after a {days}-day grace period. Sign in and cancel anytime before then. Personal data is removed; approved tournament results remain and show \"Deleted player\".'**
  String deleteBody(int days);

  /// No description provided for @deleteReasonLabel.
  ///
  /// In en, this message translates to:
  /// **'Reason (optional)'**
  String get deleteReasonLabel;

  /// No description provided for @deletePasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'Confirm with your password'**
  String get deletePasswordLabel;

  /// No description provided for @deleteButton.
  ///
  /// In en, this message translates to:
  /// **'Schedule deletion'**
  String get deleteButton;

  /// No description provided for @deleteScheduled.
  ///
  /// In en, this message translates to:
  /// **'Deletion scheduled for {date}.'**
  String deleteScheduled(String date);

  /// No description provided for @deleteCancel.
  ///
  /// In en, this message translates to:
  /// **'Keep my account'**
  String get deleteCancel;

  /// No description provided for @deleteCancelled.
  ///
  /// In en, this message translates to:
  /// **'Deletion cancelled. Your account stays active.'**
  String get deleteCancelled;

  /// No description provided for @galleryTitle.
  ///
  /// In en, this message translates to:
  /// **'Design system'**
  String get galleryTitle;

  /// No description provided for @checkInShowCode.
  ///
  /// In en, this message translates to:
  /// **'Show check-in code'**
  String get checkInShowCode;

  /// No description provided for @checkInPassTitle.
  ///
  /// In en, this message translates to:
  /// **'Check-in pass'**
  String get checkInPassTitle;

  /// No description provided for @checkInPassInstruction.
  ///
  /// In en, this message translates to:
  /// **'Show this code at the venue. Staff confirm your identity, record any entry fee in person, and check you in.'**
  String get checkInPassInstruction;

  /// No description provided for @checkInPassOffline.
  ///
  /// In en, this message translates to:
  /// **'Showing the copy saved on this device. Your status may be out of date.'**
  String get checkInPassOffline;

  /// No description provided for @checkInPassQrLabel.
  ///
  /// In en, this message translates to:
  /// **'Check-in QR code for {name}'**
  String checkInPassQrLabel(String name);

  /// No description provided for @checkInPassBrightness.
  ///
  /// In en, this message translates to:
  /// **'If the code won\'t scan, turn up your screen brightness.'**
  String get checkInPassBrightness;

  /// No description provided for @checkInPassCode.
  ///
  /// In en, this message translates to:
  /// **'Code {code}'**
  String checkInPassCode(String code);

  /// No description provided for @checkInPassDone.
  ///
  /// In en, this message translates to:
  /// **'You\'re checked in.'**
  String get checkInPassDone;

  /// No description provided for @checkedInAt.
  ///
  /// In en, this message translates to:
  /// **'Checked in at {time}'**
  String checkedInAt(String time);

  /// No description provided for @checkInClosesAt.
  ///
  /// In en, this message translates to:
  /// **'Check-in closes {time}'**
  String checkInClosesAt(String time);

  /// No description provided for @receiptsTitle.
  ///
  /// In en, this message translates to:
  /// **'Receipts'**
  String get receiptsTitle;

  /// No description provided for @receiptPayment.
  ///
  /// In en, this message translates to:
  /// **'Paid {amount} · Receipt {reference}'**
  String receiptPayment(String amount, String reference);

  /// No description provided for @receiptRefund.
  ///
  /// In en, this message translates to:
  /// **'Refunded {amount} · Receipt {reference}'**
  String receiptRefund(String amount, String reference);

  /// No description provided for @featuredTitle.
  ///
  /// In en, this message translates to:
  /// **'Featured'**
  String get featuredTitle;

  /// No description provided for @carouselLabel.
  ///
  /// In en, this message translates to:
  /// **'Featured'**
  String get carouselLabel;

  /// No description provided for @homeBrowseBySport.
  ///
  /// In en, this message translates to:
  /// **'Browse by sport'**
  String get homeBrowseBySport;

  /// No description provided for @homeLatestNews.
  ///
  /// In en, this message translates to:
  /// **'Latest news'**
  String get homeLatestNews;

  /// No description provided for @homeNextUp.
  ///
  /// In en, this message translates to:
  /// **'Your next tournament'**
  String get homeNextUp;

  /// No description provided for @homeOpenNow.
  ///
  /// In en, this message translates to:
  /// **'Registration open'**
  String get homeOpenNow;

  /// No description provided for @homeOpenNowSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Places are going. Grab yours.'**
  String get homeOpenNowSubtitle;

  /// No description provided for @countdownStartsIn.
  ///
  /// In en, this message translates to:
  /// **'Starts in {time}'**
  String countdownStartsIn(String time);

  /// No description provided for @countdownClosesIn.
  ///
  /// In en, this message translates to:
  /// **'Closes in {time}'**
  String countdownClosesIn(String time);

  /// No description provided for @countdownToday.
  ///
  /// In en, this message translates to:
  /// **'Today'**
  String get countdownToday;

  /// No description provided for @countdownLive.
  ///
  /// In en, this message translates to:
  /// **'Under way'**
  String get countdownLive;

  /// No description provided for @durationDays.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 day} other{{count} days}}'**
  String durationDays(int count);

  /// No description provided for @durationHours.
  ///
  /// In en, this message translates to:
  /// **'{count} h'**
  String durationHours(int count);

  /// No description provided for @durationMinutes.
  ///
  /// In en, this message translates to:
  /// **'{count} min'**
  String durationMinutes(int count);

  /// No description provided for @capacityTaken.
  ///
  /// In en, this message translates to:
  /// **'{taken} of {max} places taken'**
  String capacityTaken(int taken, int max);

  /// No description provided for @capacityFull.
  ///
  /// In en, this message translates to:
  /// **'Full'**
  String get capacityFull;

  /// No description provided for @statEntrants.
  ///
  /// In en, this message translates to:
  /// **'Entrants'**
  String get statEntrants;

  /// No description provided for @statEntry.
  ///
  /// In en, this message translates to:
  /// **'Entry'**
  String get statEntry;

  /// No description provided for @statFormat.
  ///
  /// In en, this message translates to:
  /// **'Format'**
  String get statFormat;

  /// No description provided for @statStarts.
  ///
  /// In en, this message translates to:
  /// **'Starts'**
  String get statStarts;

  /// No description provided for @newsEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No news yet'**
  String get newsEmptyTitle;

  /// No description provided for @newsEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Announcements and tournament updates will appear here.'**
  String get newsEmptyBody;

  /// No description provided for @newsReadingTime.
  ///
  /// In en, this message translates to:
  /// **'{minutes} min read'**
  String newsReadingTime(int minutes);

  /// No description provided for @newsOpenTournament.
  ///
  /// In en, this message translates to:
  /// **'View tournament'**
  String get newsOpenTournament;

  /// No description provided for @newsCategoryAnnouncement.
  ///
  /// In en, this message translates to:
  /// **'Announcement'**
  String get newsCategoryAnnouncement;

  /// No description provided for @newsCategoryTournament.
  ///
  /// In en, this message translates to:
  /// **'Tournament'**
  String get newsCategoryTournament;

  /// No description provided for @newsCategoryResults.
  ///
  /// In en, this message translates to:
  /// **'Results'**
  String get newsCategoryResults;

  /// No description provided for @newsCategoryCommunity.
  ///
  /// In en, this message translates to:
  /// **'Community'**
  String get newsCategoryCommunity;

  /// No description provided for @newsCategoryGuide.
  ///
  /// In en, this message translates to:
  /// **'Guide'**
  String get newsCategoryGuide;

  /// No description provided for @newsArticleTitle.
  ///
  /// In en, this message translates to:
  /// **'Article'**
  String get newsArticleTitle;

  /// No description provided for @rankingsPodiumAwaiting.
  ///
  /// In en, this message translates to:
  /// **'Awaiting approved results'**
  String get rankingsPodiumAwaiting;

  /// No description provided for @rankingsHowTitle.
  ///
  /// In en, this message translates to:
  /// **'How rankings work'**
  String get rankingsHowTitle;

  /// No description provided for @rankingsHowVerifiedTitle.
  ///
  /// In en, this message translates to:
  /// **'Verified results only'**
  String get rankingsHowVerifiedTitle;

  /// No description provided for @rankingsHowVerifiedBody.
  ///
  /// In en, this message translates to:
  /// **'Points come only from results approved by a referee.'**
  String get rankingsHowVerifiedBody;

  /// No description provided for @rankingsHowHistoryTitle.
  ///
  /// In en, this message translates to:
  /// **'Every point explained'**
  String get rankingsHowHistoryTitle;

  /// No description provided for @rankingsHowHistoryBody.
  ///
  /// In en, this message translates to:
  /// **'Your history will show which match each point came from.'**
  String get rankingsHowHistoryBody;

  /// No description provided for @rankingsHowSeasonTitle.
  ///
  /// In en, this message translates to:
  /// **'Per discipline and season'**
  String get rankingsHowSeasonTitle;

  /// No description provided for @rankingsHowSeasonBody.
  ///
  /// In en, this message translates to:
  /// **'Each discipline gets its own leaderboard for the season.'**
  String get rankingsHowSeasonBody;

  /// No description provided for @rankingsBySport.
  ///
  /// In en, this message translates to:
  /// **'Leaderboards'**
  String get rankingsBySport;

  /// No description provided for @rankingsSportPending.
  ///
  /// In en, this message translates to:
  /// **'No approved results yet'**
  String get rankingsSportPending;

  /// No description provided for @artworkNote.
  ///
  /// In en, this message translates to:
  /// **'Illustration'**
  String get artworkNote;

  /// No description provided for @profileStatTeams.
  ///
  /// In en, this message translates to:
  /// **'Teams'**
  String get profileStatTeams;

  /// No description provided for @profileStatDisciplines.
  ///
  /// In en, this message translates to:
  /// **'Disciplines'**
  String get profileStatDisciplines;

  /// No description provided for @profileStatEntries.
  ///
  /// In en, this message translates to:
  /// **'Active entries'**
  String get profileStatEntries;

  /// No description provided for @profileStatPoints.
  ///
  /// In en, this message translates to:
  /// **'Points'**
  String get profileStatPoints;

  /// No description provided for @prizesTitle.
  ///
  /// In en, this message translates to:
  /// **'Prizes'**
  String get prizesTitle;

  /// No description provided for @prizePlace.
  ///
  /// In en, this message translates to:
  /// **'Place {place}'**
  String prizePlace(int place);

  /// No description provided for @prizeEstimated.
  ///
  /// In en, this message translates to:
  /// **'Estimated'**
  String get prizeEstimated;

  /// No description provided for @prizeFinalized.
  ///
  /// In en, this message translates to:
  /// **'Finalized'**
  String get prizeFinalized;

  /// No description provided for @prizeGuaranteed.
  ///
  /// In en, this message translates to:
  /// **'Guaranteed'**
  String get prizeGuaranteed;

  /// No description provided for @prizeTba.
  ///
  /// In en, this message translates to:
  /// **'To be announced'**
  String get prizeTba;

  /// No description provided for @prizeLabelsNote.
  ///
  /// In en, this message translates to:
  /// **'Estimated amounts can change. Guaranteed prizes are backed by sponsor money already received.'**
  String get prizeLabelsNote;

  /// No description provided for @sponsorsTitle.
  ///
  /// In en, this message translates to:
  /// **'Sponsors'**
  String get sponsorsTitle;

  /// No description provided for @fixturesTitle.
  ///
  /// In en, this message translates to:
  /// **'Fixtures'**
  String get fixturesTitle;

  /// No description provided for @fixturesEmpty.
  ///
  /// In en, this message translates to:
  /// **'Fixtures appear here after the draw.'**
  String get fixturesEmpty;

  /// No description provided for @fixturesBracket.
  ///
  /// In en, this message translates to:
  /// **'Bracket'**
  String get fixturesBracket;

  /// No description provided for @fixturesMatches.
  ///
  /// In en, this message translates to:
  /// **'Matches'**
  String get fixturesMatches;

  /// No description provided for @fixturesTable.
  ///
  /// In en, this message translates to:
  /// **'Table'**
  String get fixturesTable;

  /// No description provided for @fixturesGroup.
  ///
  /// In en, this message translates to:
  /// **'Group {name}'**
  String fixturesGroup(String name);

  /// No description provided for @fixturesRound.
  ///
  /// In en, this message translates to:
  /// **'Round {round}'**
  String fixturesRound(int round);

  /// No description provided for @fixturesTbd.
  ///
  /// In en, this message translates to:
  /// **'To be decided'**
  String get fixturesTbd;

  /// No description provided for @fixturesBye.
  ///
  /// In en, this message translates to:
  /// **'Bye'**
  String get fixturesBye;

  /// No description provided for @fixturesYourMatch.
  ///
  /// In en, this message translates to:
  /// **'Your match'**
  String get fixturesYourMatch;

  /// No description provided for @fixturesFinalStandings.
  ///
  /// In en, this message translates to:
  /// **'Final standings'**
  String get fixturesFinalStandings;

  /// No description provided for @fixturesPointsTable.
  ///
  /// In en, this message translates to:
  /// **'Ranking points: {table}'**
  String fixturesPointsTable(String table);

  /// No description provided for @fixturesNoPoints.
  ///
  /// In en, this message translates to:
  /// **'No ranking points are awarded in this tournament.'**
  String get fixturesNoPoints;

  /// No description provided for @fixturesAdvance.
  ///
  /// In en, this message translates to:
  /// **'Top {count} go through'**
  String fixturesAdvance(int count);

  /// No description provided for @tableP.
  ///
  /// In en, this message translates to:
  /// **'P'**
  String get tableP;

  /// No description provided for @tableW.
  ///
  /// In en, this message translates to:
  /// **'W'**
  String get tableW;

  /// No description provided for @tableD.
  ///
  /// In en, this message translates to:
  /// **'D'**
  String get tableD;

  /// No description provided for @tableL.
  ///
  /// In en, this message translates to:
  /// **'L'**
  String get tableL;

  /// No description provided for @tablePts.
  ///
  /// In en, this message translates to:
  /// **'Pts'**
  String get tablePts;

  /// No description provided for @matchTitle.
  ///
  /// In en, this message translates to:
  /// **'Match'**
  String get matchTitle;

  /// No description provided for @matchStatusReady.
  ///
  /// In en, this message translates to:
  /// **'Ready to play'**
  String get matchStatusReady;

  /// No description provided for @matchStatusPending.
  ///
  /// In en, this message translates to:
  /// **'Waiting for opponents'**
  String get matchStatusPending;

  /// No description provided for @matchStatusCompleted.
  ///
  /// In en, this message translates to:
  /// **'Final'**
  String get matchStatusCompleted;

  /// No description provided for @matchStatusCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get matchStatusCancelled;

  /// No description provided for @matchStatusBye.
  ///
  /// In en, this message translates to:
  /// **'Bye'**
  String get matchStatusBye;

  /// No description provided for @matchLive.
  ///
  /// In en, this message translates to:
  /// **'Result pending'**
  String get matchLive;

  /// No description provided for @matchSubmitResult.
  ///
  /// In en, this message translates to:
  /// **'Submit result'**
  String get matchSubmitResult;

  /// No description provided for @matchPendingMine.
  ///
  /// In en, this message translates to:
  /// **'Result sent: {score}. Waiting for the referee.'**
  String matchPendingMine(String score);

  /// No description provided for @matchPendingOther.
  ///
  /// In en, this message translates to:
  /// **'A result was sent: {score}. A referee will review it.'**
  String matchPendingOther(String score);

  /// No description provided for @matchDispute.
  ///
  /// In en, this message translates to:
  /// **'Dispute this result'**
  String get matchDispute;

  /// No description provided for @matchDisputeReason.
  ///
  /// In en, this message translates to:
  /// **'What is wrong with the result?'**
  String get matchDisputeReason;

  /// No description provided for @matchDisputeSent.
  ///
  /// In en, this message translates to:
  /// **'Dispute sent. A referee will review it.'**
  String get matchDisputeSent;

  /// No description provided for @matchDisputeOpen.
  ///
  /// In en, this message translates to:
  /// **'Your dispute is being reviewed by a referee.'**
  String get matchDisputeOpen;

  /// No description provided for @matchDisputeWindow.
  ///
  /// In en, this message translates to:
  /// **'You can dispute within {hours} hours of approval.'**
  String matchDisputeWindow(int hours);

  /// No description provided for @matchApprovedNote.
  ///
  /// In en, this message translates to:
  /// **'Approved by a referee.'**
  String get matchApprovedNote;

  /// No description provided for @resultOutcome.
  ///
  /// In en, this message translates to:
  /// **'How did it end?'**
  String get resultOutcome;

  /// No description provided for @outcomeNormal.
  ///
  /// In en, this message translates to:
  /// **'Played'**
  String get outcomeNormal;

  /// No description provided for @outcomeWalkover.
  ///
  /// In en, this message translates to:
  /// **'Walkover'**
  String get outcomeWalkover;

  /// No description provided for @outcomeForfeit.
  ///
  /// In en, this message translates to:
  /// **'Forfeit'**
  String get outcomeForfeit;

  /// No description provided for @outcomeNoShow.
  ///
  /// In en, this message translates to:
  /// **'No-show'**
  String get outcomeNoShow;

  /// No description provided for @resultWinner.
  ///
  /// In en, this message translates to:
  /// **'Winner'**
  String get resultWinner;

  /// No description provided for @resultWinnerTiebreak.
  ///
  /// In en, this message translates to:
  /// **'Level score? Choose who won the tiebreak.'**
  String get resultWinnerTiebreak;

  /// No description provided for @resultUseSets.
  ///
  /// In en, this message translates to:
  /// **'Score by sets'**
  String get resultUseSets;

  /// No description provided for @resultSet.
  ///
  /// In en, this message translates to:
  /// **'Set {n}'**
  String resultSet(int n);

  /// No description provided for @resultAddSet.
  ///
  /// In en, this message translates to:
  /// **'Add set'**
  String get resultAddSet;

  /// No description provided for @resultSent.
  ///
  /// In en, this message translates to:
  /// **'Result sent to the referee.'**
  String get resultSent;

  /// No description provided for @resultNote.
  ///
  /// In en, this message translates to:
  /// **'Note (optional)'**
  String get resultNote;

  /// No description provided for @rankingsSeason.
  ///
  /// In en, this message translates to:
  /// **'This season'**
  String get rankingsSeason;

  /// No description provided for @rankingsLifetime.
  ///
  /// In en, this message translates to:
  /// **'All time'**
  String get rankingsLifetime;

  /// No description provided for @rankingsEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No points yet'**
  String get rankingsEmptyTitle;

  /// No description provided for @rankingsEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Points appear once referees approve results in ranking tournaments.'**
  String get rankingsEmptyBody;

  /// No description provided for @rankingsNoTable.
  ///
  /// In en, this message translates to:
  /// **'No points table is published for this discipline yet. No points have been awarded.'**
  String get rankingsNoTable;

  /// No description provided for @rankingsPointsValue.
  ///
  /// In en, this message translates to:
  /// **'{points} pts'**
  String rankingsPointsValue(int points);

  /// No description provided for @rankingsWinsValue.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 win} other{{count} wins}}'**
  String rankingsWinsValue(int count);

  /// No description provided for @rankingsYou.
  ///
  /// In en, this message translates to:
  /// **'You'**
  String get rankingsYou;

  /// No description provided for @pointsHistoryTitle.
  ///
  /// In en, this message translates to:
  /// **'Points history'**
  String get pointsHistoryTitle;

  /// No description provided for @pointsHistoryEmpty.
  ///
  /// In en, this message translates to:
  /// **'No points yet. Points come only from approved results in ranking tournaments.'**
  String get pointsHistoryEmpty;

  /// No description provided for @pointsTotal.
  ///
  /// In en, this message translates to:
  /// **'Total points'**
  String get pointsTotal;

  /// No description provided for @awardMatchWin.
  ///
  /// In en, this message translates to:
  /// **'Match win'**
  String get awardMatchWin;

  /// No description provided for @awardMatchDraw.
  ///
  /// In en, this message translates to:
  /// **'Draw'**
  String get awardMatchDraw;

  /// No description provided for @awardMatchLoss.
  ///
  /// In en, this message translates to:
  /// **'Match played'**
  String get awardMatchLoss;

  /// No description provided for @awardParticipation.
  ///
  /// In en, this message translates to:
  /// **'Participation'**
  String get awardParticipation;

  /// No description provided for @awardPlace.
  ///
  /// In en, this message translates to:
  /// **'Place {place}'**
  String awardPlace(int place);

  /// No description provided for @awardReversal.
  ///
  /// In en, this message translates to:
  /// **'Correction'**
  String get awardReversal;

  /// No description provided for @inboxNotificationsTitle.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get inboxNotificationsTitle;

  /// No description provided for @inboxUnread.
  ///
  /// In en, this message translates to:
  /// **'{count} unread'**
  String inboxUnread(int count);

  /// No description provided for @inboxUnreadLabel.
  ///
  /// In en, this message translates to:
  /// **'Unread'**
  String get inboxUnreadLabel;

  /// No description provided for @inboxMarkAllRead.
  ///
  /// In en, this message translates to:
  /// **'Mark all read'**
  String get inboxMarkAllRead;

  /// No description provided for @timeNow.
  ///
  /// In en, this message translates to:
  /// **'now'**
  String get timeNow;

  /// No description provided for @badgeEarned.
  ///
  /// In en, this message translates to:
  /// **'Earned'**
  String get badgeEarned;

  /// No description provided for @badgeLocked.
  ///
  /// In en, this message translates to:
  /// **'Locked'**
  String get badgeLocked;

  /// No description provided for @badgeProgress.
  ///
  /// In en, this message translates to:
  /// **'{current}/{target}'**
  String badgeProgress(int current, int target);

  /// No description provided for @badgeEarnedOn.
  ///
  /// In en, this message translates to:
  /// **'Earned {date}'**
  String badgeEarnedOn(String date);

  /// No description provided for @badgesEarnedCount.
  ///
  /// In en, this message translates to:
  /// **'{earned} of {total} badges'**
  String badgesEarnedCount(int earned, int total);

  /// No description provided for @badgesExplainer.
  ///
  /// In en, this message translates to:
  /// **'Badges come only from approved results, final placements and venue check-ins.'**
  String get badgesExplainer;

  /// No description provided for @notificationsEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'You\'re all caught up'**
  String get notificationsEmptyTitle;

  /// No description provided for @notificationsEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Invitations, results, reminders and announcements will appear here.'**
  String get notificationsEmptyBody;

  /// No description provided for @settingsSafety.
  ///
  /// In en, this message translates to:
  /// **'Safety'**
  String get settingsSafety;

  /// No description provided for @policyTerms.
  ///
  /// In en, this message translates to:
  /// **'Terms of use'**
  String get policyTerms;

  /// No description provided for @policyPrivacy.
  ///
  /// In en, this message translates to:
  /// **'Privacy policy'**
  String get policyPrivacy;

  /// No description provided for @policyGuidelines.
  ///
  /// In en, this message translates to:
  /// **'Community guidelines'**
  String get policyGuidelines;

  /// No description provided for @policyNotPublishedTitle.
  ///
  /// In en, this message translates to:
  /// **'Not published yet'**
  String get policyNotPublishedTitle;

  /// No description provided for @policyNotPublishedBody.
  ///
  /// In en, this message translates to:
  /// **'The RIVALO team has not published this page yet. Check back soon.'**
  String get policyNotPublishedBody;

  /// No description provided for @policyPendingTitle.
  ///
  /// In en, this message translates to:
  /// **'Draft: pending legal review'**
  String get policyPendingTitle;

  /// No description provided for @policyPendingBody.
  ///
  /// In en, this message translates to:
  /// **'This text has not been reviewed by a lawyer yet and may change before RIVALO launches publicly.'**
  String get policyPendingBody;

  /// No description provided for @policyTranslationPending.
  ///
  /// In en, this message translates to:
  /// **'Not translated yet. Showing the English text.'**
  String get policyTranslationPending;

  /// No description provided for @policyVersion.
  ///
  /// In en, this message translates to:
  /// **'Version {version} · {date}'**
  String policyVersion(int version, String date);

  /// No description provided for @moreActions.
  ///
  /// In en, this message translates to:
  /// **'More'**
  String get moreActions;

  /// No description provided for @reportPlayerAction.
  ///
  /// In en, this message translates to:
  /// **'Report player'**
  String get reportPlayerAction;

  /// No description provided for @reportTeamAction.
  ///
  /// In en, this message translates to:
  /// **'Report team'**
  String get reportTeamAction;

  /// No description provided for @reportAccountRequired.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to report players or teams.'**
  String get reportAccountRequired;

  /// No description provided for @reportPlayerTitle.
  ///
  /// In en, this message translates to:
  /// **'Report {name}'**
  String reportPlayerTitle(String name);

  /// No description provided for @reportTeamTitle.
  ///
  /// In en, this message translates to:
  /// **'Report {name}'**
  String reportTeamTitle(String name);

  /// No description provided for @reportAppTitle.
  ///
  /// In en, this message translates to:
  /// **'Report a problem or send feedback'**
  String get reportAppTitle;

  /// No description provided for @reportAppIntro.
  ///
  /// In en, this message translates to:
  /// **'Tell the RIVALO team what went wrong or what would make the app better. We add your app version automatically.'**
  String get reportAppIntro;

  /// No description provided for @reportPrivateNote.
  ///
  /// In en, this message translates to:
  /// **'Reports are private. We never tell the other player who reported them.'**
  String get reportPrivateNote;

  /// No description provided for @reportReasonHeader.
  ///
  /// In en, this message translates to:
  /// **'What is wrong?'**
  String get reportReasonHeader;

  /// No description provided for @reportReasonOffensiveName.
  ///
  /// In en, this message translates to:
  /// **'Offensive name'**
  String get reportReasonOffensiveName;

  /// No description provided for @reportReasonOffensivePhoto.
  ///
  /// In en, this message translates to:
  /// **'Offensive photo or logo'**
  String get reportReasonOffensivePhoto;

  /// No description provided for @reportReasonHarassment.
  ///
  /// In en, this message translates to:
  /// **'Harassment or threats'**
  String get reportReasonHarassment;

  /// No description provided for @reportReasonCheating.
  ///
  /// In en, this message translates to:
  /// **'Cheating or fake results'**
  String get reportReasonCheating;

  /// No description provided for @reportReasonImpersonation.
  ///
  /// In en, this message translates to:
  /// **'Pretending to be someone else'**
  String get reportReasonImpersonation;

  /// No description provided for @reportReasonSpam.
  ///
  /// In en, this message translates to:
  /// **'Spam, scams or betting'**
  String get reportReasonSpam;

  /// No description provided for @reportReasonOther.
  ///
  /// In en, this message translates to:
  /// **'Something else'**
  String get reportReasonOther;

  /// No description provided for @reportReasonAppProblem.
  ///
  /// In en, this message translates to:
  /// **'Something isn\'t working'**
  String get reportReasonAppProblem;

  /// No description provided for @reportReasonSuggestion.
  ///
  /// In en, this message translates to:
  /// **'Suggestion'**
  String get reportReasonSuggestion;

  /// No description provided for @reportDetailsLabel.
  ///
  /// In en, this message translates to:
  /// **'Details (optional)'**
  String get reportDetailsLabel;

  /// No description provided for @reportDetailsLabelRequired.
  ///
  /// In en, this message translates to:
  /// **'Details'**
  String get reportDetailsLabelRequired;

  /// No description provided for @reportDetailsHint.
  ///
  /// In en, this message translates to:
  /// **'What happened, and when?'**
  String get reportDetailsHint;

  /// No description provided for @reportAppHint.
  ///
  /// In en, this message translates to:
  /// **'What were you doing when it happened?'**
  String get reportAppHint;

  /// No description provided for @reportChooseReason.
  ///
  /// In en, this message translates to:
  /// **'Choose what is wrong.'**
  String get reportChooseReason;

  /// No description provided for @reportDetailsRequired.
  ///
  /// In en, this message translates to:
  /// **'Tell us briefly what happened.'**
  String get reportDetailsRequired;

  /// No description provided for @reportSend.
  ///
  /// In en, this message translates to:
  /// **'Send report'**
  String get reportSend;

  /// No description provided for @reportSendFeedback.
  ///
  /// In en, this message translates to:
  /// **'Send'**
  String get reportSendFeedback;

  /// No description provided for @reportThanksTitle.
  ///
  /// In en, this message translates to:
  /// **'Thanks for telling us'**
  String get reportThanksTitle;

  /// No description provided for @reportThanksBody.
  ///
  /// In en, this message translates to:
  /// **'Our team will review it. You\'ll get a message in your inbox when they have.'**
  String get reportThanksBody;

  /// No description provided for @reportFeedbackThanks.
  ///
  /// In en, this message translates to:
  /// **'Thanks for your feedback'**
  String get reportFeedbackThanks;

  /// No description provided for @reportFeedbackThanksBody.
  ///
  /// In en, this message translates to:
  /// **'The RIVALO team reads every message.'**
  String get reportFeedbackThanksBody;

  /// No description provided for @blockAction.
  ///
  /// In en, this message translates to:
  /// **'Block {name}'**
  String blockAction(String name);

  /// No description provided for @unblockAction.
  ///
  /// In en, this message translates to:
  /// **'Unblock {name}'**
  String unblockAction(String name);

  /// No description provided for @blockConfirmTitle.
  ///
  /// In en, this message translates to:
  /// **'Block {name}?'**
  String blockConfirmTitle(String name);

  /// No description provided for @blockConfirmBody.
  ///
  /// In en, this message translates to:
  /// **'You won\'t find each other in search and can\'t invite each other to teams. They are not told. You can still play in the same tournaments.'**
  String get blockConfirmBody;

  /// No description provided for @blockButton.
  ///
  /// In en, this message translates to:
  /// **'Block'**
  String get blockButton;

  /// No description provided for @blockedToast.
  ///
  /// In en, this message translates to:
  /// **'{name} is blocked.'**
  String blockedToast(String name);

  /// No description provided for @unblockedToast.
  ///
  /// In en, this message translates to:
  /// **'{name} is unblocked.'**
  String unblockedToast(String name);

  /// No description provided for @blockedNotice.
  ///
  /// In en, this message translates to:
  /// **'You blocked this player.'**
  String get blockedNotice;

  /// No description provided for @blockedTitle.
  ///
  /// In en, this message translates to:
  /// **'Blocked players'**
  String get blockedTitle;

  /// No description provided for @blockedEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No one blocked'**
  String get blockedEmptyTitle;

  /// No description provided for @blockedExplainer.
  ///
  /// In en, this message translates to:
  /// **'Blocked players don\'t see you in search and can\'t invite you to teams. They are not told.'**
  String get blockedExplainer;

  /// No description provided for @unblockButton.
  ///
  /// In en, this message translates to:
  /// **'Unblock'**
  String get unblockButton;

  /// No description provided for @tabStore.
  ///
  /// In en, this message translates to:
  /// **'Store'**
  String get tabStore;

  /// No description provided for @storeTitle.
  ///
  /// In en, this message translates to:
  /// **'Store'**
  String get storeTitle;

  /// No description provided for @storeFeatured.
  ///
  /// In en, this message translates to:
  /// **'Featured'**
  String get storeFeatured;

  /// No description provided for @storeCatJerseys.
  ///
  /// In en, this message translates to:
  /// **'Jerseys'**
  String get storeCatJerseys;

  /// No description provided for @storeCatApparel.
  ///
  /// In en, this message translates to:
  /// **'Apparel'**
  String get storeCatApparel;

  /// No description provided for @storeCatAccessories.
  ///
  /// In en, this message translates to:
  /// **'Accessories'**
  String get storeCatAccessories;

  /// No description provided for @storeCatEquipment.
  ///
  /// In en, this message translates to:
  /// **'Equipment'**
  String get storeCatEquipment;

  /// No description provided for @storeEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'Nothing here yet'**
  String get storeEmptyTitle;

  /// No description provided for @storeEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Official RIVALO products will appear here.'**
  String get storeEmptyBody;

  /// No description provided for @storePriceFrom.
  ///
  /// In en, this message translates to:
  /// **'From {price}'**
  String storePriceFrom(String price);

  /// No description provided for @storeSoldOut.
  ///
  /// In en, this message translates to:
  /// **'Sold out'**
  String get storeSoldOut;

  /// No description provided for @storeOnlyLeft.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{Only 1 left} other{Only {count} left}}'**
  String storeOnlyLeft(int count);

  /// No description provided for @storeChooseSize.
  ///
  /// In en, this message translates to:
  /// **'Choose a size'**
  String get storeChooseSize;

  /// No description provided for @storeOption.
  ///
  /// In en, this message translates to:
  /// **'Option'**
  String get storeOption;

  /// No description provided for @storeAbout.
  ///
  /// In en, this message translates to:
  /// **'About this item'**
  String get storeAbout;

  /// No description provided for @storeAddToBag.
  ///
  /// In en, this message translates to:
  /// **'Add to bag'**
  String get storeAddToBag;

  /// No description provided for @storeAddedToBag.
  ///
  /// In en, this message translates to:
  /// **'Added to your bag.'**
  String get storeAddedToBag;

  /// No description provided for @storeViewBag.
  ///
  /// In en, this message translates to:
  /// **'View bag'**
  String get storeViewBag;

  /// No description provided for @storeAccountRequired.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to buy from the RIVALO store.'**
  String get storeAccountRequired;

  /// No description provided for @storeCashNotice.
  ///
  /// In en, this message translates to:
  /// **'Pay in cash when you collect your order or when it is delivered. There is no card payment in the app.'**
  String get storeCashNotice;

  /// No description provided for @quantityLabel.
  ///
  /// In en, this message translates to:
  /// **'Quantity'**
  String get quantityLabel;

  /// No description provided for @quantityFor.
  ///
  /// In en, this message translates to:
  /// **'Quantity of {name}'**
  String quantityFor(String name);

  /// No description provided for @quantityDecrease.
  ///
  /// In en, this message translates to:
  /// **'Fewer'**
  String get quantityDecrease;

  /// No description provided for @quantityIncrease.
  ///
  /// In en, this message translates to:
  /// **'More'**
  String get quantityIncrease;

  /// No description provided for @bagTitle.
  ///
  /// In en, this message translates to:
  /// **'Bag'**
  String get bagTitle;

  /// No description provided for @bagWithCount.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{Bag, 1 item} other{Bag, {count} items}}'**
  String bagWithCount(int count);

  /// No description provided for @bagEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'Your bag is empty'**
  String get bagEmptyTitle;

  /// No description provided for @bagEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Add jerseys, apparel or equipment from the store.'**
  String get bagEmptyBody;

  /// No description provided for @bagBrowse.
  ///
  /// In en, this message translates to:
  /// **'Browse the store'**
  String get bagBrowse;

  /// No description provided for @bagSubtotal.
  ///
  /// In en, this message translates to:
  /// **'Subtotal'**
  String get bagSubtotal;

  /// No description provided for @bagCheckout.
  ///
  /// In en, this message translates to:
  /// **'Checkout'**
  String get bagCheckout;

  /// No description provided for @bagFixFirst.
  ///
  /// In en, this message translates to:
  /// **'Something in your bag changed. Update or remove it first.'**
  String get bagFixFirst;

  /// No description provided for @bagOnlyLeft.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =0{Sold out} =1{Only 1 left: lower the quantity} other{Only {count} left: lower the quantity}}'**
  String bagOnlyLeft(int count);

  /// No description provided for @bagUnavailable.
  ///
  /// In en, this message translates to:
  /// **'No longer available'**
  String get bagUnavailable;

  /// No description provided for @bagRemove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get bagRemove;

  /// No description provided for @checkoutTitle.
  ///
  /// In en, this message translates to:
  /// **'Checkout'**
  String get checkoutTitle;

  /// No description provided for @checkoutPickup.
  ///
  /// In en, this message translates to:
  /// **'Pickup'**
  String get checkoutPickup;

  /// No description provided for @checkoutDelivery.
  ///
  /// In en, this message translates to:
  /// **'Delivery'**
  String get checkoutDelivery;

  /// No description provided for @checkoutNoDelivery.
  ///
  /// In en, this message translates to:
  /// **'Delivery is not available yet. Choose pickup.'**
  String get checkoutNoDelivery;

  /// No description provided for @checkoutPickupWhere.
  ///
  /// In en, this message translates to:
  /// **'Where to collect'**
  String get checkoutPickupWhere;

  /// No description provided for @checkoutPickupFooter.
  ///
  /// In en, this message translates to:
  /// **'We tell you in your inbox when your order is ready.'**
  String get checkoutPickupFooter;

  /// No description provided for @checkoutDeliveryArea.
  ///
  /// In en, this message translates to:
  /// **'Delivery area'**
  String get checkoutDeliveryArea;

  /// No description provided for @checkoutDeliveryFooter.
  ///
  /// In en, this message translates to:
  /// **'We deliver only to these areas. The fee is added to your total.'**
  String get checkoutDeliveryFooter;

  /// No description provided for @checkoutFree.
  ///
  /// In en, this message translates to:
  /// **'Free'**
  String get checkoutFree;

  /// No description provided for @checkoutRecipient.
  ///
  /// In en, this message translates to:
  /// **'Recipient name'**
  String get checkoutRecipient;

  /// No description provided for @checkoutPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone number'**
  String get checkoutPhone;

  /// No description provided for @checkoutAddress.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get checkoutAddress;

  /// No description provided for @checkoutAddressHelper.
  ///
  /// In en, this message translates to:
  /// **'Street, building, landmark. Only the store team sees it.'**
  String get checkoutAddressHelper;

  /// No description provided for @checkoutNote.
  ///
  /// In en, this message translates to:
  /// **'Note for the store (optional)'**
  String get checkoutNote;

  /// No description provided for @checkoutSummary.
  ///
  /// In en, this message translates to:
  /// **'Summary'**
  String get checkoutSummary;

  /// No description provided for @checkoutDeliveryFee.
  ///
  /// In en, this message translates to:
  /// **'Delivery'**
  String get checkoutDeliveryFee;

  /// No description provided for @checkoutTotal.
  ///
  /// In en, this message translates to:
  /// **'Total'**
  String get checkoutTotal;

  /// No description provided for @checkoutPayOnPickup.
  ///
  /// In en, this message translates to:
  /// **'Pay in cash on pickup'**
  String get checkoutPayOnPickup;

  /// No description provided for @checkoutPayOnDelivery.
  ///
  /// In en, this message translates to:
  /// **'Pay in cash on delivery'**
  String get checkoutPayOnDelivery;

  /// No description provided for @checkoutPayBody.
  ///
  /// In en, this message translates to:
  /// **'You pay {amount} in cash when you receive your order. Nothing is charged now.'**
  String checkoutPayBody(String amount);

  /// No description provided for @checkoutChoosePickup.
  ///
  /// In en, this message translates to:
  /// **'Choose where to collect your order.'**
  String get checkoutChoosePickup;

  /// No description provided for @checkoutChooseArea.
  ///
  /// In en, this message translates to:
  /// **'Choose a delivery area.'**
  String get checkoutChooseArea;

  /// No description provided for @checkoutDeliveryDetails.
  ///
  /// In en, this message translates to:
  /// **'Enter the name, phone number and address for delivery.'**
  String get checkoutDeliveryDetails;

  /// No description provided for @checkoutPlace.
  ///
  /// In en, this message translates to:
  /// **'Place order'**
  String get checkoutPlace;

  /// No description provided for @ordersTitle.
  ///
  /// In en, this message translates to:
  /// **'My orders'**
  String get ordersTitle;

  /// No description provided for @ordersEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No orders yet'**
  String get ordersEmptyTitle;

  /// No description provided for @ordersEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Orders you place in the store appear here.'**
  String get ordersEmptyBody;

  /// No description provided for @ordersItems.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 item} other{{count} items}}'**
  String ordersItems(int count);

  /// No description provided for @orderStatusPlaced.
  ///
  /// In en, this message translates to:
  /// **'Placed'**
  String get orderStatusPlaced;

  /// No description provided for @orderStatusConfirmed.
  ///
  /// In en, this message translates to:
  /// **'Confirmed'**
  String get orderStatusConfirmed;

  /// No description provided for @orderStatusReady.
  ///
  /// In en, this message translates to:
  /// **'Ready for pickup'**
  String get orderStatusReady;

  /// No description provided for @orderStatusOutForDelivery.
  ///
  /// In en, this message translates to:
  /// **'Out for delivery'**
  String get orderStatusOutForDelivery;

  /// No description provided for @orderStatusCompleted.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get orderStatusCompleted;

  /// No description provided for @orderStatusCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get orderStatusCancelled;

  /// No description provided for @orderPlacedTitle.
  ///
  /// In en, this message translates to:
  /// **'Order placed'**
  String get orderPlacedTitle;

  /// No description provided for @orderPlacedPickup.
  ///
  /// In en, this message translates to:
  /// **'We will tell you in your inbox when it is ready to collect. Pay in cash on pickup.'**
  String get orderPlacedPickup;

  /// No description provided for @orderPlacedDelivery.
  ///
  /// In en, this message translates to:
  /// **'We will tell you in your inbox when it is on its way. Pay in cash on delivery.'**
  String get orderPlacedDelivery;

  /// No description provided for @orderPlacedOn.
  ///
  /// In en, this message translates to:
  /// **'Placed {date}'**
  String orderPlacedOn(String date);

  /// No description provided for @orderCancelledByYou.
  ///
  /// In en, this message translates to:
  /// **'You cancelled this order.'**
  String get orderCancelledByYou;

  /// No description provided for @orderCancelledByStore.
  ///
  /// In en, this message translates to:
  /// **'The store cancelled this order.'**
  String get orderCancelledByStore;

  /// No description provided for @orderItems.
  ///
  /// In en, this message translates to:
  /// **'Items'**
  String get orderItems;

  /// No description provided for @orderReturned.
  ///
  /// In en, this message translates to:
  /// **'{count} returned'**
  String orderReturned(int count);

  /// No description provided for @orderPayment.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get orderPayment;

  /// No description provided for @orderPaid.
  ///
  /// In en, this message translates to:
  /// **'Paid in cash'**
  String get orderPaid;

  /// No description provided for @orderRefunded.
  ///
  /// In en, this message translates to:
  /// **'Refunded'**
  String get orderRefunded;

  /// No description provided for @orderPartlyRefunded.
  ///
  /// In en, this message translates to:
  /// **'Paid · {amount} refunded'**
  String orderPartlyRefunded(String amount);

  /// No description provided for @orderReceipt.
  ///
  /// In en, this message translates to:
  /// **'Receipt {reference}'**
  String orderReceipt(String reference);

  /// No description provided for @orderCancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel order'**
  String get orderCancel;

  /// No description provided for @orderCancelTitle.
  ///
  /// In en, this message translates to:
  /// **'Cancel this order?'**
  String get orderCancelTitle;

  /// No description provided for @orderCancelBody.
  ///
  /// In en, this message translates to:
  /// **'The items go back on sale. You can order again later.'**
  String get orderCancelBody;

  /// No description provided for @orderCancelled.
  ///
  /// In en, this message translates to:
  /// **'Order cancelled.'**
  String get orderCancelled;

  /// No description provided for @notifOrders.
  ///
  /// In en, this message translates to:
  /// **'Store orders'**
  String get notifOrders;

  /// No description provided for @storeSoldBy.
  ///
  /// In en, this message translates to:
  /// **'Sold by {name}'**
  String storeSoldBy(String name);

  /// No description provided for @storeOfficialProduct.
  ///
  /// In en, this message translates to:
  /// **'Official RIVALO product'**
  String get storeOfficialProduct;

  /// No description provided for @storeApprovedSeller.
  ///
  /// In en, this message translates to:
  /// **'Approved seller'**
  String get storeApprovedSeller;

  /// No description provided for @storeSellerNote.
  ///
  /// In en, this message translates to:
  /// **'Sold by an approved seller. RIVALO keeps the stock, hands it over and takes the payment, so pickup, delivery and returns work as for official products.'**
  String get storeSellerNote;

  /// No description provided for @storeShopsTitle.
  ///
  /// In en, this message translates to:
  /// **'Shops'**
  String get storeShopsTitle;

  /// No description provided for @storeShopProducts.
  ///
  /// In en, this message translates to:
  /// **'On sale'**
  String get storeShopProducts;

  /// No description provided for @storeShopEmpty.
  ///
  /// In en, this message translates to:
  /// **'Nothing on sale right now'**
  String get storeShopEmpty;

  /// No description provided for @storeShopEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Check back soon.'**
  String get storeShopEmptyBody;

  /// No description provided for @storeShopUnavailableTitle.
  ///
  /// In en, this message translates to:
  /// **'This shop is not available'**
  String get storeShopUnavailableTitle;

  /// No description provided for @storeShopUnavailableBody.
  ///
  /// In en, this message translates to:
  /// **'It may be paused or closed.'**
  String get storeShopUnavailableBody;

  /// No description provided for @sellTitle.
  ///
  /// In en, this message translates to:
  /// **'Sell on RIVALO'**
  String get sellTitle;

  /// No description provided for @sellTeaser.
  ///
  /// In en, this message translates to:
  /// **'Run a sports shop or make gear? Apply to sell to RIVALO players.'**
  String get sellTeaser;

  /// No description provided for @sellAccountRequired.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to apply to sell.'**
  String get sellAccountRequired;

  /// No description provided for @sellIntro.
  ///
  /// In en, this message translates to:
  /// **'Approved shops and makers can sell their products in the RIVALO store.'**
  String get sellIntro;

  /// No description provided for @sellHowTitle.
  ///
  /// In en, this message translates to:
  /// **'How it works'**
  String get sellHowTitle;

  /// No description provided for @sellHow1.
  ///
  /// In en, this message translates to:
  /// **'Apply here. RIVALO staff check who you are and sign an agreement with you, including the commission.'**
  String get sellHow1;

  /// No description provided for @sellHow2.
  ///
  /// In en, this message translates to:
  /// **'Add your products in the Seller Hub on a computer. Staff review each one before it goes on sale.'**
  String get sellHow2;

  /// No description provided for @sellHow3.
  ///
  /// In en, this message translates to:
  /// **'Bring your stock to RIVALO. We hand it to customers, take the cash and pay you what you earned, minus the commission.'**
  String get sellHow3;

  /// No description provided for @sellApply.
  ///
  /// In en, this message translates to:
  /// **'Apply to sell'**
  String get sellApply;

  /// No description provided for @sellApplyAgain.
  ///
  /// In en, this message translates to:
  /// **'Apply again'**
  String get sellApplyAgain;

  /// No description provided for @sellApplyTitle.
  ///
  /// In en, this message translates to:
  /// **'Apply to sell'**
  String get sellApplyTitle;

  /// No description provided for @sellBusinessName.
  ///
  /// In en, this message translates to:
  /// **'Shop or business name'**
  String get sellBusinessName;

  /// No description provided for @sellPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone number'**
  String get sellPhone;

  /// No description provided for @sellPhoneHelper.
  ///
  /// In en, this message translates to:
  /// **'Only RIVALO staff see it.'**
  String get sellPhoneHelper;

  /// No description provided for @sellPhoneInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a phone number (digits, spaces, + and - only).'**
  String get sellPhoneInvalid;

  /// No description provided for @sellCity.
  ///
  /// In en, this message translates to:
  /// **'City'**
  String get sellCity;

  /// No description provided for @sellCategories.
  ///
  /// In en, this message translates to:
  /// **'What would you sell?'**
  String get sellCategories;

  /// No description provided for @sellCategoriesRequired.
  ///
  /// In en, this message translates to:
  /// **'Choose at least one.'**
  String get sellCategoriesRequired;

  /// No description provided for @sellDescription.
  ///
  /// In en, this message translates to:
  /// **'Tell us about your products'**
  String get sellDescription;

  /// No description provided for @sellDescriptionHelper.
  ///
  /// In en, this message translates to:
  /// **'Brands, what you make, where you sell today.'**
  String get sellDescriptionHelper;

  /// No description provided for @sellDescriptionTooShort.
  ///
  /// In en, this message translates to:
  /// **'Write at least 20 characters.'**
  String get sellDescriptionTooShort;

  /// No description provided for @sellLink.
  ///
  /// In en, this message translates to:
  /// **'Shop page or social profile (optional)'**
  String get sellLink;

  /// No description provided for @sellTermsNotice.
  ///
  /// In en, this message translates to:
  /// **'Before you apply, read how selling on RIVALO works.'**
  String get sellTermsNotice;

  /// No description provided for @sellReadTerms.
  ///
  /// In en, this message translates to:
  /// **'Selling on RIVALO: terms'**
  String get sellReadTerms;

  /// No description provided for @sellSubmit.
  ///
  /// In en, this message translates to:
  /// **'Send application'**
  String get sellSubmit;

  /// No description provided for @sellSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Application sent.'**
  String get sellSubmitted;

  /// No description provided for @sellPendingPill.
  ///
  /// In en, this message translates to:
  /// **'Under review'**
  String get sellPendingPill;

  /// No description provided for @sellPendingTitle.
  ///
  /// In en, this message translates to:
  /// **'Your application is being reviewed'**
  String get sellPendingTitle;

  /// No description provided for @sellPendingBody.
  ///
  /// In en, this message translates to:
  /// **'RIVALO staff will contact you about the seller agreement. You will get a message in your inbox when they decide.'**
  String get sellPendingBody;

  /// No description provided for @sellSentOn.
  ///
  /// In en, this message translates to:
  /// **'Sent {date}'**
  String sellSentOn(String date);

  /// No description provided for @sellWithdraw.
  ///
  /// In en, this message translates to:
  /// **'Withdraw application'**
  String get sellWithdraw;

  /// No description provided for @sellWithdrawConfirm.
  ///
  /// In en, this message translates to:
  /// **'Withdraw your application?'**
  String get sellWithdrawConfirm;

  /// No description provided for @sellRejectedTitle.
  ///
  /// In en, this message translates to:
  /// **'Your last application was not approved'**
  String get sellRejectedTitle;

  /// No description provided for @sellRejectedNoReason.
  ///
  /// In en, this message translates to:
  /// **'No reason was given. You can contact support from Settings.'**
  String get sellRejectedNoReason;

  /// No description provided for @sellClosedTitle.
  ///
  /// In en, this message translates to:
  /// **'Selling is not open yet'**
  String get sellClosedTitle;

  /// No description provided for @sellClosedBody.
  ///
  /// In en, this message translates to:
  /// **'RIVALO is not accepting new sellers right now.'**
  String get sellClosedBody;

  /// No description provided for @sellApprovedTitle.
  ///
  /// In en, this message translates to:
  /// **'You sell on RIVALO as {shop}'**
  String sellApprovedTitle(String shop);

  /// No description provided for @sellShopPaused.
  ///
  /// In en, this message translates to:
  /// **'Shop paused'**
  String get sellShopPaused;

  /// No description provided for @sellShopClosed.
  ///
  /// In en, this message translates to:
  /// **'Shop closed'**
  String get sellShopClosed;

  /// No description provided for @sellHubTitle.
  ///
  /// In en, this message translates to:
  /// **'Seller Hub'**
  String get sellHubTitle;

  /// No description provided for @sellHubBody.
  ///
  /// In en, this message translates to:
  /// **'Manage your products, sales and statement in the Seller Hub. Sign in on a computer with your RIVALO account and your authenticator app.'**
  String get sellHubBody;

  /// No description provided for @sellHubAddress.
  ///
  /// In en, this message translates to:
  /// **'Seller Hub address'**
  String get sellHubAddress;

  /// No description provided for @sellCopyAddress.
  ///
  /// In en, this message translates to:
  /// **'Copy address'**
  String get sellCopyAddress;

  /// No description provided for @sellCopied.
  ///
  /// In en, this message translates to:
  /// **'Address copied.'**
  String get sellCopied;

  /// No description provided for @sellViewShop.
  ///
  /// In en, this message translates to:
  /// **'See your shop in the store'**
  String get sellViewShop;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en', 'kmr'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
    case 'kmr':
      return AppLocalizationsKmr();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
