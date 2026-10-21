import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/competition.dart';

/// Fixtures on the tournament page: final standings, then each stage as a bracket (knockout)
/// or a table plus its matches (league and groups). Only published fixtures are shown.
class FixturesSection extends ConsumerWidget {
  const FixturesSection({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return ref.watch(fixturesProvider(slug)).when(
          skipLoadingOnRefresh: true,
          loading: () => RLoadingList(count: 1, semanticLabel: l10n.loading),
          error: (e, _) => RNotice(message: errorMessage(context, e), icon: CupertinoIcons.exclamationmark_circle, color: RColors.textSecondary),
          data: (f) {
            if (f.isEmpty) {
              return RNotice(message: l10n.fixturesEmpty, icon: CupertinoIcons.square_split_2x1, color: RColors.textSecondary);
            }
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (f.placementsFinal && f.placements.isNotEmpty) ...[
                  _FinalStandings(placements: f.placements),
                  const SizedBox(height: RSpace.lg),
                ],
                for (final stage in f.stages.reversed) ...[
                  Padding(
                    padding: const EdgeInsets.only(bottom: RSpace.sm),
                    child: Text(stage.name, style: RType.headline.copyWith(color: RColors.textSecondary)),
                  ),
                  if (stage.isBracket) BracketView(stage: stage) else _TableStage(stage: stage),
                  const SizedBox(height: RSpace.xl),
                ],
                Text(
                  f.pointsTable != null ? l10n.fixturesPointsTable(f.pointsTable!) : l10n.fixturesNoPoints,
                  style: RType.footnote.copyWith(color: RColors.textTertiary),
                ),
              ],
            );
          },
        );
  }
}

// ------------------------------------------------------------------ Bracket

/// Knockout bracket: one column per round, scrolling sideways. Each match sits halfway between
/// the two matches that feed it, joined by connector lines. A third-place match is shown below.
class BracketView extends StatelessWidget {
  const BracketView({super.key, required this.stage});

  final FixtureStage stage;

  static const double cardWidth = 196;
  static const double cardHeight = 76;
  static const double gap = 14;
  static const double connector = 28;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final rounds = stage.rounds.where((r) => r.matches.isNotEmpty).toList()..sort((a, b) => a.round.compareTo(b.round));
    if (rounds.isEmpty) return const SizedBox.shrink();

    final lastRound = rounds.last.round;
    FixtureMatch? third;
    final columns = <List<FixtureMatch>>[];
    for (final r in rounds) {
      final ms = [...r.matches]..sort((a, b) => a.position.compareTo(b.position));
      if (r.round == lastRound && ms.length > 1) {
        third = ms.firstWhere((m) => m.position == 2);
        ms.removeWhere((m) => m.position == 2);
      }
      columns.add(ms);
    }
    final height = columns.first.length * (cardHeight + gap);
    final screen = MediaQuery.sizeOf(context).width;

    return LayoutBuilder(
      builder: (context, c) {
        final edge = (screen - c.maxWidth) / 2;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Transform.translate(
              offset: Offset(-edge, 0),
              child: SizedBox(
                width: screen,
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  physics: const BouncingScrollPhysics(),
                  padding: EdgeInsets.symmetric(horizontal: edge),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      for (var i = 0; i < columns.length; i++) ...[
                        if (i > 0)
                          Padding(
                            padding: const EdgeInsets.only(top: 28),
                            child: CustomPaint(size: Size(connector, height), painter: _ConnectorPainter(feeders: columns[i - 1].length)),
                          ),
                        SizedBox(
                          width: cardWidth,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              SizedBox(
                                height: 28,
                                child: Text(
                                  (rounds[i].label ?? l10n.fixturesRound(rounds[i].round)).toUpperCase(),
                                  style: RType.caption2.copyWith(color: RColors.textSecondary, letterSpacing: 0.6),
                                ),
                              ),
                              SizedBox(
                                height: height,
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                                  children: [for (final m in columns[i]) SizedBox(height: cardHeight, child: MatchCard(match: m))],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
            if (third != null) ...[
              const SizedBox(height: RSpace.md),
              Text((third.label ?? '').toUpperCase(), style: RType.caption2.copyWith(color: RColors.textSecondary, letterSpacing: 0.6)),
              const SizedBox(height: RSpace.xs),
              SizedBox(width: cardWidth, height: cardHeight, child: MatchCard(match: third)),
            ],
          ],
        );
      },
    );
  }
}

/// Lines from each pair of matches in one round to the match they feed in the next.
class _ConnectorPainter extends CustomPainter {
  _ConnectorPainter({required this.feeders});

  final int feeders;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = RColors.borderStrong
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;
    final mid = size.width / 2;
    // Same maths as MainAxisAlignment.spaceAround: item i of n is centred at h(2i+1)/(2n).
    double centre(int i, int n) => size.height * (2 * i + 1) / (2 * n);
    for (var j = 0; j < feeders ~/ 2; j++) {
      final a = centre(2 * j, feeders);
      final b = centre(2 * j + 1, feeders);
      final out = centre(j, feeders ~/ 2);
      canvas.drawPath(
        Path()
          ..moveTo(0, a)
          ..lineTo(mid, a)
          ..lineTo(mid, b)
          ..lineTo(0, b)
          ..moveTo(mid, out)
          ..lineTo(size.width, out),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _ConnectorPainter old) => old.feeders != feeders;
}

// ------------------------------------------------------------------ Match card

/// Two lines (one per side) with scores; the winner in lime. My matches get a lime border.
class MatchCard extends StatelessWidget {
  const MatchCard({super.key, required this.match});

  final FixtureMatch match;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final m = match;
    final r = m.result;
    final sets = r?.sets ?? const [];
    final pending = m.pendingScoreLine != null && r == null;

    String side(int n) {
      final s = m.slot(n);
      if (s.name != null) return s.name!;
      return m.status == 'bye' ? l10n.fixturesBye : l10n.fixturesTbd;
    }

    String? score(int n) {
      if (r == null) return null;
      if (sets.isNotEmpty) return sets.map((s) => s[n - 1]).join(' ');
      return n == 1 ? r.slot1?.toString() : r.slot2?.toString();
    }

    Widget line(int n) {
      final s = m.slot(n);
      final won = s.isWinner;
      final mine = m.mySlot == n;
      final color = s.name == null ? RColors.textTertiary : (won ? RColors.accent : RColors.textPrimary);
      return Row(
        children: [
          if (mine) ...[const Icon(CupertinoIcons.person_fill, size: 11, color: RColors.accent), const SizedBox(width: 3)],
          Expanded(
            child: Text(side(n),
                style: RType.footnote.copyWith(color: color, fontWeight: won ? FontWeight.w700 : FontWeight.w500), maxLines: 1, overflow: TextOverflow.ellipsis),
          ),
          if (score(n) != null)
            Text(score(n)!, style: RType.footnote.copyWith(color: color, fontWeight: FontWeight.w700, fontFeatures: const [FontFeature.tabularFigures()])),
        ],
      );
    }

    final semantic = [
      m.label ?? l10n.fixturesRound(m.round),
      '${side(1)} ${score(1) ?? ''}',
      '${side(2)} ${score(2) ?? ''}',
      _statusText(context, m),
    ].join(', ');

    return RPressable(
      onPressed: m.status == 'bye' ? null : () => context.push(Routes.match(m.id)),
      semanticLabel: semantic,
      pressedScale: 0.96,
      pressedOpacity: 0.85,
      minSize: 0,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: RSpace.md, vertical: RSpace.sm),
        decoration: BoxDecoration(
          color: RColors.surface,
          borderRadius: BorderRadius.circular(RRadius.md),
          border: Border.all(color: m.isMine ? RColors.accent.withValues(alpha: 0.7) : (pending ? RColors.warning.withValues(alpha: 0.5) : RColors.border)),
        ),
        child: ExcludeSemantics(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              line(1),
              const Divider(height: RSpace.sm),
              line(2),
            ],
          ),
        ),
      ),
    );
  }
}

String _statusText(BuildContext context, FixtureMatch m) {
  final l = context.l10n;
  if (m.result == null && m.pendingScoreLine != null) return l.matchLive;
  return switch (m.status) {
    'completed' => l.matchStatusCompleted,
    'cancelled' => l.matchStatusCancelled,
    'bye' => l.matchStatusBye,
    'ready' => l.matchStatusReady,
    _ => l.matchStatusPending,
  };
}

String matchStatusLabel(BuildContext context, FixtureMatch m) => _statusText(context, m);

/// A match as a full-width list row (league and group fixtures).
class MatchRow extends StatelessWidget {
  const MatchRow({super.key, required this.match});

  final FixtureMatch match;

  @override
  Widget build(BuildContext context) {
    final m = match;
    return Padding(
      padding: const EdgeInsets.only(bottom: RSpace.sm),
      child: Row(
        children: [
          Expanded(child: SizedBox(height: BracketView.cardHeight, child: MatchCard(match: m))),
          const SizedBox(width: RSpace.sm),
          SizedBox(
            width: 84,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(matchStatusLabel(context, m), style: RType.caption1.copyWith(color: m.status == 'completed' ? RColors.success : RColors.textSecondary), textAlign: TextAlign.end),
                if (m.scheduledAt != null)
                  Text(formatDateTime(context, m.scheduledAt), style: RType.caption2.copyWith(color: RColors.textTertiary), textAlign: TextAlign.end, maxLines: 2),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ------------------------------------------------------------------ Tables

class _TableStage extends StatelessWidget {
  const _TableStage({required this.stage});

  final FixtureStage stage;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (stage.groups.isNotEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final g in stage.groups) ...[
            Row(children: [
              Text(l10n.fixturesGroup(g.name), style: RType.title3),
              const Spacer(),
              if (stage.advancePerGroup != null)
                Text(l10n.fixturesAdvance(stage.advancePerGroup!), style: RType.caption1.copyWith(color: RColors.accent)),
            ]),
            const SizedBox(height: RSpace.sm),
            StandingsTable(rows: g.standings, qualifying: stage.advancePerGroup ?? 0),
            const SizedBox(height: RSpace.md),
            _MatchesByRound(matches: g.matches),
            const SizedBox(height: RSpace.lg),
          ],
        ],
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (stage.standings != null) ...[StandingsTable(rows: stage.standings!), const SizedBox(height: RSpace.lg)],
        _MatchesByRound(matches: stage.rounds.expand((r) => r.matches).toList()),
      ],
    );
  }
}

class _MatchesByRound extends StatelessWidget {
  const _MatchesByRound({required this.matches});

  final List<FixtureMatch> matches;

  @override
  Widget build(BuildContext context) {
    final byRound = <int, List<FixtureMatch>>{};
    for (final m in matches) {
      byRound.putIfAbsent(m.round, () => []).add(m);
    }
    final rounds = byRound.keys.toList()..sort();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final r in rounds) ...[
          Padding(
            padding: const EdgeInsets.only(bottom: RSpace.xs, top: RSpace.xs),
            child: Text(context.l10n.fixturesRound(r).toUpperCase(), style: RType.caption2.copyWith(color: RColors.textSecondary, letterSpacing: 0.6)),
          ),
          for (final m in byRound[r]!) MatchRow(match: m),
        ],
      ],
    );
  }
}

/// League/group table. Qualifying places get a lime edge; my row is highlighted.
class StandingsTable extends StatelessWidget {
  const StandingsTable({super.key, required this.rows, this.qualifying = 0});

  final List<StandingRow> rows;
  final int qualifying;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final head = RType.caption1.copyWith(color: RColors.textSecondary, fontWeight: FontWeight.w700);
    const num = TextStyle(fontFeatures: [FontFeature.tabularFigures()]);

    Widget cell(String text, {TextStyle? style, double width = 30}) =>
        SizedBox(width: width, child: Text(text, style: (style ?? RType.footnote).merge(num), textAlign: TextAlign.center));

    return Container(
      decoration: BoxDecoration(color: RColors.surface, borderRadius: BorderRadius.circular(RRadius.md), border: Border.all(color: RColors.border)),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: RSpace.md, vertical: RSpace.sm),
            child: Row(children: [
              SizedBox(width: 24, child: Text('#', style: head)),
              Expanded(child: const SizedBox()),
              cell(l10n.tableP, style: head),
              cell(l10n.tableW, style: head),
              cell(l10n.tableD, style: head),
              cell(l10n.tableL, style: head),
              cell('+/-', style: head, width: 36),
              cell(l10n.tablePts, style: head, width: 36),
            ]),
          ),
          for (final r in rows)
            MergeSemantics(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: RSpace.md, vertical: RSpace.sm),
                decoration: BoxDecoration(
                  color: r.isMine ? RColors.accent.withValues(alpha: 0.08) : null,
                  border: BorderDirectional(
                    top: const BorderSide(color: RColors.border, width: 0.5),
                    start: BorderSide(color: r.rank <= qualifying ? RColors.accent : Colors.transparent, width: 3),
                  ),
                ),
                child: Row(children: [
                  SizedBox(width: 24, child: Text('${r.rank}', style: RType.footnote.copyWith(color: RColors.textSecondary, fontWeight: FontWeight.w700))),
                  Expanded(
                    child: Text(r.name, style: RType.subheadline.copyWith(fontWeight: r.isMine ? FontWeight.w700 : FontWeight.w500), maxLines: 1, overflow: TextOverflow.ellipsis),
                  ),
                  cell('${r.played}'),
                  cell('${r.won}'),
                  cell('${r.drawn}'),
                  cell('${r.lost}'),
                  cell(r.diff > 0 ? '+${r.diff}' : '${r.diff}', width: 36),
                  cell('${r.points}', style: RType.footnote.copyWith(fontWeight: FontWeight.w800, color: RColors.accent), width: 36),
                ]),
              ),
            ),
        ],
      ),
    );
  }
}

// ------------------------------------------------------------------ Final standings

class _FinalStandings extends StatelessWidget {
  const _FinalStandings({required this.placements});

  final List<Placement> placements;

  @override
  Widget build(BuildContext context) {
    Color medal(int p) => switch (p) { 1 => RColors.gold, 2 => RColors.silver, 3 => RColors.bronze, _ => RColors.textSecondary };
    return RCard(
      highlight: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            const Icon(CupertinoIcons.rosette, color: RColors.gold, size: 20),
            const SizedBox(width: RSpace.sm),
            Text(context.l10n.fixturesFinalStandings, style: RType.headline),
          ]),
          const SizedBox(height: RSpace.md),
          for (final p in placements.take(8))
            Padding(
              padding: const EdgeInsets.only(bottom: RSpace.sm),
              child: Row(children: [
                Container(
                  width: 28,
                  height: 28,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(shape: BoxShape.circle, color: medal(p.place).withValues(alpha: 0.18), border: Border.all(color: medal(p.place))),
                  child: Text('${p.place}', style: RType.caption1.copyWith(color: medal(p.place), fontWeight: FontWeight.w800)),
                ),
                const SizedBox(width: RSpace.md),
                Expanded(child: Text(p.name, style: RType.subheadline.copyWith(fontWeight: p.isMine ? FontWeight.w800 : FontWeight.w500))),
                if (p.isMine) Text(context.l10n.rankingsYou, style: RType.caption1.copyWith(color: RColors.accent)),
              ]),
            ),
        ],
      ),
    );
  }
}
