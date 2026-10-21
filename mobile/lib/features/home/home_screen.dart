import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/design/design_system.dart';
import '../../core/providers.dart';
import '../../core/ui/ui_helpers.dart';
import '../auth/data/auth_repository.dart';
import '../auth/state/session_controller.dart';
import '../auth/widgets/account_required.dart';
import '../catalog/data/catalog.dart';
import '../../core/push/push_service.dart';
import '../engagement/data/engagement.dart';
import '../engagement/widgets/engagement_widgets.dart';
import '../news/data/news.dart';
import '../news/widgets/news_widgets.dart';
import '../teams/data/teams.dart';
import '../tournaments/data/tournament_models.dart';
import '../tournaments/data/tournaments_repository.dart';
import '../tournaments/widgets/tournament_card.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  static const _forMe = (scope: 'for_me', sportId: null);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (ref.watch(isGuestProvider)) return const _GuestHome();

    final l10n = context.l10n;
    final me = ref.watch(meProvider);
    final online = ref.watch(connectivityProvider).value ?? true;
    final upcoming = ref.watch(tournamentListProvider(_forMe));
    final registrations = ref.watch(myRegistrationsProvider);
    final inbox = ref.watch(inboxProvider);
    ref.watch(pushRegistrationProvider); // registers this phone for push when a token source exists

    Future<void> refresh() async {
      ref.invalidate(tournamentListProvider(_forMe));
      ref.invalidate(myRegistrationsProvider);
      ref.invalidate(inboxProvider);
      ref.invalidate(featuredNewsProvider);
      ref.invalidate(newsListProvider(null));
      ref.invalidate(unreadNotificationsProvider);
      await ref.read(sessionProvider.notifier).refreshMe().catchError((_) {});
    }

    return RLargeTitlePage(
      title: l10n.homeGreeting(me?.name ?? ''),
      onRefresh: refresh,
      trailing: const InboxBellButton(),
      slivers: [
        SliverList.list(children: [
          if (!online) ...[ROfflineBanner(message: l10n.offlineBanner), const SizedBox(height: RSpace.md)],
          if (me != null && me.deletionPending) ...[
            RNotice(
              message: l10n.deleteScheduled(formatDate(context, me.deletionScheduledFor)),
              icon: CupertinoIcons.exclamationmark_triangle,
              color: RColors.warning,
            ),
            const SizedBox(height: RSpace.md),
          ],
          if (me != null && !me.emailVerified) ...[const _VerifyBanner(), const SizedBox(height: RSpace.md)],
          inbox.maybeWhen(
            data: (box) => box.count == 0
                ? const SizedBox.shrink()
                : Padding(
                    padding: const EdgeInsets.only(bottom: RSpace.md),
                    child: RCard(
                      highlight: true,
                      onTap: () => context.push(Routes.invitations),
                      child: Row(
                        children: [
                          const Icon(CupertinoIcons.person_badge_plus, color: RColors.accent),
                          const SizedBox(width: RSpace.md),
                          Expanded(child: Text(l10n.homeInvitations(box.count), style: RType.headline)),
                          Text(l10n.homeReview, style: RType.subheadline.copyWith(color: RColors.accent, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ),
            orElse: () => const SizedBox.shrink(),
          ),

          const _FeaturedSlider(),

          // My registrations: the next one large, the rest as rows.
          RSectionTitle(l10n.homeNextUp),
          RAsyncView<List<TournamentEntry>>(
            value: registrations,
            onRetry: () => ref.invalidate(myRegistrationsProvider),
            loading: RLoadingList(count: 1, semanticLabel: l10n.loading),
            data: (entries) {
              final active = entries.where((e) => e.isActive).toList()
                ..sort((a, b) => (a.tournamentStartsAt ?? DateTime(9999)).compareTo(b.tournamentStartsAt ?? DateTime(9999)));
              if (active.isEmpty) {
                return _EmptyNextUp(onBrowse: () => context.go(Routes.compete));
              }
              return Column(children: [
                RFadeIn(child: _NextUpCard(entry: active.first)),
                for (final (i, e) in active.skip(1).indexed) RFadeIn(index: i + 1, child: _RegistrationTile(entry: e)),
              ]);
            },
          ),

          RSectionTitle(l10n.homeBrowseBySport),
          const _SportBubbles(),

          RSectionTitle(
            l10n.homeUpcomingForYou,
            actionLabel: l10n.actionSeeAll,
            onAction: () => context.go(Routes.compete),
          ),
          if (me != null && me.disciplineIds.isEmpty)
            REmptyState(
              icon: CupertinoIcons.sportscourt,
              title: l10n.homeNoDisciplinesTitle,
              message: l10n.homeNoDisciplinesBody,
              actionLabel: l10n.homeChooseSports,
              onAction: () => context.push(Routes.profileSports),
            )
          else
            RAsyncView<List<Tournament>>(
              value: upcoming,
              onRetry: () => ref.invalidate(tournamentListProvider(_forMe)),
              data: (list) => list.isEmpty
                  ? REmptyState(icon: CupertinoIcons.calendar, title: l10n.homeEmptyUpcomingTitle, message: l10n.homeEmptyUpcomingBody)
                  : TournamentRail(tournaments: list.take(8).toList()),
            ),

          const _LatestNews(),
        ]),
      ],
    );
  }
}

/// Home while browsing as a guest: the same rich content plus an invitation to sign up.
class _GuestHome extends ConsumerWidget {
  const _GuestHome();

  static const _upcoming = (scope: 'upcoming', sportId: null);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final online = ref.watch(connectivityProvider).value ?? true;
    final upcoming = ref.watch(tournamentListProvider(_upcoming));

    return RLargeTitlePage(
      title: l10n.guestHomeTitle,
      onRefresh: () async {
        ref.invalidate(tournamentListProvider(_upcoming));
        ref.invalidate(featuredNewsProvider);
        ref.invalidate(newsListProvider(null));
      },
      slivers: [
        SliverList.list(children: [
          if (!online) ...[ROfflineBanner(message: l10n.offlineBanner), const SizedBox(height: RSpace.md)],
          const _FeaturedSlider(),
          const GuestInviteCard(),
          RSectionTitle(l10n.homeBrowseBySport),
          const _SportBubbles(),
          RSectionTitle(
            l10n.homeUpcoming,
            actionLabel: l10n.actionSeeAll,
            onAction: () => context.go(Routes.compete),
          ),
          RAsyncView<List<Tournament>>(
            value: upcoming,
            onRetry: () => ref.invalidate(tournamentListProvider(_upcoming)),
            data: (list) => list.isEmpty
                ? REmptyState(icon: CupertinoIcons.calendar, title: l10n.tournamentsEmptyTitle, message: l10n.tournamentsEmptyBody)
                : TournamentRail(tournaments: list.take(8).toList()),
          ),
          const _LatestNews(),
        ]),
      ],
    );
  }
}

/// Featured news slider. Quietly absent when there is no featured news or it can't load:
/// Home never shows an error for optional content.
class _FeaturedSlider extends ConsumerWidget {
  const _FeaturedSlider();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return ref.watch(featuredNewsProvider).when(
          skipLoadingOnRefresh: true,
          loading: () => const Padding(padding: EdgeInsets.only(bottom: RSpace.md), child: NewsCarouselSkeleton()),
          error: (_, _) => const SizedBox.shrink(),
          data: (list) => list.isEmpty
              ? const SizedBox.shrink()
              : Padding(padding: const EdgeInsets.only(bottom: RSpace.md), child: RFadeIn(child: NewsCarousel(articles: list))),
        );
  }
}

class _LatestNews extends ConsumerWidget {
  const _LatestNews();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final list = ref.watch(newsListProvider(null)).value ?? const <NewsArticle>[];
    if (list.isEmpty) return const SizedBox.shrink();
    final items = list.take(3).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        RSectionTitle(l10n.homeLatestNews, actionLabel: l10n.actionSeeAll, onAction: () => context.go(Routes.news)),
        for (final (i, a) in items.indexed) ...[
          RFadeIn(index: i, child: NewsRow(article: a)),
          if (i < items.length - 1) const Divider(height: RSpace.md),
        ],
      ],
    );
  }
}

/// Round sport shortcuts that open Compete filtered to that sport.
class _SportBubbles extends ConsumerWidget {
  const _SportBubbles();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sports = ref.watch(sportsProvider).value ?? const <Sport>[];
    if (sports.isEmpty) return const SizedBox(height: RSpace.xs);
    final screen = MediaQuery.sizeOf(context).width;
    return LayoutBuilder(
      builder: (context, c) => SizedBox(
        height: 100,
        child: OverflowBox(
          minWidth: screen,
          maxWidth: screen,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            padding: EdgeInsets.symmetric(horizontal: (screen - c.maxWidth) / 2),
            itemCount: sports.length,
            separatorBuilder: (_, _) => const SizedBox(width: RSpace.sm),
            itemBuilder: (context, i) => RFadeIn(
              index: i,
              child: RSportBubble(icon: sports[i].icon, label: sports[i].name, onTap: () => context.go(Routes.competeSport(sports[i].id))),
            ),
          ),
        ),
      ),
    );
  }
}

/// The next tournament I'm registered for: cover, countdown and my status.
class _NextUpCard extends StatelessWidget {
  const _NextUpCard({required this.entry});

  final TournamentEntry entry;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final status = registrationStatus(context, entry.registrationStatus, waitlistPosition: entry.waitlistPosition);
    final start = entry.tournamentStartsAt;
    final countdown = start != null && start.isAfter(DateTime.now()) ? l10n.countdownStartsIn(timeUntil(context, start)) : null;

    return Padding(
      padding: const EdgeInsets.only(bottom: RSpace.md),
      child: RCard(
        padding: EdgeInsets.zero,
        highlight: true,
        onTap: entry.tournamentSlug == null ? null : () => context.push(Routes.tournament(entry.tournamentSlug!)),
        semanticLabel: [entry.tournamentName ?? entry.displayName, status.label, formatDateTime(context, start), ?countdown].join('. '),
        child: ExcludeSemantics(
          child: SizedBox(
            height: 148,
            child: Stack(
              fit: StackFit.expand,
              children: [
                RCover(imageUrl: entry.tournamentCoverUrl, sportIcon: entry.tournamentSportIcon, seed: entry.id, scrim: RCoverScrim.strong),
                Padding(
                  padding: const EdgeInsets.all(RSpace.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        RStatusPill(label: status.label, tone: status.tone),
                        const Spacer(),
                        if (countdown != null) RGlassChip(label: countdown, icon: CupertinoIcons.timer, color: RColors.accent),
                      ]),
                      const Spacer(),
                      Text(entry.tournamentName ?? entry.displayName, style: RType.title3, maxLines: 2, overflow: TextOverflow.ellipsis),
                      const SizedBox(height: RSpace.xxs),
                      Text(formatDateTime(context, start), style: RType.footnote.copyWith(color: RColors.textSecondary)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _EmptyNextUp extends StatelessWidget {
  const _EmptyNextUp({required this.onBrowse});

  final VoidCallback onBrowse;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return RCard(
      onTap: onBrowse,
      child: Row(children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(color: RColors.accent.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(RRadius.md)),
          alignment: Alignment.center,
          child: const Icon(CupertinoIcons.flag_fill, color: RColors.accent),
        ),
        const SizedBox(width: RSpace.md),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l10n.homeNoRegistrations, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
            const SizedBox(height: RSpace.xxs),
            Text(l10n.competeTournaments, style: RType.headline.copyWith(color: RColors.accent)),
          ]),
        ),
        const Icon(CupertinoIcons.chevron_forward, size: 17, color: RColors.textTertiary),
      ]),
    );
  }
}

class _RegistrationTile extends StatelessWidget {
  const _RegistrationTile({required this.entry});

  final TournamentEntry entry;

  @override
  Widget build(BuildContext context) {
    final status = registrationStatus(context, entry.registrationStatus, waitlistPosition: entry.waitlistPosition);
    return Padding(
      padding: const EdgeInsets.only(bottom: RSpace.sm),
      child: RCard(
        onTap: entry.tournamentSlug == null ? null : () => context.push(Routes.tournament(entry.tournamentSlug!)),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(color: RColors.surfaceRaised, borderRadius: BorderRadius.circular(RRadius.sm)),
              alignment: Alignment.center,
              child: const Icon(CupertinoIcons.calendar, color: RColors.accent, size: RSize.iconMd),
            ),
            const SizedBox(width: RSpace.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(entry.tournamentName ?? entry.displayName, style: RType.headline, maxLines: 1, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: RSpace.xxs),
                  Text(formatDateTime(context, entry.tournamentStartsAt), style: RType.footnote.copyWith(color: RColors.textSecondary)),
                ],
              ),
            ),
            const SizedBox(width: RSpace.sm),
            RStatusPill(label: status.label, tone: status.tone),
          ],
        ),
      ),
    );
  }
}

class _VerifyBanner extends ConsumerStatefulWidget {
  const _VerifyBanner();

  @override
  ConsumerState<_VerifyBanner> createState() => _VerifyBannerState();
}

class _VerifyBannerState extends ConsumerState<_VerifyBanner> {
  bool _sending = false;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return RCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            const Icon(CupertinoIcons.envelope_badge, color: RColors.warning, size: RSize.iconMd),
            const SizedBox(width: RSpace.sm),
            Expanded(child: Text(l10n.verifyBannerTitle, style: RType.headline)),
          ]),
          const SizedBox(height: RSpace.xs),
          Text(l10n.verifyBannerBody, style: RType.footnote.copyWith(color: RColors.textSecondary)),
          const SizedBox(height: RSpace.md),
          Row(children: [
            RButton.secondary(
              label: l10n.verifyResend,
              compact: true,
              expand: false,
              loading: _sending,
              onPressed: () async {
                setState(() => _sending = true);
                try {
                  await ref.read(authRepositoryProvider).resendVerification();
                  if (context.mounted) showToast(context, l10n.verifyResent);
                } catch (e) {
                  if (context.mounted) showToast(context, errorMessage(context, e));
                } finally {
                  if (mounted) setState(() => _sending = false);
                }
              },
            ),
            const SizedBox(width: RSpace.sm),
            RButton.tertiary(label: l10n.verifyCheck, onPressed: () => ref.read(sessionProvider.notifier).refreshMe().catchError((_) {})),
          ]),
        ],
      ),
    );
  }
}
