import 'package:flutter/cupertino.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/tournament_models.dart';

/// Tournament card: cover photo (or sport artwork) with status and countdown, then name, date,
/// place, a live "places taken" bar and the entry fee.
///
/// [compact] is the narrower variant used in horizontal rails.
class TournamentCard extends StatelessWidget {
  const TournamentCard({super.key, required this.tournament, this.compact = false});

  final Tournament tournament;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final t = tournament;
    final status = tournamentStatus(context, t.status);
    final where = t.venueName ?? t.city ?? t.onlineRegion;
    final fee = t.isPaid ? l10n.feeInPerson(t.entryFeeFormatted) : l10n.free;
    final countdown = tournamentCountdown(
      context,
      status: t.status,
      startsAt: t.startsAt,
      registrationClosesAt: t.registrationClosesAt,
      registrationIsOpen: t.registrationIsOpen,
    );
    final placesNote = t.isFull
        ? (t.waitlisted > 0 ? '${l10n.capacityFull} · ${l10n.waitlistCount(t.waitlisted)}' : l10n.capacityFull)
        : l10n.placesLeft(t.placesLeft);

    final card = RCard(
      padding: EdgeInsets.zero,
      onTap: () => context.push(Routes.tournament(t.slug)),
      semanticLabel: [t.name, status.label, t.disciplineName, formatDateTime(context, t.startsAt), placesNote, ?countdown].join('. '),
      child: ExcludeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AspectRatio(
              aspectRatio: compact ? 16 / 9 : 2.2,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  RCover(imageUrl: t.coverUrl, sportIcon: t.sportIcon, seed: t.id, scrim: RCoverScrim.strong),
                  PositionedDirectional(
                    top: RSpace.md,
                    start: RSpace.md,
                    end: RSpace.md,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Flexible(child: RGlassChip(label: status.label, color: _toneColor(status.tone))),
                        if (t.isSampleData) ...[const SizedBox(width: RSpace.sm), Flexible(child: RSampleBadge(label: l10n.sampleData))],
                      ],
                    ),
                  ),
                  PositionedDirectional(
                    bottom: RSpace.md,
                    start: RSpace.md,
                    end: RSpace.md,
                    child: Row(
                      children: [
                        Container(
                          width: 34,
                          height: 34,
                          decoration: BoxDecoration(color: const Color(0x990B1020), shape: BoxShape.circle, border: Border.all(color: const Color(0x33FFFFFF), width: 0.5)),
                          alignment: Alignment.center,
                          child: SportGlyph(t.sportIcon, size: 20, color: RColors.accent),
                        ),
                        const SizedBox(width: RSpace.sm),
                        if (countdown != null)
                          Expanded(
                            child: Align(
                              alignment: AlignmentDirectional.centerEnd,
                              child: RGlassChip(label: countdown, icon: CupertinoIcons.timer, color: RColors.accent),
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(RSpace.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(t.name, style: compact ? RType.headline : RType.title3, maxLines: 2, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: RSpace.xs),
                  Text('${t.disciplineName} · ${registrationTypeLabel(context, t.registrationType)}',
                      style: RType.subheadline.copyWith(color: RColors.textSecondary), maxLines: 1, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: RSpace.md),
                  _Meta(icon: CupertinoIcons.calendar, text: formatDateTime(context, t.startsAt)),
                  if (where != null && !compact) ...[const SizedBox(height: RSpace.xs), _Meta(icon: CupertinoIcons.location, text: where)],
                  const SizedBox(height: RSpace.md),
                  RCapacityBar(
                    taken: t.reserved,
                    capacity: t.maxEntrants,
                    label: l10n.capacityTaken(t.reserved, t.maxEntrants),
                    trailing: placesNote,
                    compact: compact,
                  ),
                  const SizedBox(height: RSpace.md),
                  Row(
                    children: [
                      Icon(CupertinoIcons.ticket, size: 16, color: t.isPaid ? RColors.textPrimary : RColors.success),
                      const SizedBox(width: RSpace.sm),
                      Expanded(
                        child: Text(fee,
                            style: RType.footnote.copyWith(color: t.isPaid ? RColors.textPrimary : RColors.success, fontWeight: FontWeight.w600),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis),
                      ),
                      const Icon(CupertinoIcons.chevron_forward, size: 15, color: RColors.textTertiary),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );

    if (compact) return card;
    return Padding(padding: const EdgeInsets.only(bottom: RSpace.lg), child: card);
  }
}

Color _toneColor(RTone tone) => switch (tone) {
      RTone.accent => RColors.accent,
      RTone.success => RColors.success,
      RTone.warning => RColors.warning,
      RTone.error => RColors.error,
      RTone.info => RColors.info,
      _ => RColors.textPrimary,
    };

/// Horizontal rail of compact tournament cards (App Store "shelf").
class TournamentRail extends StatelessWidget {
  const TournamentRail({super.key, required this.tournaments});

  final List<Tournament> tournaments;

  @override
  Widget build(BuildContext context) {
    final screen = MediaQuery.sizeOf(context).width;
    final cardWidth = (screen * 0.78).clamp(260.0, 340.0);
    // A Row inside a horizontal scroll view takes the height of its tallest card, so long names
    // and large text sizes never get cut off. Rails hold a handful of cards, so this stays cheap.
    return LayoutBuilder(
      builder: (context, c) {
        final edge = (screen - c.maxWidth) / 2;
        return Transform.translate(
          offset: Offset(-edge, 0),
          child: SizedBox(
            width: screen,
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              padding: EdgeInsets.symmetric(horizontal: edge),
              child: IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (var i = 0; i < tournaments.length; i++) ...[
                      if (i > 0) const SizedBox(width: RSpace.md),
                      SizedBox(
                        width: cardWidth,
                        child: RFadeIn(index: i, child: TournamentCard(tournament: tournaments[i], compact: true)),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 16, color: RColors.textSecondary),
        const SizedBox(width: RSpace.sm),
        Flexible(child: Text(text, style: RType.footnote.copyWith(color: RColors.textSecondary), maxLines: 1, overflow: TextOverflow.ellipsis)),
      ],
    );
  }
}
