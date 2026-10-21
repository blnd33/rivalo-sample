import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/safety.dart';

/// Terms, Privacy or Community guidelines, as published by staff. Shows plainly when the text is
/// still pending legal review or not yet translated into the app language.
class PolicyScreen extends ConsumerWidget {
  const PolicyScreen({super.key, required this.slug});

  final String slug;

  String _fallbackTitle(BuildContext context) => switch (slug) {
        PolicySlugs.terms => context.l10n.policyTerms,
        PolicySlugs.privacy => context.l10n.policyPrivacy,
        _ => context.l10n.policyGuidelines,
      };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final page = ref.watch(policyPageProvider(slug));

    return RDetailPage(
      title: page.value?.title ?? _fallbackTitle(context),
      child: RAsyncView<PolicyPage>(
        value: page,
        onRetry: () => ref.invalidate(policyPageProvider(slug)),
        notFound: REmptyState(icon: CupertinoIcons.doc_text, title: l10n.policyNotPublishedTitle, message: l10n.policyNotPublishedBody),
        data: (p) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (p.pendingLegalReview) ...[
              RNotice(title: l10n.policyPendingTitle, message: l10n.policyPendingBody, icon: CupertinoIcons.exclamationmark_triangle, color: RColors.warning),
              const SizedBox(height: RSpace.md),
            ],
            if (p.translationPending) ...[
              RNotice(message: l10n.policyTranslationPending, icon: CupertinoIcons.globe),
              const SizedBox(height: RSpace.md),
            ],
            Text(
              l10n.policyVersion(p.version, formatDate(context, p.publishedAt)),
              style: RType.footnote.copyWith(color: RColors.textTertiary),
            ),
            const SizedBox(height: RSpace.lg),
            // English text keeps left-to-right layout inside an Arabic app.
            Directionality(
              textDirection: p.translationPending ? TextDirection.ltr : Directionality.of(context),
              child: PolicyBody(text: p.body),
            ),
          ],
        ),
      ),
    );
  }
}

/// Plain text from the admin panel: a blank line starts a paragraph; a short line without
/// end punctuation, alone or at the top of a paragraph, is a heading; lines starting with "•"
/// are bullets.
class PolicyBody extends StatelessWidget {
  const PolicyBody({super.key, required this.text});

  final String text;

  static bool _isHeading(String line) =>
      line.length <= 60 && !RegExp(r'[.:!?؟,،]$').hasMatch(line) && !line.startsWith('•');

  Widget _line(String line) => line.startsWith('•')
      ? Padding(
          padding: const EdgeInsetsDirectional.only(start: RSpace.sm, bottom: RSpace.xxs),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('•  ', style: RType.body.copyWith(color: RColors.accent)),
            Expanded(child: Text(line.substring(1).trim(), style: RType.body.copyWith(height: 1.45))),
          ]),
        )
      : Text(line, style: RType.body.copyWith(height: 1.45, color: RColors.textSecondary));

  @override
  Widget build(BuildContext context) {
    final blocks = text.replaceAll('\r\n', '\n').split(RegExp(r'\n\s*\n')).map((b) => b.trim()).where((b) => b.isNotEmpty);
    return SelectionArea(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final block in blocks)
            Padding(
              padding: const EdgeInsets.only(bottom: RSpace.md),
              child: Builder(builder: (context) {
                final lines = block.split('\n').map((l) => l.trim()).where((l) => l.isNotEmpty).toList();
                final heading = _isHeading(lines.first) ? lines.removeAt(0) : null;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (heading != null)
                      Padding(
                        padding: EdgeInsets.only(top: RSpace.sm, bottom: lines.isEmpty ? 0 : RSpace.xs),
                        child: Semantics(header: true, child: Text(heading, style: RType.headline)),
                      ),
                    for (final line in lines) _line(line),
                  ],
                );
              }),
            ),
        ],
      ),
    );
  }
}
