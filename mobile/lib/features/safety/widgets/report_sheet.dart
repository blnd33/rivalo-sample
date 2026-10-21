import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/env.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/safety.dart';

String reportReasonLabel(BuildContext context, String reason) {
  final l = context.l10n;
  return switch (reason) {
    'offensive_name' => l.reportReasonOffensiveName,
    'offensive_photo' => l.reportReasonOffensivePhoto,
    'harassment' => l.reportReasonHarassment,
    'cheating' => l.reportReasonCheating,
    'impersonation' => l.reportReasonImpersonation,
    'spam' => l.reportReasonSpam,
    'app_problem' => l.reportReasonAppProblem,
    'suggestion' => l.reportReasonSuggestion,
    _ => l.reportReasonOther,
  };
}

/// Report a player, a team, or a problem with the app. Reports are private: the reported player
/// is never told who reported them. Returns true when the report was sent.
Future<bool> showReportSheet(BuildContext context, ReportTarget target) async {
  final sent = await showModalBottomSheet<bool>(
    context: context,
    useRootNavigator: true,
    useSafeArea: true,
    isScrollControlled: true,
    backgroundColor: RColors.background,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(RRadius.sheet))),
    builder: (_) => _ReportSheet(target: target),
  );
  return sent ?? false;
}

class _ReportSheet extends ConsumerStatefulWidget {
  const _ReportSheet({required this.target});

  final ReportTarget target;

  @override
  ConsumerState<_ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends ConsumerState<_ReportSheet> {
  final _details = TextEditingController();
  String? _reason;
  bool _busy = false;
  bool _done = false;
  String? _error;

  ReportTarget get _t => widget.target;

  bool get _detailsRequired => _t is ReportApp || _reason == 'other';

  @override
  void initState() {
    super.initState();
    if (_t.reasons.length == 1) _reason = _t.reasons.first;
  }

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l10n = context.l10n;
    if (_reason == null) {
      setState(() => _error = l10n.reportChooseReason);
      return;
    }
    if (_detailsRequired && _details.text.trim().isEmpty) {
      setState(() => _error = l10n.reportDetailsRequired);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(safetyRepositoryProvider).report(
            _t,
            _reason!,
            details: _details.text,
            appVersion: Env.appVersion,
            platform: kIsWeb ? 'web' : defaultTargetPlatform.name,
          );
      HapticFeedback.mediumImpact();
      if (mounted) setState(() => _done = true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _title(BuildContext context) {
    final l = context.l10n;
    return switch (_t) {
      ReportPlayer(:final name) => l.reportPlayerTitle(name),
      ReportTeam(:final name) => l.reportTeamTitle(name),
      ReportApp() => l.reportAppTitle,
    };
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    if (_done) {
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(RSpace.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const ExcludeSemantics(child: Icon(CupertinoIcons.checkmark_shield_fill, size: 56, color: RColors.success)),
              const SizedBox(height: RSpace.md),
              Semantics(
                header: true,
                liveRegion: true,
                child: Text(_t is ReportApp ? l10n.reportFeedbackThanks : l10n.reportThanksTitle, style: RType.title2, textAlign: TextAlign.center),
              ),
              const SizedBox(height: RSpace.sm),
              Text(
                _t is ReportApp ? l10n.reportFeedbackThanksBody : l10n.reportThanksBody,
                style: RType.body.copyWith(color: RColors.textSecondary),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: RSpace.xl),
              RButton(label: l10n.actionDone, onPressed: () => Navigator.of(context).pop(true)),
            ],
          ),
        ),
      );
    }

    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(RSpace.xl, RSpace.lg, RSpace.xl, RSpace.xl + bottomInset),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Semantics(header: true, child: Text(_title(context), style: RType.title2)),
          const SizedBox(height: RSpace.xs),
          Text(_t is ReportApp ? l10n.reportAppIntro : l10n.reportPrivateNote, style: RType.footnote.copyWith(color: RColors.textSecondary)),
          if (_t.reasons.length > 1) ...[
            const SizedBox(height: RSpace.md),
            RGroupedSection(
              header: l10n.reportReasonHeader,
              children: [
                for (final r in _t.reasons)
                  Semantics(
                    selected: _reason == r,
                    inMutuallyExclusiveGroup: true,
                    child: RListRow(
                      title: reportReasonLabel(context, r),
                      showChevron: false,
                      trailing: _reason == r ? const Icon(CupertinoIcons.checkmark_alt, color: RColors.accent) : null,
                      onTap: () => setState(() {
                        _reason = r;
                        _error = null;
                      }),
                    ),
                  ),
              ],
            ),
          ],
          const SizedBox(height: RSpace.md),
          RTextField(
            label: _detailsRequired ? l10n.reportDetailsLabelRequired : l10n.reportDetailsLabel,
            hint: _t is ReportApp ? l10n.reportAppHint : l10n.reportDetailsHint,
            controller: _details,
            maxLines: 4,
            maxLength: 1000,
            textCapitalization: TextCapitalization.sentences,
          ),
          if (_error != null) ...[
            const SizedBox(height: RSpace.sm),
            Semantics(liveRegion: true, child: Text(_error!, style: RType.footnote.copyWith(color: RColors.error))),
          ],
          const SizedBox(height: RSpace.lg),
          RButton(label: _t is ReportApp ? l10n.reportSendFeedback : l10n.reportSend, onPressed: _busy ? null : _submit, loading: _busy),
          const SizedBox(height: RSpace.sm),
          Center(child: RButton.tertiary(label: l10n.actionCancel, onPressed: () => Navigator.of(context).pop(false))),
        ],
      ),
    );
  }
}
