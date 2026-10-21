import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../catalog/data/catalog.dart';
import '../../safety/data/safety.dart';
import '../../store/data/store.dart';
import '../../store/widgets/store_widgets.dart';
import '../data/selling.dart';

/// "Sell on RIVALO": how it works, the application and its status, and for approved sellers where
/// to manage their shop (the Seller Hub on the web). Money is never handled in the app.
class SellingScreen extends ConsumerWidget {
  const SellingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final status = ref.watch(sellingStatusProvider);

    return RDetailPage(
      title: l10n.sellTitle,
      child: RAsyncView<SellingStatus>(
        value: status,
        onRetry: () => ref.invalidate(sellingStatusProvider),
        data: (s) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (s.shop != null)
              _ApprovedSeller(status: s)
            else if (s.application?.status == 'pending')
              _PendingApplication(application: s.application!)
            else if (!s.open)
              REmptyState(icon: CupertinoIcons.bag, title: l10n.sellClosedTitle, message: l10n.sellClosedBody)
            else
              _Intro(status: s),
            const SizedBox(height: RSpace.lg),
            RGroupedSection(
              children: [
                RListRow(
                  title: l10n.sellReadTerms,
                  leading: const Icon(CupertinoIcons.doc_text),
                  onTap: () => context.push(Routes.policy(PolicySlugs.sellerTerms)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Intro extends StatelessWidget {
  const _Intro({required this.status});

  final SellingStatus status;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final rejected = status.application?.status == 'rejected' ? status.application : null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (rejected != null) ...[
          RNotice(
            title: l10n.sellRejectedTitle,
            message: rejected.decisionNote ?? l10n.sellRejectedNoReason,
            icon: CupertinoIcons.exclamationmark_circle,
            color: RColors.warning,
          ),
          const SizedBox(height: RSpace.lg),
        ],
        Text(l10n.sellIntro, style: RType.body.copyWith(color: RColors.textSecondary, height: 1.45)),
        RSectionHeader(l10n.sellHowTitle),
        for (final (i, step) in [l10n.sellHow1, l10n.sellHow2, l10n.sellHow3].indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: RSpace.md),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 28,
                  height: 28,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(color: RColors.surface, shape: BoxShape.circle),
                  child: Text(
                    '${i + 1}',
                    style: RType.subheadline.copyWith(color: RColors.accent, fontWeight: FontWeight.w800),
                  ),
                ),
                const SizedBox(width: RSpace.md),
                Expanded(child: Text(step, style: RType.body)),
              ],
            ),
          ),
        const SizedBox(height: RSpace.md),
        RButton(
          label: rejected != null ? l10n.sellApplyAgain : l10n.sellApply,
          icon: CupertinoIcons.paperplane,
          onPressed: status.canApply ? () => context.push(Routes.sellApply) : null,
        ),
      ],
    );
  }
}

class _PendingApplication extends ConsumerStatefulWidget {
  const _PendingApplication({required this.application});

  final MyApplication application;

  @override
  ConsumerState<_PendingApplication> createState() => _PendingApplicationState();
}

class _PendingApplicationState extends ConsumerState<_PendingApplication> {
  bool _busy = false;

  Future<void> _withdraw() async {
    final l10n = context.l10n;
    final ok = await confirm(context, title: l10n.sellWithdrawConfirm, confirmLabel: l10n.sellWithdraw, destructive: true);
    if (!ok || !mounted) return;
    setState(() => _busy = true);
    try {
      await ref.read(sellingRepositoryProvider).withdraw();
      ref.invalidate(sellingStatusProvider);
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final a = widget.application;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        RCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              RStatusPill(label: l10n.sellPendingPill, tone: RTone.info, icon: CupertinoIcons.hourglass),
              const SizedBox(height: RSpace.md),
              Text(l10n.sellPendingTitle, style: RType.title3.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: RSpace.xs),
              Text(a.businessName, style: RType.subheadline.copyWith(color: RColors.textSecondary)),
              if (a.submittedAt != null) Text(l10n.sellSentOn(formatDate(context, a.submittedAt)), style: RType.footnote.copyWith(color: RColors.textTertiary)),
              const SizedBox(height: RSpace.md),
              Text(l10n.sellPendingBody, style: RType.body),
            ],
          ),
        ),
        const SizedBox(height: RSpace.lg),
        RButton.secondary(label: l10n.sellWithdraw, onPressed: _busy ? null : _withdraw, loading: _busy),
      ],
    );
  }
}

class _ApprovedSeller extends ConsumerWidget {
  const _ApprovedSeller({required this.status});

  final SellingStatus status;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final shop = status.shop!;
    final marketplaceOn = ref.watch(appConfigProvider).value?.marketplaceEnabled ?? false;
    final (pill, tone) = switch (shop.status) {
      'suspended' => (l10n.sellShopPaused, RTone.warning),
      'closed' => (l10n.sellShopClosed, RTone.neutral),
      _ => (l10n.storeApprovedSeller, RTone.success),
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        RCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  RAvatar(name: shop.name, size: RSize.avatarMd),
                  const SizedBox(width: RSpace.md),
                  Expanded(
                    child: Text(l10n.sellApprovedTitle(shop.name), style: RType.title3.copyWith(fontWeight: FontWeight.w700)),
                  ),
                ],
              ),
              const SizedBox(height: RSpace.md),
              RStatusPill(label: pill, tone: tone),
              if (shop.statusReason != null && shop.statusReason!.trim().isNotEmpty) ...[
                const SizedBox(height: RSpace.sm),
                Text(shop.statusReason!, style: RType.footnote.copyWith(color: RColors.textSecondary)),
              ],
            ],
          ),
        ),
        RGroupedSection(
          header: l10n.sellHubTitle,
          footer: l10n.sellHubBody,
          children: [
            RListRow(
              title: l10n.sellHubAddress,
              subtitle: status.hubUrl,
              leading: const Icon(CupertinoIcons.desktopcomputer),
              trailing: IconButton(
                tooltip: l10n.sellCopyAddress,
                icon: const Icon(CupertinoIcons.doc_on_doc, color: RColors.accent),
                onPressed: () {
                  Clipboard.setData(ClipboardData(text: status.hubUrl));
                  HapticFeedback.selectionClick();
                  showToast(context, l10n.sellCopied);
                },
              ),
            ),
            if (marketplaceOn && shop.status == 'active')
              RListRow(title: l10n.sellViewShop, leading: const Icon(CupertinoIcons.bag), onTap: () => context.push(Routes.shop(shop.slug))),
          ],
        ),
      ],
    );
  }
}

/// Full-screen application form.
class SellApplyScreen extends ConsumerStatefulWidget {
  const SellApplyScreen({super.key});

  @override
  ConsumerState<SellApplyScreen> createState() => _SellApplyScreenState();
}

class _SellApplyScreenState extends ConsumerState<SellApplyScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _city = TextEditingController();
  final _description = TextEditingController();
  final _link = TextEditingController();
  final Set<String> _categories = {};
  final Map<String, String?> _errors = {};
  String? _formError;
  bool _busy = false;

  @override
  void dispose() {
    for (final c in [_name, _phone, _city, _description, _link]) {
      c.dispose();
    }
    super.dispose();
  }

  bool _validate() {
    final l10n = context.l10n;
    _errors
      ..clear()
      ..['business_name'] = _name.text.trim().length < 2 ? l10n.validationRequired : null
      ..['contact_phone'] = RegExp(r'^[0-9+()\s-]{6,32}$').hasMatch(_phone.text.trim()) ? null : l10n.sellPhoneInvalid
      ..['city'] = _city.text.trim().length < 2 ? l10n.validationRequired : null
      ..['categories'] = _categories.isEmpty ? l10n.sellCategoriesRequired : null
      ..['description'] = _description.text.trim().length < 20 ? l10n.sellDescriptionTooShort : null;
    _errors.removeWhere((_, v) => v == null);
    return _errors.isEmpty;
  }

  Future<void> _submit() async {
    final l10n = context.l10n;
    setState(() => _formError = null);
    if (!_validate()) {
      setState(() {});
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(sellingRepositoryProvider)
          .apply(
            SellerApplicationRequest(
              businessName: _name.text,
              contactPhone: _phone.text,
              city: _city.text,
              categories: _categories.toList(),
              description: _description.text,
              link: _link.text,
            ),
          );
      ref.invalidate(sellingStatusProvider);
      HapticFeedback.mediumImpact();
      if (!mounted) return;
      showToast(context, l10n.sellSubmitted);
      if (context.canPop()) context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        for (final f in ['business_name', 'contact_phone', 'city', 'categories', 'description', 'link']) {
          final error = e.fieldError(f);
          if (error != null) _errors[f] = error;
        }
        _formError = _errors.isEmpty ? errorMessage(context, e) : null;
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return RDetailPage(
      title: l10n.sellApplyTitle,
      bottomBar: RButton(label: l10n.sellSubmit, icon: CupertinoIcons.paperplane, onPressed: _busy ? null : _submit, loading: _busy),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_formError != null) ...[
            RNotice(message: _formError!, icon: CupertinoIcons.exclamationmark_circle, color: RColors.error),
            const SizedBox(height: RSpace.lg),
          ],
          RTextField(
            label: l10n.sellBusinessName,
            controller: _name,
            errorText: _errors['business_name'],
            maxLength: 120,
            textCapitalization: TextCapitalization.words,
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: RSpace.xl),
          RTextField(
            label: l10n.sellPhone,
            helper: l10n.sellPhoneHelper,
            controller: _phone,
            errorText: _errors['contact_phone'],
            keyboardType: TextInputType.phone,
            autofillHints: const [AutofillHints.telephoneNumber],
            maxLength: 32,
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: RSpace.xl),
          RTextField(
            label: l10n.sellCity,
            controller: _city,
            errorText: _errors['city'],
            maxLength: 80,
            textCapitalization: TextCapitalization.words,
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: RSpace.xl),
          RGroupedSection(
            header: l10n.sellCategories,
            footer: _errors['categories'],
            children: [
              for (final c in storeCategories)
                Semantics(
                  selected: _categories.contains(c),
                  child: RListRow(
                    title: storeCategoryLabel(context, c),
                    leading: Icon(storeCategoryIcon(c)),
                    trailing: _categories.contains(c) ? const Icon(CupertinoIcons.checkmark_alt, color: RColors.accent) : null,
                    showChevron: false,
                    onTap: () {
                      HapticFeedback.selectionClick();
                      setState(() => _categories.contains(c) ? _categories.remove(c) : _categories.add(c));
                    },
                  ),
                ),
            ],
          ),
          const SizedBox(height: RSpace.lg),
          RTextField(
            label: l10n.sellDescription,
            helper: l10n.sellDescriptionHelper,
            controller: _description,
            errorText: _errors['description'],
            maxLines: 5,
            maxLength: 1000,
            textCapitalization: TextCapitalization.sentences,
          ),
          const SizedBox(height: RSpace.xl),
          RTextField(label: l10n.sellLink, controller: _link, errorText: _errors['link'], keyboardType: TextInputType.url, maxLength: 255, autocorrect: false),
          const SizedBox(height: RSpace.xl),
          const SizedBox(height: RSpace.md),
          RNotice(message: l10n.sellTermsNotice, icon: CupertinoIcons.doc_text, color: RColors.info),
          RGroupedSection(
            children: [
              RListRow(
                title: l10n.sellReadTerms,
                leading: const Icon(CupertinoIcons.doc_text),
                onTap: () => context.push(Routes.policy(PolicySlugs.sellerTerms)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
