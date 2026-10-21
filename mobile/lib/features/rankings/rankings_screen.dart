import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/design/design_system.dart';
import '../../core/ui/ui_helpers.dart';
import '../auth/state/session_controller.dart';
import '../catalog/data/catalog.dart';
import '../competition/data/competition.dart';

/// Leaderboards per discipline, for this season or all time. Built only from points awarded for
/// referee-approved results under a published points table. With no points, the podium stays
/// empty and says so; nothing is invented.
class RankingsScreen extends ConsumerStatefulWidget {
  const RankingsScreen({super.key});

  @override
  ConsumerState<RankingsScreen> createState() => _RankingsScreenState();
}

class _RankingsScreenState extends ConsumerState<RankingsScreen> {
  int? _disciplineId;
  bool _lifetime = false;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final sports = ref.watch(sportsProvider).value ?? const <Sport>[];
    final disciplines = [for (final s in sports) for (final d in s.disciplines) (sport: s, discipline: d)];
    final me = ref.watch(meProvider);

    // Default to one of my disciplines, otherwise the first one.
    final selected = _disciplineId ??
        (disciplines.where((x) => me?.disciplineIds.contains(x.discipline.id) ?? false).firstOrNull ?? disciplines.firstOrNull)?.discipline.id;
    final seasons = ref.watch(seasonsProvider).value ?? const <Season>[];
    final activeSeason = seasons.where((s) => s.status == 'active').firstOrNull;
    final seasonId = _lifetime ? null : activeSeason?.id;
    final board = selected == null ? null : ref.watch(leaderboardProvider((disciplineId: selected, seasonId: seasonId)));

    return RLargeTitlePage(
      title: l10n.rankingsTitle,
      onRefresh: () async {
        ref.invalidate(sportsProvider);
        ref.invalidate(seasonsProvider);
        if (selected != null) ref.invalidate(leaderboardProvider((disciplineId: selected, seasonId: seasonId)));
      },
      slivers: [
        SliverList.list(children: [
          if (disciplines.isNotEmpty) ...[
            _DisciplineChips(
              items: disciplines,
              selected: selected,
              onChanged: (id) => setState(() => _disciplineId = id),
            ),
            const SizedBox(height: RSpace.md),
          ],
          if (activeSeason != null) ...[
            SizedBox(
              width: double.infinity,
              child: CupertinoSlidingSegmentedControl<bool>(
                groupValue: _lifetime,
                backgroundColor: RColors.surface,
                thumbColor: RColors.surfaceRaised,
                children: {
                  false: _Seg('${l10n.rankingsSeason} · ${activeSeason.name}'),
                  true: _Seg(l10n.rankingsLifetime),
                },
                onValueChanged: (v) {
                  HapticFeedback.selectionClick();
                  setState(() => _lifetime = v ?? false);
                },
              ),
            ),
            const SizedBox(height: RSpace.lg),
          ],
          if (board == null)
            const RFadeIn(child: _PodiumHero(top: []))
          else
            board.when(
              skipLoadingOnRefresh: true,
              loading: () => RLoadingList(count: 2, semanticLabel: l10n.loading),
              error: (e, _) => RErrorState(
                title: l10n.errorGenericTitle,
                message: errorMessage(context, e),
                retryLabel: l10n.actionRetry,
                onRetry: () => ref.invalidate(leaderboardProvider((disciplineId: selected!, seasonId: seasonId))),
              ),
              data: (b) => _BoardView(board: b),
            ),
          RSectionTitle(l10n.rankingsHowTitle),
          RFadeIn(
            index: 3,
            child: _HowItWorks(items: [
              (CupertinoIcons.checkmark_seal_fill, l10n.rankingsHowVerifiedTitle, l10n.rankingsHowVerifiedBody, RColors.success),
              (CupertinoIcons.list_number, l10n.rankingsHowHistoryTitle, l10n.rankingsHowHistoryBody, RColors.info),
              (CupertinoIcons.calendar_today, l10n.rankingsHowSeasonTitle, l10n.rankingsHowSeasonBody, RColors.gold),
            ]),
          ),
          const SizedBox(height: RSpace.lg),
          RNotice(message: l10n.rankingsPrinciple, icon: CupertinoIcons.checkmark_shield),
        ]),
      ],
    );
  }
}

class _Seg extends StatelessWidget {
  const _Seg(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: RSpace.sm, horizontal: RSpace.xs),
        child: Text(text, style: RType.footnote.copyWith(fontWeight: FontWeight.w600), maxLines: 1, overflow: TextOverflow.ellipsis),
      );
}

class _BoardView extends StatelessWidget {
  const _BoardView({required this.board});

  final Leaderboard board;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final rows = board.rows;
    if (rows.isEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const RFadeIn(child: _PodiumHero(top: [])),
          const SizedBox(height: RSpace.lg),
          Semantics(header: true, child: Text(l10n.rankingsEmptyTitle, style: RType.title3, textAlign: TextAlign.center)),
          const SizedBox(height: RSpace.sm),
          Text(board.hasPublishedRules ? l10n.rankingsEmptyBody : l10n.rankingsNoTable,
              style: RType.subheadline.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
        ],
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        RFadeIn(child: _PodiumHero(top: rows.take(3).toList())),
        const SizedBox(height: RSpace.lg),
        if (rows.length > 3)
          RFadeIn(
            index: 1,
            child: RGroupedSection(children: [for (final r in rows.skip(3)) _BoardRow(row: r)]),
          ),
      ],
    );
  }
}

class _BoardRow extends StatelessWidget {
  const _BoardRow({required this.row});

  final LeaderboardRow row;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final r = row;
    return Container(
      color: r.isMe ? RColors.accent.withValues(alpha: 0.08) : null,
      child: RListRow(
        title: r.isMe ? '${r.name} · ${l10n.rankingsYou}' : r.name,
        subtitle: '@${r.username} · ${l10n.rankingsWinsValue(r.wins)}',
        leading: Row(mainAxisSize: MainAxisSize.min, children: [
          SizedBox(width: 28, child: Text('${r.rank}', style: RType.headline.copyWith(color: RColors.textSecondary))),
          RAvatar(name: r.name, imageUrl: r.avatarUrl, size: RSize.avatarSm, ring: r.isMe),
        ]),
        trailing: Text(l10n.rankingsPointsValue(r.points), style: RType.headline.copyWith(color: RColors.accent)),
        onTap: () => context.push(Routes.player(r.username)),
        showChevron: false,
      ),
    );
  }
}

class _DisciplineChips extends StatelessWidget {
  const _DisciplineChips({required this.items, required this.selected, required this.onChanged});

  final List<({Sport sport, Discipline discipline})> items;
  final int? selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final screen = MediaQuery.sizeOf(context).width;
    return LayoutBuilder(
      builder: (context, c) {
        final edge = (screen - c.maxWidth) / 2;
        return Transform.translate(
          offset: Offset(-edge, 0),
          child: SizedBox(
            width: screen,
            height: RSize.minTouchTarget,
            child: ListView(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              padding: EdgeInsets.symmetric(horizontal: edge),
              children: [
                for (final x in items)
                  Padding(
                    padding: const EdgeInsetsDirectional.only(end: RSpace.sm),
                    child: Semantics(
                      selected: x.discipline.id == selected,
                      button: true,
                      label: x.discipline.name,
                      excludeSemantics: true,
                      child: RPressable(
                        isButton: false,
                        pressedScale: 0.94,
                        onPressed: () {
                          HapticFeedback.selectionClick();
                          onChanged(x.discipline.id);
                        },
                        child: AnimatedContainer(
                          duration: RMotion.of(context, RMotion.base),
                          padding: const EdgeInsets.symmetric(horizontal: RSpace.md),
                          decoration: BoxDecoration(
                            color: x.discipline.id == selected ? RColors.accent : RColors.surface,
                            borderRadius: BorderRadius.circular(RRadius.pill),
                            border: Border.all(color: x.discipline.id == selected ? RColors.accent : RColors.border),
                          ),
                          child: Row(children: [
                            SportGlyph(x.sport.icon, size: 16, color: x.discipline.id == selected ? RColors.onAccent : RColors.accent),
                            const SizedBox(width: RSpace.xs + 2),
                            Text(x.discipline.name,
                                style: RType.subheadline.copyWith(fontWeight: FontWeight.w600, color: x.discipline.id == selected ? RColors.onAccent : RColors.textPrimary)),
                          ]),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Podium. With real leaders it shows their photo, name and points; empty places show "?" and
/// "Awaiting approved results", never a made-up player.
class _PodiumHero extends StatelessWidget {
  const _PodiumHero({required this.top});

  final List<LeaderboardRow> top;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    LeaderboardRow? at(int i) => i < top.length ? top[i] : null;
    return Container(
      padding: const EdgeInsets.fromLTRB(RSpace.md, RSpace.xxl, RSpace.md, 0),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(RRadius.xl),
        border: Border.all(color: RColors.border),
        gradient: const RadialGradient(
          center: Alignment(0, -0.6),
          radius: 1.1,
          colors: [Color(0xFF2A3A1A), RColors.surface, RColors.backgroundDeep],
          stops: [0, 0.55, 1],
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          Semantics(
            container: true,
            label: top.isEmpty ? l10n.rankingsPodiumAwaiting : top.map((r) => '${r.rank}. ${r.name}, ${l10n.rankingsPointsValue(r.points)}').join('; '),
            child: ExcludeSemantics(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(child: _Step(place: 2, color: RColors.silver, height: 86, row: at(1))),
                  const SizedBox(width: RSpace.sm),
                  Expanded(child: _Step(place: 1, color: RColors.gold, height: 120, crown: true, row: at(0))),
                  const SizedBox(width: RSpace.sm),
                  Expanded(child: _Step(place: 3, color: RColors.bronze, height: 64, row: at(2))),
                ],
              ),
            ),
          ),
          if (top.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: RSpace.md),
              decoration: const BoxDecoration(border: Border(top: BorderSide(color: RColors.border))),
              child: ExcludeSemantics(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(CupertinoIcons.hourglass, size: 14, color: RColors.textSecondary),
                    const SizedBox(width: RSpace.xs),
                    Text(l10n.rankingsPodiumAwaiting, style: RType.footnote.copyWith(color: RColors.textSecondary)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({required this.place, required this.color, required this.height, this.crown = false, this.row});

  final int place;
  final Color color;
  final double height;
  final bool crown;
  final LeaderboardRow? row;

  @override
  Widget build(BuildContext context) {
    final r = row;
    final size = crown ? 64.0 : 52.0;
    final seat = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: color.withValues(alpha: 0.10),
        border: Border.all(color: color.withValues(alpha: 0.9), width: 2),
        boxShadow: [BoxShadow(color: color.withValues(alpha: 0.25), blurRadius: 20)],
      ),
      alignment: Alignment.center,
      child: r == null
          ? Text('?', style: RType.title2.copyWith(color: color))
          : Padding(padding: const EdgeInsets.all(2), child: RAvatar(name: r.name, imageUrl: r.avatarUrl, size: size - 6)),
    );
    final content = Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (crown) ...[Icon(CupertinoIcons.star_fill, color: color, size: 22), const SizedBox(height: RSpace.xs)],
          seat,
          if (r != null) ...[
            const SizedBox(height: RSpace.xs),
            Text(r.name, style: RType.footnote.copyWith(fontWeight: FontWeight.w700), maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
            Text(context.l10n.rankingsPointsValue(r.points), style: RType.caption1.copyWith(color: color, fontWeight: FontWeight.w700)),
          ],
          const SizedBox(height: RSpace.sm),
          TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: height),
            duration: RMotion.of(context, Duration(milliseconds: 700 + place * 120)),
            curve: Curves.easeOutBack,
            builder: (context, h, _) => Container(
              height: h.clamp(0, 400),
              decoration: BoxDecoration(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(RRadius.sm)),
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [color.withValues(alpha: 0.55), color.withValues(alpha: 0.12)],
                ),
                border: Border.all(color: color.withValues(alpha: 0.6), width: 1),
              ),
              alignment: Alignment.topCenter,
              padding: const EdgeInsets.only(top: RSpace.sm),
              // Tied players share a rank, so the step shows the real rank, not just the podium slot.
              child: h > 30 ? Text('${r?.rank ?? place}', style: RType.title1.copyWith(color: RColors.textPrimary)) : null,
            ),
          ),
        ],
      );
    // Empty seats are not buttons (and must not look disabled).
    if (r == null) return content;
    return RPressable(onPressed: () => context.push(Routes.player(r.username)), pressedScale: 0.95, minSize: 0, child: content);
  }
}

class _HowItWorks extends StatelessWidget {
  const _HowItWorks({required this.items});

  final List<(IconData, String, String, Color)> items;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final (icon, title, body, color) in items)
          Padding(
            padding: const EdgeInsets.only(bottom: RSpace.sm),
            child: RCard(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(RRadius.sm)),
                    alignment: Alignment.center,
                    child: Icon(icon, color: color, size: 20),
                  ),
                  const SizedBox(width: RSpace.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(title, style: RType.headline),
                        const SizedBox(height: RSpace.xxs),
                        Text(body, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
