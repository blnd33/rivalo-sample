import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/store.dart';
import '../widgets/store_widgets.dart';

/// The bag: today's prices, quantities, and anything that sold out since it was added.
class BagScreen extends ConsumerStatefulWidget {
  const BagScreen({super.key});

  @override
  ConsumerState<BagScreen> createState() => _BagScreenState();
}

class _BagScreenState extends ConsumerState<BagScreen> {
  bool _busy = false;

  Future<void> _change(Future<Bag> Function(StoreRepository r) action) async {
    setState(() => _busy = true);
    try {
      await action(ref.read(storeRepositoryProvider));
      ref.invalidate(bagProvider);
      await ref.read(bagProvider.future);
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final bag = ref.watch(bagProvider);
    final value = bag.value;

    return RDetailPage(
      title: l10n.bagTitle,
      bottomBar: value == null || value.lines.isEmpty
          ? null
          : Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Expanded(child: Text(l10n.bagSubtotal, style: RType.headline)),
                Text(formatMoney(context, value.subtotalMinor, value.currency), style: RType.headline.copyWith(color: RColors.accent)),
              ]),
              const SizedBox(height: RSpace.md),
              RButton(
                label: l10n.bagCheckout,
                icon: CupertinoIcons.arrow_right_circle,
                onPressed: value.ready && !_busy ? () => context.push(Routes.checkout) : null,
              ),
              if (!value.ready) ...[
                const SizedBox(height: RSpace.sm),
                Text(l10n.bagFixFirst, style: RType.footnote.copyWith(color: RColors.warning), textAlign: TextAlign.center),
              ],
            ]),
      child: RAsyncView<Bag>(
        value: bag,
        onRetry: () => ref.invalidate(bagProvider),
        data: (b) {
          if (b.lines.isEmpty) {
            return REmptyState(
              icon: CupertinoIcons.bag,
              title: l10n.bagEmptyTitle,
              message: l10n.bagEmptyBody,
              actionLabel: l10n.bagBrowse,
              onAction: () => context.go(Routes.store),
            );
          }
          return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            for (final (i, line) in b.lines.indexed) ...[
              if (i > 0) const Divider(height: RSpace.xl),
              _BagLineRow(line: line, currency: b.currency, busy: _busy, onQuantity: (q) => _change((r) => r.setQuantity(line.variantId, q)), onRemove: () => _change((r) => r.remove(line.variantId))),
            ],
            const SizedBox(height: RSpace.xl),
            const CashOnHandoverNotice(),
          ]);
        },
      ),
    );
  }
}

class _BagLineRow extends StatelessWidget {
  const _BagLineRow({required this.line, required this.currency, required this.busy, required this.onQuantity, required this.onRemove});

  final BagLine line;
  final String currency;
  final bool busy;
  final ValueChanged<int> onQuantity;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final unavailable = line.status == 'unavailable';
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(
        width: 84,
        height: 84,
        child: Opacity(opacity: unavailable ? 0.4 : 1, child: ProductImage(url: line.imageUrl, category: line.category, radius: RRadius.md)),
      ),
      const SizedBox(width: RSpace.md),
      Expanded(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(line.productName, style: RType.headline, maxLines: 2, overflow: TextOverflow.ellipsis),
          Text('${line.optionLabel} · ${formatMoney(context, line.unitPriceMinor, currency)}', style: RType.footnote.copyWith(color: RColors.textSecondary)),
          if (line.sellerName != null) Text(l10n.storeSoldBy(line.sellerName!), style: RType.caption1.copyWith(color: RColors.textTertiary)),
          if (line.status == 'reduced_stock')
            Padding(
              padding: const EdgeInsets.only(top: RSpace.xs),
              child: Text(l10n.bagOnlyLeft(line.available), style: RType.footnote.copyWith(color: RColors.warning, fontWeight: FontWeight.w600)),
            ),
          if (unavailable)
            Padding(
              padding: const EdgeInsets.only(top: RSpace.xs),
              child: Text(l10n.bagUnavailable, style: RType.footnote.copyWith(color: RColors.error, fontWeight: FontWeight.w600)),
            ),
          const SizedBox(height: RSpace.sm),
          Row(children: [
            if (!unavailable)
              QuantityStepper(
                value: line.quantity,
                // Reduced stock: allow stepping down to what is available.
                max: line.available < line.quantity ? line.quantity : line.available,
                label: l10n.quantityFor(line.productName),
                onChanged: busy ? null : (q) => onQuantity(line.status == 'reduced_stock' && q > line.available ? line.available : q),
              ),
            const Spacer(),
            TextButton.icon(
              onPressed: busy ? null : onRemove,
              icon: const Icon(CupertinoIcons.trash, size: 16, color: RColors.error),
              label: Text(l10n.bagRemove, style: const TextStyle(color: RColors.error)),
            ),
          ]),
        ]),
      ),
    ]);
  }
}
