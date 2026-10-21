import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../safety/data/safety.dart';

import '../../../core/design/design_system.dart';
import '../../../core/providers.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../auth/widgets/account_required.dart';

/// Profile tab while browsing as a guest: what an account unlocks, plus a local
/// language choice (guests have no server profile to store it on).
class GuestProfileView extends ConsumerWidget {
  const GuestProfileView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final current = Localizations.localeOf(context).languageCode;

    Widget language(String code, String label) => Semantics(
          selected: current == code,
          child: RListRow(
            title: label,
            showChevron: false,
            trailing: current == code ? const Icon(CupertinoIcons.checkmark_alt, color: RColors.accent) : null,
            onTap: current == code ? null : () => ref.read(localeProvider.notifier).set(Locale(code)),
          ),
        );

    return RLargeTitlePage(
      title: l10n.profileTitle,
      slivers: [
        SliverList.list(children: [
          const Center(child: RAvatar(name: '?', size: RSize.avatarLg)),
          const SizedBox(height: RSpace.xl),
          const GuestInviteCard(),
          RGroupedSection(
            header: l10n.settingsLanguage,
            children: [
              language('en', l10n.languageEnglish),
              language('ar', l10n.languageArabic),
              language('kmr', l10n.languageKurdish),
            ],
          ),
          RGroupedSection(
            header: l10n.settingsSupport,
            children: [
              RListRow(title: l10n.policyGuidelines, leading: const Icon(CupertinoIcons.person_3), onTap: () => context.push(Routes.policy(PolicySlugs.guidelines))),
              RListRow(title: l10n.policyTerms, leading: const Icon(CupertinoIcons.doc_text), onTap: () => context.push(Routes.policy(PolicySlugs.terms))),
              RListRow(title: l10n.policyPrivacy, leading: const Icon(CupertinoIcons.lock_shield), onTap: () => context.push(Routes.policy(PolicySlugs.privacy))),
            ],
          ),
          RGroupedSection(children: [
            RListRow(
              title: l10n.guestLeave,
              // Arrow points the reading direction, so it flips in Arabic.
                leading: Transform.flip(flipX: Directionality.of(context) == TextDirection.rtl, child: const Icon(CupertinoIcons.arrow_left_square)),
              showChevron: false,
              onTap: () => ref.read(sessionProvider.notifier).exitGuest(),
            ),
          ]),
          const SizedBox(height: RSpace.xxl),
          const Center(child: RivaloWordmark(height: 18, showTagline: true)),
        ]),
      ],
    );
  }
}
