import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../auth/widgets/account_required.dart';
import '../../competition/data/competition.dart';
import '../../competition/widgets/fixtures_view.dart';
import '../data/tournament_models.dart';
import '../data/tournaments_repository.dart';

class TournamentDetailScreen extends ConsumerWidget {
  const TournamentDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final detail = ref.watch(tournamentDetailProvider(slug));
    final isGuest = ref.watch(isGuestProvider);
    // Guests have no entries; skip the authenticated call entirely.
    final entry = isGuest ? const AsyncValue<TournamentEntry?>.data(null) : ref.watch(myEntryProvider(slug));
    final me = ref.watch(meProvider);

    return detail.when(
      skipLoadingOnRefresh: true,
      loading: () => RDetailPage(title: l10n.competeTournaments, child: RLoadingList(semanticLabel: l10n.loading)),
      error: (e, _) => RDetailPage(
        title: l10n.competeTournaments,
        child: RErrorState(
          title: isOffline(e) ? l10n.errorOfflineTitle : l10n.errorGenericTitle,
          message: errorMessage(context, e),
          retryLabel: l10n.actionRetry,
          offline: isOffline(e),
          onRetry: () => ref.invalidate(tournamentDetailProvider(slug)),
        ),
      ),
      data: (t) {
        final myEntry = entry.value;
        final hasEntry = myEntry != null && myEntry.isActive;

        Widget? bottom;
        if (!hasEntry) {
          if (isGuest && t.registrationIsOpen) {
            bottom = RButton(
              label: l10n.detailSignUpToRegister,
              onPressed: () => showAccountRequired(context, reason: l10n.accountRequiredRegister),
            );
          } else if (const {'in_progress', 'completed', 'cancelled'}.contains(t.status)) {
            bottom = null; // play has started or ended: nothing to register for
          } else if (!t.registrationIsOpen) {
            bottom = RButton.secondary(label: l10n.detailRegistrationNotOpen, onPressed: null);
          } else if (me != null && !me.emailVerified) {
            bottom = RButton.secondary(label: l10n.detailVerifyToRegister, onPressed: () => context.push(Routes.verifyEmail));
          } else {
            bottom = RButton(
              label: t.isFull ? l10n.detailJoinWaitlist : l10n.detailRegister,
              onPressed: () => context.push(Routes.registerEntry(t.slug)),
            );
          }
        }

        return RHeroPage(
          title: t.name,
          header: RCover(
            imageUrl: t.coverUrl,
            sportIcon: t.sportIcon,
            seed: t.id,
            scrim: RCoverScrim.strong,
            semanticLabel: t.coverUrl == null ? null : t.name,
          ),
          bottomBar: bottom,
          onRefresh: () async {
            ref.invalidate(tournamentDetailProvider(slug));
            ref.invalidate(myEntryProvider(slug));
            ref.invalidate(fixturesProvider(slug));
            await ref.read(tournamentDetailProvider(slug).future).catchError((_) => t);
          },
          child: _Body(tournament: t, entry: hasEntry ? myEntry : null),
        );
      },
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.tournament, required this.entry});

  final Tournament tournament;
  final TournamentEntry? entry;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final t = tournament;
    final status = tournamentStatus(context, t.status);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Wrap(spacing: RSpace.sm, runSpacing: RSpace.sm, children: [
          RStatusPill(label: status.label, tone: status.tone),
          RStatusPill(label: registrationTypeLabel(context, t.registrationType), tone: RTone.neutral, icon: CupertinoIcons.person_2),
          if (t.isSampleData) RSampleBadge(label: l10n.sampleData),
        ]),
        const SizedBox(height: RSpace.md),
        Semantics(header: true, child: Text(t.name, style: RType.title1)),
        const SizedBox(height: RSpace.xs),
        Text('${t.sportName} · ${t.disciplineName}${t.seasonName != null ? ' · ${t.seasonName}' : ''}',
            style: RType.subheadline.copyWith(color: RColors.textSecondary)),

        if (t.statusReason != null && (t.status == 'postponed' || t.status == 'cancelled')) ...[
          const SizedBox(height: RSpace.lg),
          RNotice(title: l10n.detailStatusReason, message: t.statusReason!, icon: CupertinoIcons.exclamationmark_triangle, color: RColors.warning),
        ],

        const SizedBox(height: RSpace.xl),
        RFadeIn(child: _SpotsPanel(tournament: t)),
        const SizedBox(height: RSpace.md),
        RFadeIn(index: 1, child: _StatGrid(tournament: t)),
        if (t.prizes.isNotEmpty) ...[
          RSectionTitle(l10n.prizesTitle),
          RFadeIn(index: 2, child: _PrizesSection(prizes: t.prizes)),
        ],
        if (t.sponsors.isNotEmpty) ...[
          RSectionTitle(l10n.sponsorsTitle),
          _SponsorsStrip(sponsors: t.sponsors),
        ],

        if (entry != null) ...[const SizedBox(height: RSpace.xl), RFadeIn(index: 2, child: _MyEntryCard(entry: entry!, tournament: t))],

        const SizedBox(height: RSpace.xl),
        RCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              RInfoItem(icon: CupertinoIcons.calendar, label: l10n.detailStarts, value: formatDateTime(context, t.startsAt)),
              if (t.endsAt != null) ...[const SizedBox(height: RSpace.lg), RInfoItem(icon: CupertinoIcons.calendar_badge_minus, label: l10n.detailEnds, value: formatDateTime(context, t.endsAt))],
              if (t.venueName != null || t.city != null) ...[
                const SizedBox(height: RSpace.lg),
                RInfoItem(
                  icon: CupertinoIcons.location,
                  label: l10n.detailVenue,
                  value: [t.venueName, t.venueAddress, t.city].whereType<String>().where((s) => s.isNotEmpty).join('\n'),
                ),
              ],
              if (t.onlineRegion != null) ...[const SizedBox(height: RSpace.lg), RInfoItem(icon: CupertinoIcons.globe, label: l10n.detailOnline, value: t.onlineRegion!)],
              const SizedBox(height: RSpace.lg),
              RInfoItem(
                icon: CupertinoIcons.time,
                label: l10n.detailRegistration,
                value: t.registrationIsOpen
                    ? l10n.detailRegistrationCloses(formatDateTime(context, t.registrationClosesAt))
                    : (t.registrationOpensAt != null && t.registrationOpensAt!.isAfter(DateTime.now())
                        ? l10n.detailRegistrationOpens(formatDateTime(context, t.registrationOpensAt))
                        : tournamentStatus(context, t.status).label),
              ),
              if (t.checkInClosesAt != null) ...[const SizedBox(height: RSpace.lg), RInfoItem(icon: CupertinoIcons.checkmark_seal, label: l10n.detailCheckInCloses, value: formatDateTime(context, t.checkInClosesAt))],
              const SizedBox(height: RSpace.md),
              Text(l10n.detailTimesLocal, style: RType.caption1.copyWith(color: RColors.textTertiary)),
            ],
          ),
        ),

        const SizedBox(height: RSpace.md),
        RCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              RInfoItem(icon: CupertinoIcons.person_3, label: l10n.detailEntrants, value: '${l10n.detailEntrantsValue(t.reserved, t.maxEntrants)}${t.waitlisted > 0 ? '\n${l10n.waitlistCount(t.waitlisted)}' : ''}'),
              const SizedBox(height: RSpace.lg),
              RInfoItem(icon: CupertinoIcons.square_grid_2x2, label: l10n.detailFormat, value: formatLabel(context, t.format)),
              if (t.isTeamBased) ...[
                const SizedBox(height: RSpace.lg),
                RInfoItem(icon: CupertinoIcons.person_2_square_stack, label: l10n.detailRoster, value: l10n.detailRosterValue(t.rosterMin, t.rosterMax, t.substitutesMax)),
              ],
              const SizedBox(height: RSpace.lg),
              RInfoItem(icon: CupertinoIcons.ticket, label: l10n.detailEntry, value: t.isPaid ? l10n.feeInPerson(t.entryFeeFormatted) : l10n.free),
              const SizedBox(height: RSpace.lg),
              RInfoItem(
                icon: CupertinoIcons.chart_bar,
                label: l10n.tabRankings,
                value: t.rankingEligible ? l10n.detailRankingEligible : l10n.detailRankingNotEligible,
              ),
            ],
          ),
        ),
        if (t.isPaid) ...[const SizedBox(height: RSpace.md), RNotice(message: l10n.detailPaymentNotice, icon: CupertinoIcons.money_dollar_circle)],

        if (t.lobbyDetails != null) ...[
          RSectionHeader(l10n.detailLobby),
          RCard(child: SelectableText(t.lobbyDetails!, style: RType.body)),
        ],
        if ((t.description ?? '').isNotEmpty) ...[RSectionHeader(l10n.detailAbout), Text(t.description!, style: RType.body)],
        if ((t.eligibilityNotes ?? '').isNotEmpty) ...[RSectionHeader(l10n.detailEligibility), Text(t.eligibilityNotes!, style: RType.body)],
        if ((t.prizeRules ?? '').isNotEmpty) ...[RSectionHeader(l10n.detailPrizes), Text(t.prizeRules!, style: RType.body)],
        if ((t.rules ?? '').isNotEmpty) ...[
          RSectionHeader(l10n.detailRules),
          Text(t.rules!, style: RType.body),
          if (t.rulesLocked) ...[const SizedBox(height: RSpace.md), RNotice(message: l10n.detailRulesLocked, icon: CupertinoIcons.lock)],
        ],
        const SizedBox(height: RSpace.xl),
        RSectionTitle(l10n.fixturesTitle),
        FixturesSection(slug: t.slug),
      ],
    );
  }
}

class _MyEntryCard extends ConsumerStatefulWidget {
  const _MyEntryCard({required this.entry, required this.tournament});

  final TournamentEntry entry;
  final Tournament tournament;

  @override
  ConsumerState<_MyEntryCard> createState() => _MyEntryCardState();
}

class _MyEntryCardState extends ConsumerState<_MyEntryCard> {
  bool _cancelling = false;

  Future<void> _cancel() async {
    final l10n = context.l10n;
    final ok = await confirm(context, title: l10n.entryCancelConfirmTitle, message: l10n.entryCancelConfirmBody, confirmLabel: l10n.entryCancel, destructive: true);
    if (!ok || !mounted) return;

    setState(() => _cancelling = true);
    try {
      await ref.read(tournamentsRepositoryProvider).cancel(widget.entry.id);
      if (!mounted) return;
      showToast(context, l10n.entryCancelled);
      ref.invalidate(myEntryProvider(widget.tournament.slug));
      ref.invalidate(tournamentDetailProvider(widget.tournament.slug));
      ref.invalidate(myRegistrationsProvider);
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _cancelling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final e = widget.entry;
    final rows = [
      (l10n.entryRegistration, registrationStatus(context, e.registrationStatus, waitlistPosition: e.waitlistPosition)),
      (l10n.entryPayment, paymentStatus(context, e.paymentStatus)),
      (l10n.entryCheckIn, checkInStatus(context, e.checkInStatus)),
      (l10n.entryEligibility, eligibilityStatus(context, e.eligibilityStatus)),
    ];

    return RCard(
      highlight: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Expanded(child: Semantics(header: true, child: Text(l10n.myEntryTitle, style: RType.headline))),
            if (e.rosterLocked) RStatusPill(label: l10n.entryRosterLocked, tone: RTone.neutral, icon: CupertinoIcons.lock_fill),
          ]),
          if (e.teamName != null) ...[const SizedBox(height: RSpace.xxs), Text(e.teamName!, style: RType.footnote.copyWith(color: RColors.textSecondary))],
          const SizedBox(height: RSpace.md),
          for (final (label, view) in rows)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: RSpace.xs),
              child: MergeSemantics(
                child: Row(children: [
                  Expanded(child: Text(label, style: RType.subheadline.copyWith(color: RColors.textSecondary))),
                  RStatusPill(label: view.label, tone: view.tone),
                ]),
              ),
            ),
          if (e.members.length > 1) ...[
            const SizedBox(height: RSpace.sm),
            Text(e.members.map((m) => m.displayName ?? m.username).join(', '), style: RType.footnote.copyWith(color: RColors.textSecondary)),
          ],
          if (e.registrationStatus == 'reserved' && e.checkInStatus != 'checked_in') ...[
            const SizedBox(height: RSpace.md),
            Text(
              widget.tournament.isPaid ? l10n.entryNextStepPaid(widget.tournament.entryFeeFormatted) : l10n.entryNextStepFree,
              style: RType.footnote.copyWith(color: RColors.textPrimary),
            ),
          ],
          if (e.eligibilityStatus == 'ineligible' && e.eligibilityReason != null) ...[
            const SizedBox(height: RSpace.sm),
            Text(e.eligibilityReason!, style: RType.footnote.copyWith(color: RColors.error)),
          ],
          if (e.isCheckedIn && e.checkedInAt != null) ...[
            const SizedBox(height: RSpace.sm),
            Text(l10n.checkedInAt(formatDateTime(context, e.checkedInAt)), style: RType.footnote.copyWith(color: RColors.success)),
          ],
          if (e.canShowCheckInCode && widget.tournament.status != 'completed' && widget.tournament.status != 'cancelled') ...[
            const SizedBox(height: RSpace.md),
            RButton(
              label: l10n.checkInShowCode,
              icon: CupertinoIcons.qrcode,
              onPressed: () => context.push(Routes.checkInPass(e.id)),
            ),
          ],
          if (e.receipts.isNotEmpty) ...[
            const SizedBox(height: RSpace.md),
            Text(l10n.receiptsTitle, style: RType.subheadline.copyWith(fontWeight: FontWeight.w600)),
            const SizedBox(height: RSpace.xs),
            for (final r in e.receipts)
              Padding(
                padding: const EdgeInsets.only(top: RSpace.xxs),
                child: Text(
                  r.isRefund ? l10n.receiptRefund(r.formatted, r.reference) : l10n.receiptPayment(r.formatted, r.reference),
                  style: RType.footnote.copyWith(color: RColors.textSecondary),
                ),
              ),
          ],
          if (e.canManage && widget.tournament.registrationIsOpen) ...[
            const SizedBox(height: RSpace.md),
            RButton.destructive(label: l10n.entryCancel, onPressed: _cancel, loading: _cancelling, compact: true),
          ],
        ],
      ),
    );
  }
}

/// The competitive heartbeat of a tournament: how many places are taken and how long is left.
class _SpotsPanel extends StatelessWidget {
  const _SpotsPanel({required this.tournament});

  final Tournament tournament;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final t = tournament;
    final countdown = tournamentCountdown(
      context,
      status: t.status,
      startsAt: t.startsAt,
      registrationClosesAt: t.registrationClosesAt,
      registrationIsOpen: t.registrationIsOpen,
    );
    final note = t.isFull
        ? (t.waitlisted > 0 ? '${l10n.capacityFull} · ${l10n.waitlistCount(t.waitlisted)}' : l10n.capacityFull)
        : l10n.placesLeft(t.placesLeft);

    return Container(
      padding: const EdgeInsets.all(RSpace.lg),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(RRadius.lg),
        border: Border.all(color: RColors.accent.withValues(alpha: 0.35)),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [RColors.accent.withValues(alpha: 0.10), RColors.surface],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('${t.reserved}', style: RType.largeTitle.copyWith(color: RColors.accent, height: 1)),
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Text(' / ${t.maxEntrants}', style: RType.title3.copyWith(color: RColors.textSecondary)),
              ),
              const Spacer(),
              if (countdown != null)
                Flexible(
                  child: RGlassChip(label: countdown, icon: CupertinoIcons.timer, color: RColors.accent, background: RColors.surfaceRaised),
                ),
            ],
          ),
          const SizedBox(height: RSpace.md),
          RCapacityBar(taken: t.reserved, capacity: t.maxEntrants, label: l10n.capacityTaken(t.reserved, t.maxEntrants), trailing: note),
        ],
      ),
    );
  }
}

/// Four quick facts as tiles: start, entrants, entry fee, format.
class _StatGrid extends StatelessWidget {
  const _StatGrid({required this.tournament});

  final Tournament tournament;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final t = tournament;
    final tiles = [
      RStatTile(icon: CupertinoIcons.calendar, value: formatDate(context, t.startsAt), label: l10n.statStarts),
      RStatTile(icon: CupertinoIcons.person_2_fill, value: registrationTypeLabel(context, t.registrationType), label: l10n.statEntrants),
      RStatTile(
        icon: CupertinoIcons.ticket_fill,
        value: t.isPaid ? t.entryFeeFormatted : l10n.free,
        label: l10n.statEntry,
        color: t.isPaid ? RColors.textPrimary : RColors.success,
      ),
      RStatTile(icon: CupertinoIcons.square_grid_2x2_fill, value: formatLabel(context, t.format), label: l10n.statFormat),
    ];
    return LayoutBuilder(
      builder: (context, c) {
        final columns = c.maxWidth >= 560 ? 4 : 2;
        const gap = RSpace.sm;
        final width = (c.maxWidth - gap * (columns - 1)) / columns;
        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [for (final tile in tiles) SizedBox(width: width, child: tile)],
        );
      },
    );
  }
}

/// Prize tiers as podium cards: medal colour for places 1–3, the cash amount (or "To be
/// announced"), any item, and an honest certainty label.
class _PrizesSection extends StatelessWidget {
  const _PrizesSection({required this.prizes});

  final List<PrizeTier> prizes;

  static Color medal(int place) => switch (place) {
        1 => RColors.gold,
        2 => RColors.silver,
        3 => RColors.bronze,
        _ => RColors.textSecondary,
      };

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final p in prizes)
          Padding(
            padding: const EdgeInsets.only(bottom: RSpace.sm),
            child: _PrizeCard(prize: p, color: medal(p.place)),
          ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: RSpace.xs),
          child: Text(l10n.prizeLabelsNote, style: RType.footnote.copyWith(color: RColors.textSecondary)),
        ),
      ],
    );
  }
}

class _PrizeCard extends StatelessWidget {
  const _PrizeCard({required this.prize, required this.color});

  final PrizeTier prize;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final p = prize;
    final (label, tone, icon) = switch (p.label) {
      'guaranteed' => (l10n.prizeGuaranteed, RTone.success, CupertinoIcons.checkmark_shield_fill),
      'finalized' => (l10n.prizeFinalized, RTone.info, CupertinoIcons.checkmark_seal_fill),
      _ => (l10n.prizeEstimated, RTone.warning, CupertinoIcons.hourglass),
    };
    final amount = p.amountFormatted ?? (p.inKind == null ? l10n.prizeTba : null);

    return MergeSemantics(
      child: Container(
        padding: const EdgeInsets.all(RSpace.lg),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(RRadius.lg),
          border: Border.all(color: color.withValues(alpha: 0.45)),
          gradient: LinearGradient(
            begin: AlignmentDirectional.centerStart,
            end: AlignmentDirectional.centerEnd,
            colors: [color.withValues(alpha: 0.16), RColors.surface],
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: color.withValues(alpha: 0.18),
                border: Border.all(color: color, width: 1.5),
              ),
              alignment: Alignment.center,
              child: Text('${p.place}', style: RType.title3.copyWith(color: color, fontWeight: FontWeight.w800)),
            ),
            const SizedBox(width: RSpace.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(p.title ?? l10n.prizePlace(p.place), style: RType.subheadline.copyWith(color: RColors.textSecondary)),
                  if (amount != null) ...[
                    const SizedBox(height: RSpace.xxs),
                    Text(amount, style: RType.title3.copyWith(color: p.amountFormatted == null ? RColors.textSecondary : RColors.textPrimary)),
                  ],
                  if (p.inKind != null) ...[
                    const SizedBox(height: RSpace.xxs),
                    Row(children: [
                      Icon(CupertinoIcons.gift_fill, size: 14, color: color),
                      const SizedBox(width: RSpace.xs),
                      Flexible(child: Text(p.inKind!, style: RType.footnote)),
                    ]),
                  ],
                ],
              ),
            ),
            const SizedBox(width: RSpace.sm),
            Flexible(child: RStatusPill(label: label, tone: tone, icon: icon)),
          ],
        ),
      ),
    );
  }
}

/// Sponsors who agreed to be shown: logo (or initials) and name. No amounts.
class _SponsorsStrip extends StatelessWidget {
  const _SponsorsStrip({required this.sponsors});

  final List<SponsorRef> sponsors;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: RSpace.sm,
      runSpacing: RSpace.sm,
      children: [
        for (final s in sponsors)
          Container(
            padding: const EdgeInsetsDirectional.fromSTEB(RSpace.xs, RSpace.xs, RSpace.md, RSpace.xs),
            decoration: BoxDecoration(
              color: RColors.surface,
              borderRadius: BorderRadius.circular(RRadius.pill),
              border: Border.all(color: RColors.border),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                RAvatar(name: s.name, imageUrl: s.logoUrl, size: RSize.avatarSm),
                const SizedBox(width: RSpace.sm),
                Flexible(child: Text(s.name, style: RType.subheadline.copyWith(fontWeight: FontWeight.w600), maxLines: 1, overflow: TextOverflow.ellipsis)),
                if (s.isSampleData) ...[const SizedBox(width: RSpace.sm), RSampleBadge(label: context.l10n.sampleData)],
              ],
            ),
          ),
      ],
    );
  }
}
