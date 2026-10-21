import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../data/profile_repository.dart';

/// English, Arabic (right-to-left) and Kurdish Kurmanji (Latin script, left-to-right).
class LanguageScreen extends ConsumerStatefulWidget {
  const LanguageScreen({super.key});

  @override
  ConsumerState<LanguageScreen> createState() => _LanguageScreenState();
}

class _LanguageScreenState extends ConsumerState<LanguageScreen> {
  bool _saving = false;

  Future<void> _choose(String code) async {
    setState(() => _saving = true);
    try {
      final me = await ref.read(profileRepositoryProvider).update(locale: code);
      ref.read(sessionProvider.notifier).updateMe(me); // also switches the app locale
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final current = Localizations.localeOf(context).languageCode;
    Widget option(String code, String label) => Semantics(
          selected: current == code,
          child: RListRow(
            title: label,
            showChevron: false,
            trailing: current == code ? const Icon(CupertinoIcons.checkmark_alt, color: RColors.accent) : null,
            onTap: _saving || current == code ? null : () => _choose(code),
          ),
        );

    return RDetailPage(
      title: l10n.languageTitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          RGroupedSection(
            footer: '${l10n.languageArabicReview}\n${l10n.languageKurdishReview}',
            children: [
              option('en', l10n.languageEnglish),
              option('ar', l10n.languageArabic),
              option('kmr', l10n.languageKurdish),
            ],
          ),
        ],
      ),
    );
  }
}
