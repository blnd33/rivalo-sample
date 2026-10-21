import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/store.dart';
import '../widgets/store_widgets.dart';

/// My orders, newest first.
class OrdersScreen extends ConsumerWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return RDetailPage(
      title: l10n.ordersTitle,
      child: RAsyncView<List<StoreOrder>>(
        value: ref.watch(myOrdersProvider),
        onRetry: () => ref.invalidate(myOrdersProvider),
        data: (orders) => orders.isEmpty
            ? REmptyState(icon: CupertinoIcons.doc_text, title: l10n.ordersEmptyTitle, message: l10n.ordersEmptyBody, actionLabel: l10n.bagBrowse, onAction: () => context.go(Routes.store))
            : RGroupedSection(children: [
                for (final o in orders)
                  RListRow(
                    title: o.number,
                    subtitle: '${formatDate(context, o.placedAt)} · ${l10n.ordersItems(o.itemCount)} · ${formatMoney(context, o.totalMinor, o.currency)}',
                    trailing: Builder(builder: (context) {
                      final s = orderStatusView(context, o.status);
                      return RStatusPill(label: s.label, tone: s.tone);
                    }),
                    onTap: () => context.push(Routes.order(o.number)),
                  ),
              ]),
      ),
    );
  }
}

/// One order: where it stands, where to collect it or where it goes, what's in it, and the total.
class OrderScreen extends ConsumerWidget {
  const OrderScreen({super.key, required this.number, this.justPlaced = false});

  final String number;

  /// Shown right after checkout: a thank-you header instead of only the status.
  final bool justPlaced;

  Future<void> _cancel(BuildContext context, WidgetRef ref) async {
    final l10n = context.l10n;
    if (!await confirm(context, title: l10n.orderCancelTitle, message: l10n.orderCancelBody, confirmLabel: l10n.orderCancel, destructive: true)) return;
    try {
      await ref.read(storeRepositoryProvider).cancel(number);
      ref.invalidate(orderProvider(number));
      ref.invalidate(myOrdersProvider);
      ref.invalidate(storeProductsProvider);
      if (context.mounted) showToast(context, l10n.orderCancelled);
    } on ApiException catch (e) {
      if (context.mounted) showToast(context, errorMessage(context, e));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return RDetailPage(
      title: number,
      child: RAsyncView<StoreOrder>(
        value: ref.watch(orderProvider(number)),
        onRetry: () => ref.invalidate(orderProvider(number)),
        data: (o) {
          final status = orderStatusView(context, o.status);
          return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (justPlaced) ...[
              const ExcludeSemantics(child: Icon(CupertinoIcons.checkmark_seal_fill, size: 64, color: RColors.accent)),
              const SizedBox(height: RSpace.md),
              Semantics(header: true, liveRegion: true, child: Text(l10n.orderPlacedTitle, style: RType.title1, textAlign: TextAlign.center)),
              const SizedBox(height: RSpace.sm),
              Text(o.isPickup ? l10n.orderPlacedPickup : l10n.orderPlacedDelivery, style: RType.body.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
              const SizedBox(height: RSpace.xl),
            ],
            Row(children: [
              Expanded(child: Text(l10n.orderPlacedOn(formatDateTime(context, o.placedAt)), style: RType.footnote.copyWith(color: RColors.textSecondary))),
              RStatusPill(label: status.label, tone: status.tone),
            ]),
            const SizedBox(height: RSpace.lg),
            if (o.status == 'cancelled')
              RNotice(
                title: l10n.orderStatusCancelled,
                message: [o.cancelledBy == 'customer' ? l10n.orderCancelledByYou : l10n.orderCancelledByStore, ?o.cancelReason].join(' '),
                icon: CupertinoIcons.xmark_circle,
                color: RColors.textSecondary,
              )
            else
              _Timeline(order: o),
            RGroupedSection(
              header: o.isPickup ? l10n.checkoutPickup : l10n.checkoutDelivery,
              children: o.isPickup
                  ? [RListRow(title: o.pickupName ?? '—', subtitle: [?o.pickupAddress, ?o.pickupHours].join('\n'), leading: const Icon(CupertinoIcons.location_solid), showChevron: false)]
                  : [
                      RListRow(title: o.deliveryZone ?? '—', subtitle: o.deliveryEstimate, leading: const Icon(CupertinoIcons.car_detailed), showChevron: false),
                      RListRow(title: o.recipientName ?? '—', subtitle: [?o.recipientPhone, ?o.deliveryAddress].join('\n'), leading: const Icon(CupertinoIcons.person), showChevron: false),
                    ],
            ),
            RGroupedSection(
              header: l10n.orderItems,
              children: [
                for (final i in o.items)
                  RListRow(
                    title: i.productName,
                    subtitle: '${i.optionLabel} · × ${i.quantity}${i.returnedQuantity > 0 ? ' · ${l10n.orderReturned(i.returnedQuantity)}' : ''}'
                        '${i.sellerName != null ? '\n${l10n.storeSoldBy(i.sellerName!)}' : ''}',
                    value: formatMoney(context, i.lineTotalMinor, o.currency),
                    showChevron: false,
                    onTap: i.productSlug == null ? null : () => context.push(Routes.product(i.productSlug!)),
                  ),
                RListRow(title: l10n.bagSubtotal, value: formatMoney(context, o.subtotalMinor, o.currency), showChevron: false),
                if (!o.isPickup) RListRow(title: l10n.checkoutDeliveryFee, value: o.deliveryFeeMinor == 0 ? l10n.checkoutFree : formatMoney(context, o.deliveryFeeMinor, o.currency), showChevron: false),
                RListRow(title: l10n.checkoutTotal, value: formatMoney(context, o.totalMinor, o.currency), showChevron: false),
              ],
            ),
            RGroupedSection(
              header: l10n.orderPayment,
              children: [
                RListRow(
                  title: switch (o.paymentStatus) {
                    'paid' => l10n.orderPaid,
                    'refunded' => l10n.orderRefunded,
                    'partially_refunded' => l10n.orderPartlyRefunded(formatMoney(context, o.refundedMinor, o.currency)),
                    _ => o.isPickup ? l10n.checkoutPayOnPickup : l10n.checkoutPayOnDelivery,
                  },
                  subtitle: o.paymentReference == null ? null : l10n.orderReceipt(o.paymentReference!),
                  leading: const Icon(CupertinoIcons.money_dollar_circle),
                  showChevron: false,
                ),
              ],
            ),
            if (o.canCancel) ...[
              const SizedBox(height: RSpace.lg),
              RButton.destructive(label: l10n.orderCancel, onPressed: () => _cancel(context, ref)),
            ],
          ]);
        },
      ),
    );
  }
}

class _Timeline extends StatelessWidget {
  const _Timeline({required this.order});

  final StoreOrder order;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final o = order;
    final steps = [
      (l10n.orderStatusPlaced, o.placedAt),
      (l10n.orderStatusConfirmed, o.confirmedAt),
      (o.isPickup ? l10n.orderStatusReady : l10n.orderStatusOutForDelivery, o.readyAt),
      (l10n.orderStatusCompleted, o.completedAt),
    ];
    final current = steps.lastIndexWhere((s) => s.$2 != null);
    return Column(children: [
      for (final (i, (label, at)) in steps.indexed)
        Semantics(
          label: '$label${at != null ? ', ${formatDateTime(context, at)}' : ''}',
          excludeSemantics: true,
          child: IntrinsicHeight(
            child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              SizedBox(
                width: 28,
                child: Column(children: [
                  Container(
                    width: 16,
                    height: 16,
                    margin: const EdgeInsets.only(top: 2),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: i <= current ? RColors.accent : RColors.surface,
                      border: Border.all(color: i <= current ? RColors.accent : RColors.border, width: 2),
                    ),
                    child: i < current || (i == current && o.status == 'completed') ? const Icon(CupertinoIcons.checkmark, size: 10, color: RColors.onAccent) : null,
                  ),
                  if (i < steps.length - 1) Expanded(child: Container(width: 2, color: i < current ? RColors.accent : RColors.border)),
                ]),
              ),
              const SizedBox(width: RSpace.sm),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(bottom: RSpace.lg),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(label, style: RType.subheadline.copyWith(fontWeight: i == current ? FontWeight.w700 : FontWeight.w500, color: i <= current ? RColors.textPrimary : RColors.textTertiary)),
                    if (at != null) Text(formatDateTime(context, at), style: RType.caption1.copyWith(color: RColors.textTertiary)),
                  ]),
                ),
              ),
            ]),
          ),
        ),
    ]);
  }
}
