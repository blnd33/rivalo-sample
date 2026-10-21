import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';

/// Bottom sheet shown when a guest tries an action that needs an account
/// (register, join or create a team, invitations, settings).
Future<void> showAccountRequired(BuildContext context, {String? reason}) {
  return showModalBottomSheet<void>(
    context: context,
    useRootNavigator: true,
    useSafeArea: true,
    // Size to content and scroll on short screens or with large text.
    isScrollControlled: true,
    builder: (sheetContext) {
      final l10n = sheetContext.l10n;
      void go(String route) {
        Navigator.of(sheetContext).pop();
        context.push(route);
      }

      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(RSpace.xl, 0, RSpace.xl, RSpace.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const ExcludeSemantics(child: Icon(CupertinoIcons.person_crop_circle_badge_plus, size: 48, color: RColors.accent)),
            const SizedBox(height: RSpace.md),
            Semantics(header: true, child: Text(l10n.accountRequiredTitle, style: RType.title2, textAlign: TextAlign.center)),
            const SizedBox(height: RSpace.sm),
            Text(
              reason ?? l10n.accountRequiredGeneric,
              style: RType.body.copyWith(color: RColors.textSecondary),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: RSpace.xxl),
            RButton(label: l10n.welcomeCreateAccount, onPressed: () => go(Routes.register)),
            const SizedBox(height: RSpace.md),
            RButton.secondary(label: l10n.signInButton, onPressed: () => go(Routes.login)),
            const SizedBox(height: RSpace.sm),
            Center(child: RButton.tertiary(label: l10n.accountRequiredNotNow, onPressed: () => Navigator.of(sheetContext).pop())),
          ],
        ),
      );
    },
  );
}

/// Card used on guest screens: explains what an account unlocks, with sign-up and sign-in.
class GuestInviteCard extends StatelessWidget {
  const GuestInviteCard({super.key, this.showBenefits = true});

  final bool showBenefits;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final benefits = [
      (CupertinoIcons.flag, l10n.guestBenefitRegister),
      (CupertinoIcons.person_3, l10n.guestBenefitTeams),
      (CupertinoIcons.chart_bar, l10n.guestBenefitPoints),
      (CupertinoIcons.person_crop_circle, l10n.guestBenefitProfile),
    ];

    return RCard(
      highlight: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(header: true, child: Text(l10n.guestTitle, style: RType.headline)),
          const SizedBox(height: RSpace.xs),
          Text(l10n.guestBody, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
          if (showBenefits) ...[
            const SizedBox(height: RSpace.md),
            for (final (icon, text) in benefits)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: RSpace.xs),
                child: Row(children: [
                  ExcludeSemantics(child: Icon(icon, size: RSize.iconMd, color: RColors.accent)),
                  const SizedBox(width: RSpace.md),
                  Expanded(child: Text(text, style: RType.subheadline)),
                ]),
              ),
          ],
          const SizedBox(height: RSpace.lg),
          Row(children: [
            Expanded(child: RButton(label: l10n.welcomeCreateAccount, compact: true, onPressed: () => context.push(Routes.register))),
            const SizedBox(width: RSpace.sm),
            Expanded(child: RButton.secondary(label: l10n.signInButton, compact: true, onPressed: () => context.push(Routes.login))),
          ]),
        ],
      ),
    );
  }
}
