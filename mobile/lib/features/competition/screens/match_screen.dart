import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/competition.dart';
import '../widgets/fixtures_view.dart';

/// One match: both sides, the score, status and time. Players in the match can submit the result
/// (a referee approves it) or dispute an approved result within the dispute window.
class MatchScreen extends ConsumerWidget {
  const MatchScreen({super.key, required this.matchId});

  final int matchId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return RDetailPage(
      title: l10n.matchTitle,
      child: RAsyncView<FixtureMatch>(
        value: ref.watch(matchProvider(matchId)),
        onRetry: () => ref.invalidate(matchProvider(matchId)),
        data: (m) => _MatchBody(match: m),
      ),
    );
  }
}

class _MatchBody extends ConsumerWidget {
  const _MatchBody({required this.match});

  final FixtureMatch match;

  void _refresh(WidgetRef ref) {
    ref.invalidate(matchProvider(match.id));
    if (match.tournamentSlug != null) ref.invalidate(fixturesProvider(match.tournamentSlug!));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final m = match;
    final r = m.result;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (m.tournamentName != null)
          Text(m.tournamentName!, style: RType.subheadline.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
        const SizedBox(height: RSpace.xs),
        Text(
          [if (m.group != null) l10n.fixturesGroup(m.group!), m.label ?? l10n.fixturesRound(m.round)].join(' · '),
          style: RType.headline,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: RSpace.lg),
        RFadeIn(child: _Scoreboard(match: m)),
        const SizedBox(height: RSpace.md),
        Center(child: RStatusPill(label: matchStatusLabel(context, m), tone: m.status == 'completed' ? RTone.success : (m.status == 'ready' ? RTone.accent : RTone.neutral))),
        if (m.scheduledAt != null || m.location != null) ...[
          const SizedBox(height: RSpace.lg),
          RCard(
            child: Column(children: [
              if (m.scheduledAt != null) RInfoItem(icon: CupertinoIcons.calendar, label: l10n.detailStarts, value: formatDateTime(context, m.scheduledAt)),
              if (m.scheduledAt != null && m.location != null) const SizedBox(height: RSpace.md),
              if (m.location != null) RInfoItem(icon: CupertinoIcons.location, label: l10n.detailVenue, value: m.location!),
            ]),
          ),
        ],
        if (r != null && r.outcome != 'normal') ...[
          const SizedBox(height: RSpace.md),
          RNotice(message: _outcomeLabel(context, r.outcome), icon: CupertinoIcons.info_circle),
        ],
        if (r != null) ...[
          const SizedBox(height: RSpace.md),
          RNotice(message: l10n.matchApprovedNote, icon: CupertinoIcons.checkmark_seal_fill, color: RColors.success),
        ],
        if (r == null && m.pendingScoreLine != null) ...[
          const SizedBox(height: RSpace.md),
          RNotice(
            message: m.pendingByMe ? l10n.matchPendingMine(m.pendingScoreLine!) : l10n.matchPendingOther(m.pendingScoreLine!),
            icon: CupertinoIcons.hourglass,
            color: RColors.warning,
          ),
        ],
        if (m.openDispute) ...[
          const SizedBox(height: RSpace.md),
          RNotice(message: l10n.matchDisputeOpen, icon: CupertinoIcons.scope, color: RColors.warning),
        ],
        if (m.canSubmit) ...[
          const SizedBox(height: RSpace.xl),
          RButton(
            label: l10n.matchSubmitResult,
            icon: CupertinoIcons.square_pencil,
            onPressed: () async {
              final sent = await showModalBottomSheet<bool>(
                context: context,
                isScrollControlled: true,
                useSafeArea: true,
                backgroundColor: RColors.background,
                shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(RRadius.sheet))),
                builder: (_) => _ResultSheet(match: m),
              );
              if (sent == true) _refresh(ref);
            },
          ),
        ],
        if (m.canDispute && r != null) ...[
          const SizedBox(height: RSpace.md),
          RButton.secondary(label: l10n.matchDispute, icon: CupertinoIcons.flag, onPressed: () => _dispute(context, ref, r.id)),
          if (m.disputeWindowHours != null) ...[
            const SizedBox(height: RSpace.xs),
            Text(l10n.matchDisputeWindow(m.disputeWindowHours!), style: RType.footnote.copyWith(color: RColors.textTertiary), textAlign: TextAlign.center),
          ],
        ],
      ],
    );
  }

  Future<void> _dispute(BuildContext context, WidgetRef ref, int resultId) async {
    final l10n = context.l10n;
    final controller = TextEditingController();
    final reason = await showAdaptiveDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog.adaptive(
        title: Text(l10n.matchDispute),
        content: Material(
          color: Colors.transparent,
          child: Padding(
            padding: const EdgeInsets.only(top: RSpace.md),
            child: TextField(controller: controller, maxLines: 3, autofocus: true, decoration: InputDecoration(hintText: l10n.matchDisputeReason)),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(l10n.actionCancel)),
          TextButton(onPressed: () => Navigator.pop(ctx, controller.text.trim()), child: Text(l10n.matchDispute)),
        ],
      ),
    );
    if (reason == null || reason.isEmpty || !context.mounted) return;
    try {
      await ref.read(competitionRepositoryProvider).dispute(resultId, reason);
      HapticFeedback.mediumImpact();
      if (context.mounted) showToast(context, l10n.matchDisputeSent);
      _refresh(ref);
    } on ApiException catch (e) {
      if (context.mounted) showToast(context, errorMessage(context, e));
    }
  }
}

String _outcomeLabel(BuildContext context, String outcome) {
  final l = context.l10n;
  return switch (outcome) {
    'walkover' => l.outcomeWalkover,
    'forfeit' => l.outcomeForfeit,
    'no_show' => l.outcomeNoShow,
    'cancelled' => l.matchStatusCancelled,
    'disqualification' => 'DQ',
    _ => l.outcomeNormal,
  };
}

/// Big two-sided scoreboard: names (with initials), scores or sets, winner in lime.
class _Scoreboard extends StatelessWidget {
  const _Scoreboard({required this.match});

  final FixtureMatch match;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final m = match;
    final r = m.result;

    Widget side(int n) {
      final s = m.slot(n);
      final name = s.name ?? (m.status == 'bye' ? l10n.fixturesBye : l10n.fixturesTbd);
      return Expanded(
        child: Column(children: [
          Container(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: s.isWinner ? RColors.accent : Colors.transparent, width: 2),
            ),
            padding: const EdgeInsets.all(2),
            child: RAvatar(name: name, size: RSize.avatarLg),
          ),
          const SizedBox(height: RSpace.sm),
          Text(name,
              style: RType.headline.copyWith(color: s.isWinner ? RColors.accent : RColors.textPrimary), textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis),
          if (m.mySlot == n) Text(l10n.rankingsYou, style: RType.caption1.copyWith(color: RColors.accent)),
        ]),
      );
    }

    final scoreText = r == null
        ? '–'
        : (r.slot1 != null && r.slot2 != null ? '${r.slot1}  :  ${r.slot2}' : '');

    return Container(
      padding: const EdgeInsets.all(RSpace.lg),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(RRadius.xl),
        border: Border.all(color: RColors.border),
        gradient: const LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [RColors.surfaceRaised, RColors.surface]),
      ),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              side(1),
              Padding(
                padding: const EdgeInsets.only(top: 22),
                child: Text(scoreText, style: RType.largeTitle.copyWith(fontFeatures: const [FontFeature.tabularFigures()])),
              ),
              side(2),
            ],
          ),
          if (r != null && r.sets.isNotEmpty) ...[
            const SizedBox(height: RSpace.md),
            Wrap(
              alignment: WrapAlignment.center,
              spacing: RSpace.sm,
              children: [
                for (final (i, s) in r.sets.indexed)
                  RGlassChip(label: '${l10n.resultSet(i + 1)}  ${s[0]}-${s[1]}', background: RColors.surfaceRaised),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

// ------------------------------------------------------------------ Submit result

class _ResultSheet extends ConsumerStatefulWidget {
  const _ResultSheet({required this.match});

  final FixtureMatch match;

  @override
  ConsumerState<_ResultSheet> createState() => _ResultSheetState();
}

class _ResultSheetState extends ConsumerState<_ResultSheet> {
  String _outcome = 'normal';
  int _a = 0;
  int _b = 0;
  bool _useSets = false;
  final List<List<int>> _sets = [
    [0, 0],
  ];
  int? _winner;
  final _note = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final normal = _outcome == 'normal';
      await ref.read(competitionRepositoryProvider).submitResult(
            widget.match.id,
            outcome: _outcome,
            slot1: normal && !_useSets ? _a : null,
            slot2: normal && !_useSets ? _b : null,
            sets: normal && _useSets ? _sets : const [],
            winnerSlot: _winner,
            note: _note.text.trim().isEmpty ? null : _note.text.trim(),
          );
      HapticFeedback.mediumImpact();
      if (!mounted) return;
      showToast(context, context.l10n.resultSent);
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final m = widget.match;
    final nameA = m.slot(1).name ?? '1';
    final nameB = m.slot(2).name ?? '2';
    final normal = _outcome == 'normal';
    final level = normal && !_useSets && _a == _b;

    return Padding(
      padding: EdgeInsets.fromLTRB(RSpace.gutter(context), RSpace.lg, RSpace.gutter(context), RSpace.lg + MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(child: Container(width: 36, height: 5, decoration: BoxDecoration(color: RColors.borderStrong, borderRadius: BorderRadius.circular(RRadius.pill)))),
            const SizedBox(height: RSpace.lg),
            Text(l10n.matchSubmitResult, style: RType.title2),
            const SizedBox(height: RSpace.lg),
            if (_error != null) ...[RNotice(message: _error!, icon: CupertinoIcons.exclamationmark_circle, color: RColors.error), const SizedBox(height: RSpace.md)],
            Text(l10n.resultOutcome, style: RType.footnote.copyWith(color: RColors.textSecondary)),
            const SizedBox(height: RSpace.xs),
            SizedBox(
              width: double.infinity,
              child: CupertinoSlidingSegmentedControl<String>(
                groupValue: _outcome,
                backgroundColor: RColors.surface,
                thumbColor: RColors.surfaceRaised,
                children: {
                  'normal': _seg(l10n.outcomeNormal),
                  'walkover': _seg(l10n.outcomeWalkover),
                  'forfeit': _seg(l10n.outcomeForfeit),
                  'no_show': _seg(l10n.outcomeNoShow),
                },
                onValueChanged: (v) => setState(() {
                  HapticFeedback.selectionClick();
                  _outcome = v ?? 'normal';
                }),
              ),
            ),
            const SizedBox(height: RSpace.lg),
            if (normal) ...[
              Row(children: [
                Expanded(child: Text(l10n.resultUseSets, style: RType.body)),
                CupertinoSwitch(value: _useSets, activeTrackColor: RColors.accent, onChanged: (v) => setState(() => _useSets = v)),
              ]),
              const SizedBox(height: RSpace.md),
              if (!_useSets) ...[
                _Stepper(label: nameA, value: _a, onChanged: (v) => setState(() => _a = v)),
                const SizedBox(height: RSpace.sm),
                _Stepper(label: nameB, value: _b, onChanged: (v) => setState(() => _b = v)),
              ] else ...[
                for (var i = 0; i < _sets.length; i++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: RSpace.sm),
                    child: Row(children: [
                      SizedBox(width: 56, child: Text(l10n.resultSet(i + 1), style: RType.subheadline.copyWith(color: RColors.textSecondary))),
                      Expanded(child: _MiniStepper(value: _sets[i][0], onChanged: (v) => setState(() => _sets[i][0] = v))),
                      const SizedBox(width: RSpace.sm),
                      Expanded(child: _MiniStepper(value: _sets[i][1], onChanged: (v) => setState(() => _sets[i][1] = v))),
                    ]),
                  ),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: RButton.tertiary(label: l10n.resultAddSet, onPressed: _sets.length >= 5 ? null : () => setState(() => _sets.add([0, 0]))),
                ),
              ],
            ],
            if (!normal || level) ...[
              const SizedBox(height: RSpace.md),
              Text(normal ? l10n.resultWinnerTiebreak : l10n.resultWinner, style: RType.footnote.copyWith(color: RColors.textSecondary)),
              const SizedBox(height: RSpace.xs),
              SizedBox(
                width: double.infinity,
                child: CupertinoSlidingSegmentedControl<int>(
                  groupValue: _winner,
                  backgroundColor: RColors.surface,
                  thumbColor: RColors.accent,
                  children: {1: _seg(nameA, selected: _winner == 1), 2: _seg(nameB, selected: _winner == 2)},
                  onValueChanged: (v) => setState(() => _winner = v),
                ),
              ),
            ],
            const SizedBox(height: RSpace.lg),
            RTextField(controller: _note, label: l10n.resultNote, maxLines: 2),
            const SizedBox(height: RSpace.xl),
            RButton(label: l10n.matchSubmitResult, loading: _busy, onPressed: (!normal && _winner == null) ? null : _submit),
          ],
        ),
      ),
    );
  }

  Widget _seg(String text, {bool selected = false}) => Padding(
        padding: const EdgeInsets.symmetric(vertical: RSpace.sm, horizontal: RSpace.xs),
        child: Text(text,
            style: RType.footnote.copyWith(fontWeight: FontWeight.w600, color: selected ? RColors.onAccent : null), maxLines: 1, overflow: TextOverflow.ellipsis),
      );
}

/// Apple-style − value + stepper with a name, sized for thumbs.
class _Stepper extends StatelessWidget {
  const _Stepper({required this.label, required this.value, required this.onChanged});

  final String label;
  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsetsDirectional.fromSTEB(RSpace.lg, RSpace.xs, RSpace.xs, RSpace.xs),
      decoration: BoxDecoration(color: RColors.surface, borderRadius: BorderRadius.circular(RRadius.md), border: Border.all(color: RColors.border)),
      child: Row(children: [
        Expanded(child: Text(label, style: RType.body, maxLines: 1, overflow: TextOverflow.ellipsis)),
        _MiniStepper(value: value, onChanged: onChanged),
      ]),
    );
  }
}

class _MiniStepper extends StatelessWidget {
  const _MiniStepper({required this.value, required this.onChanged});

  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    Widget btn(IconData icon, String label, VoidCallback? onTap) => RPressable(
          onPressed: onTap == null
              ? null
              : () {
                  HapticFeedback.selectionClick();
                  onTap();
                },
          semanticLabel: label,
          pressedScale: 0.9,
          child: Container(
            width: 44,
            height: 44,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: RColors.surfaceRaised, borderRadius: BorderRadius.circular(RRadius.sm)),
            child: Icon(icon, size: 18, color: RColors.textPrimary),
          ),
        );
    return Row(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        btn(CupertinoIcons.minus, '−', value > 0 ? () => onChanged(value - 1) : null),
        SizedBox(
          width: 44,
          child: Text('$value',
              style: RType.title2.copyWith(fontFeatures: const [FontFeature.tabularFigures()]), textAlign: TextAlign.center, semanticsLabel: '$value'),
        ),
        btn(CupertinoIcons.plus, '+', value < 999 ? () => onChanged(value + 1) : null),
      ],
    );
  }
}
