import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/config/env.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../catalog/data/catalog.dart';
import '../../safety/data/safety.dart';
import '../../safety/widgets/report_sheet.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final support = ref.watch(appConfigProvider).value?.supportEmail;
    final locale = Localizations.localeOf(context).languageCode;

    return RDetailPage(
      title: l10n.settingsTitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          RGroupedSection(
            header: l10n.settingsAccount,
            children: [
              RListRow(title: l10n.settingsPrivacy, leading: const Icon(CupertinoIcons.lock_shield), onTap: () => context.push(Routes.privacy)),
              RListRow(title: l10n.settingsNotifications, leading: const Icon(CupertinoIcons.bell), onTap: () => context.push(Routes.notifications)),
              RListRow(
                title: l10n.settingsLanguage,
                leading: const Icon(CupertinoIcons.globe),
                value: switch (locale) {
                  'ar' => l10n.languageArabic,
                  'kmr' => l10n.languageKurdish,
                  _ => l10n.languageEnglish,
                },
                onTap: () => context.push(Routes.language),
              ),
              RListRow(title: l10n.settingsDevices, leading: const Icon(CupertinoIcons.device_phone_portrait), onTap: () => context.push(Routes.devices)),
              RListRow(title: l10n.settingsChangePassword, leading: const Icon(CupertinoIcons.lock_rotation), onTap: () => context.push(Routes.password)),
            ],
          ),
          RGroupedSection(
            header: l10n.settingsSafety,
            children: [
              RListRow(title: l10n.policyGuidelines, leading: const Icon(CupertinoIcons.person_3), onTap: () => context.push(Routes.policy(PolicySlugs.guidelines))),
              RListRow(title: l10n.blockedTitle, leading: const Icon(CupertinoIcons.hand_raised), onTap: () => context.push(Routes.blocked)),
              RListRow(
                title: l10n.reportAppTitle,
                leading: const Icon(CupertinoIcons.exclamationmark_bubble),
                onTap: () => showReportSheet(context, const ReportApp()),
              ),
            ],
          ),
          RGroupedSection(
            header: l10n.settingsSupport,
            footer: support == null ? l10n.settingsSupportUnavailable : null,
            children: [
              RListRow(title: l10n.policyTerms, leading: const Icon(CupertinoIcons.doc_text), onTap: () => context.push(Routes.policy(PolicySlugs.terms))),
              RListRow(title: l10n.policyPrivacy, leading: const Icon(CupertinoIcons.lock_shield), onTap: () => context.push(Routes.policy(PolicySlugs.privacy))),
              RListRow(
                title: l10n.settingsSupportContact,
                leading: const Icon(CupertinoIcons.question_circle),
                value: support,
                showChevron: false,
                onTap: support == null
                    ? null
                    : () {
                        Clipboard.setData(ClipboardData(text: support));
                        showToast(context, support);
                      },
              ),
            ],
          ),
          if (Env.showDevTools)
            RGroupedSection(
              header: l10n.settingsDeveloper,
              children: [
                RListRow(title: l10n.settingsDesignGallery, leading: const Icon(CupertinoIcons.paintbrush), onTap: () => context.push(Routes.designGallery)),
              ],
            ),
          RGroupedSection(
            children: [
              RListRow(
                title: l10n.settingsSignOut,
                // Arrow points the reading direction, so it flips in Arabic.
                leading: Transform.flip(flipX: Directionality.of(context) == TextDirection.rtl, child: const Icon(CupertinoIcons.square_arrow_right)),
                showChevron: false,
                onTap: () async {
                  if (await confirm(context, title: l10n.settingsSignOut, message: l10n.signOutConfirm, confirmLabel: l10n.settingsSignOut)) {
                    await ref.read(sessionProvider.notifier).logout();
                  }
                },
              ),
              RListRow(
                title: l10n.settingsSignOutAll,
                leading: const Icon(CupertinoIcons.device_phone_portrait),
                showChevron: false,
                onTap: () async {
                  if (await confirm(context, title: l10n.settingsSignOutAll, message: l10n.signOutAllConfirm, confirmLabel: l10n.settingsSignOutAll, destructive: true)) {
                    await ref.read(sessionProvider.notifier).logout(everywhere: true);
                  }
                },
              ),
            ],
          ),
          RGroupedSection(
            children: [
              RListRow(title: l10n.settingsDeleteAccount, destructive: true, leading: const Icon(CupertinoIcons.trash, color: RColors.error), onTap: () => context.push(Routes.deleteAccount)),
            ],
          ),
          const SizedBox(height: RSpace.xxl),
          const Center(child: RivaloWordmark(height: 18, showTagline: true)),
        ],
      ),
    );
  }
}
