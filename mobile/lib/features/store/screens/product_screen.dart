import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../auth/widgets/account_required.dart';
import '../data/store.dart';
import '../widgets/store_widgets.dart';

/// Product page: photos, price, sizes with honest availability, quantity and "Add to bag".
class ProductScreen extends ConsumerWidget {
  const ProductScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final product = ref.watch(storeProductProvider(slug));
    return product.when(
      skipLoadingOnRefresh: true,
      loading: () => RDetailPage(title: '', child: RLoadingList(semanticLabel: context.l10n.loading)),
      error: (e, _) => RDetailPage(
        title: context.l10n.storeTitle,
        child: RAsyncView<StoreProduct>(value: product, onRetry: () => ref.invalidate(storeProductProvider(slug)), data: (_) => const SizedBox()),
      ),
      data: (p) => _ProductView(product: p),
    );
  }
}

class _ProductView extends ConsumerStatefulWidget {
  const _ProductView({required this.product});

  final StoreProduct product;

  @override
  ConsumerState<_ProductView> createState() => _ProductViewState();
}

class _ProductViewState extends ConsumerState<_ProductView> {
  StoreVariant? _variant;
  int _qty = 1;
  bool _busy = false;
  int _page = 0;

  StoreProduct get _p => widget.product;

  @override
  void initState() {
    super.initState();
    final available = _p.variants.where((v) => !v.soldOut).toList();
    // A single size (e.g. "One size") is chosen for you.
    if (_p.variants.length == 1 && available.isNotEmpty) _variant = available.first;
  }

  int get _max {
    final v = _variant;
    if (v == null) return 1;
    final left = v.left ?? _p.maxPerOrder;
    return left < _p.maxPerOrder ? left : _p.maxPerOrder;
  }

  Future<void> _add() async {
    final l10n = context.l10n;
    if (ref.read(sessionProvider) is! SignedIn) {
      await showAccountRequired(context, reason: l10n.storeAccountRequired);
      return;
    }
    final v = _variant;
    if (v == null) {
      showToast(context, l10n.storeChooseSize);
      return;
    }
    setState(() => _busy = true);
    try {
      final bag = ref.read(bagProvider).value ?? Bag.empty;
      final already = bag.lines.where((l) => l.variantId == v.id).fold(0, (s, l) => s + l.quantity);
      await ref.read(storeRepositoryProvider).setQuantity(v.id, already + _qty);
      ref.invalidate(bagProvider);
      HapticFeedback.mediumImpact();
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          content: Text(l10n.storeAddedToBag),
          action: SnackBarAction(label: l10n.storeViewBag, textColor: RColors.accent, onPressed: () => context.push(Routes.bag)),
        ));
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final p = _p;
    final price = formatMoney(context, _variant?.priceMinor ?? p.priceMinor, p.currency);

    return RHeroPage(
      title: p.name,
      actions: const [BagButton()],
      header: Stack(fit: StackFit.expand, children: [
        PageView.builder(
          itemCount: p.images.isEmpty ? 1 : p.images.length,
          onPageChanged: (i) => setState(() => _page = i),
          itemBuilder: (_, i) => Semantics(
            image: true,
            label: p.name,
            child: ProductImage(url: p.images.isEmpty ? null : p.images[i], category: p.category, sportIcon: p.sportIcon, radius: 0),
          ),
        ),
        if (p.images.length > 1)
          PositionedDirectional(
            bottom: RSpace.lg,
            start: 0,
            end: 0,
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              for (var i = 0; i < p.images.length; i++)
                AnimatedContainer(
                  duration: RMotion.of(context, RMotion.fast),
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  width: i == _page ? 18 : 6,
                  height: 6,
                  decoration: BoxDecoration(color: i == _page ? RColors.accent : Colors.white54, borderRadius: BorderRadius.circular(3)),
                ),
            ]),
          ),
      ]),
      bottomBar: Row(children: [
        if (_variant != null && !p.soldOut) ...[
          QuantityStepper(value: _qty.clamp(1, _max), max: _max, label: l10n.quantityLabel, onChanged: (v) => setState(() => _qty = v)),
          const SizedBox(width: RSpace.md),
        ],
        Expanded(
          child: RButton(
            label: p.soldOut ? l10n.storeSoldOut : l10n.storeAddToBag,
            icon: CupertinoIcons.bag_badge_plus,
            loading: _busy,
            onPressed: p.soldOut || _busy ? null : _add,
          ),
        ),
      ]),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(spacing: RSpace.sm, runSpacing: RSpace.sm, children: [
            RStatusPill(label: storeCategoryLabel(context, p.category), tone: RTone.neutral, icon: storeCategoryIcon(p.category)),
            if (p.sportName != null) RStatusPill(label: p.sportName!, tone: RTone.neutral, icon: CupertinoIcons.sportscourt),
          ]),
          const SizedBox(height: RSpace.md),
          Semantics(header: true, child: Text(p.name, style: RType.title1)),
          const SizedBox(height: RSpace.xs),
          Text(_variant == null && p.priceVaries ? l10n.storePriceFrom(price) : price, style: RType.title3.copyWith(color: RColors.accent, fontWeight: FontWeight.w800)),
          RSectionHeader(p.variants.length == 1 ? l10n.storeOption : l10n.storeChooseSize),
          Wrap(spacing: RSpace.sm, runSpacing: RSpace.sm, children: [
            for (final v in p.variants) _SizeChip(variant: v, selected: _variant?.id == v.id, onTap: () => setState(() {
                  _variant = v;
                  _qty = 1;
                })),
          ]),
          if (_variant?.left != null) ...[
            const SizedBox(height: RSpace.sm),
            Text(l10n.storeOnlyLeft(_variant!.left!), style: RType.footnote.copyWith(color: RColors.warning, fontWeight: FontWeight.w600)),
          ],
          const SizedBox(height: RSpace.lg),
          RGroupedSection(footer: p.seller == null ? null : l10n.storeSellerNote, children: [SoldByRow(seller: p.seller)]),
          if (p.description != null && p.description!.trim().isNotEmpty) ...[
            RSectionHeader(l10n.storeAbout),
            Text(p.description!, style: RType.body.copyWith(color: RColors.textSecondary, height: 1.45)),
          ],
          const SizedBox(height: RSpace.xl),
          const CashOnHandoverNotice(),
        ],
      ),
    );
  }
}

class _SizeChip extends StatelessWidget {
  const _SizeChip({required this.variant, required this.selected, required this.onTap});

  final StoreVariant variant;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final v = variant;
    final l10n = context.l10n;
    final fg = v.soldOut ? RColors.textTertiary : (selected ? RColors.onAccent : RColors.textPrimary);
    return Semantics(
      button: true,
      selected: selected,
      enabled: !v.soldOut,
      label: v.soldOut ? '${v.label}. ${l10n.storeSoldOut}' : v.label,
      excludeSemantics: true,
      child: RPressable(
        onPressed: v.soldOut ? null : onTap,
        isButton: false,
        pressedScale: 0.94,
        child: AnimatedContainer(
          duration: RMotion.of(context, RMotion.fast),
          constraints: const BoxConstraints(minWidth: 56, minHeight: RSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: RSpace.md),
          decoration: BoxDecoration(
            color: selected ? RColors.accent : RColors.surface,
            borderRadius: BorderRadius.circular(RRadius.md),
            border: Border.all(color: selected ? RColors.accent : RColors.border),
          ),
          // widthFactor 1: the chip is as wide as its label, not the whole row.
          child: Center(
            widthFactor: 1,
            child: Text(
              v.label,
              style: RType.headline.copyWith(color: fg, decoration: v.soldOut ? TextDecoration.lineThrough : null),
            ),
          ),
        ),
      ),
    );
  }
}
