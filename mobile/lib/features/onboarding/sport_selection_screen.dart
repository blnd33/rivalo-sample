import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../core/design/design_system.dart';
import '../../core/network/api_exception.dart';
import '../../core/ui/ui_helpers.dart';
import '../auth/state/session_controller.dart';
import '../catalog/data/catalog.dart';
import '../profile/data/profile_repository.dart';

/// Choose sports and games. Used for onboarding and from Profile > Sports & games.
class SportSelectionScreen extends ConsumerStatefulWidget {
  const SportSelectionScreen({super.key, required this.onboarding});

  final bool onboarding;

  @override
  ConsumerState<SportSelectionScreen> createState() => _SportSelectionScreenState();
}

class _SportSelectionScreenState extends ConsumerState<SportSelectionScreen> {
  late final Set<int> _selected = {...?ref.read(meProvider)?.disciplineIds};
  bool _saving = false;

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final me = await ref.read(profileRepositoryProvider).setDisciplines(_selected.toList());
      ref.read(sessionProvider.notifier).updateMe(me);
      if (!mounted) return;
      widget.onboarding ? context.go(Routes.home) : context.pop();
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _toggle(int id) {
    HapticFeedback.selectionClick();
    setState(() => _selected.contains(id) ? _selected.remove(id) : _selected.add(id));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final sports = ref.watch(sportsProvider);

    final content = RAsyncView(
      value: sports,
      onRetry: () => ref.invalidate(sportsProvider),
      data: (list) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l10n.onboardingBody, style: RType.body.copyWith(color: RColors.textSecondary)),
          for (final sport in list) ...[
            RSectionHeader(sport.name),
            Wrap(
              spacing: RSpace.sm,
              runSpacing: RSpace.sm,
              children: [
                for (final d in sport.disciplines)
                  _DisciplineChip(
                    label: d.name,
                    detail: d.esportsDetail,
                    icon: sport.icon,
                    selected: _selected.contains(d.id),
                    onTap: () => _toggle(d.id),
                  ),
              ],
            ),
          ],
        ],
      ),
    );

    return RDetailPage(
      title: widget.onboarding ? l10n.onboardingTitle : l10n.profileSports,
      actions: [
        if (widget.onboarding) TextButton(onPressed: () => context.go(Routes.home), child: Text(l10n.onboardingSkip, style: const TextStyle(color: RColors.accent))),
      ],
      bottomBar: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Semantics(liveRegion: true, child: Text(l10n.onboardingSelected(_selected.length), style: RType.footnote.copyWith(color: RColors.textSecondary))),
          const SizedBox(height: RSpace.sm),
          RButton(label: widget.onboarding ? l10n.onboardingSave : l10n.actionSave, onPressed: _save, loading: _saving),
        ],
      ),
      child: content,
    );
  }
}

class _DisciplineChip extends StatelessWidget {
  const _DisciplineChip({required this.label, required this.selected, required this.onTap, this.icon, this.detail = ''});

  final String label;
  final String detail;
  final String? icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final fg = selected ? RColors.onAccent : RColors.textPrimary;
    return Semantics(
      selected: selected,
      checked: selected,
      button: true,
      label: detail.isEmpty ? label : '$label, $detail',
      excludeSemantics: true,
      child: RPressable(
        onPressed: onTap,
        isButton: false,
        child: AnimatedContainer(
          duration: RMotion.of(context, RMotion.fast),
          curve: RMotion.easeOut,
          padding: const EdgeInsets.symmetric(horizontal: RSpace.md, vertical: RSpace.sm + 2),
          decoration: BoxDecoration(
            color: selected ? RColors.accent : RColors.surface,
            borderRadius: BorderRadius.circular(RRadius.pill),
            border: Border.all(color: selected ? RColors.accent : RColors.borderStrong),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(selected ? CupertinoIcons.checkmark_alt : CupertinoIcons.add, size: 16, color: fg),
              const SizedBox(width: RSpace.xs + 2),
              SportGlyph(icon, size: 18, color: fg),
              const SizedBox(width: RSpace.xs + 2),
              Flexible(child: Text(label, style: RType.subheadline.copyWith(color: fg, fontWeight: FontWeight.w600))),
            ],
          ),
        ),
      ),
    );
  }
}
