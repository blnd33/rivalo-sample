import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../data/store.dart';

/// Checkout: pickup or delivery, then a summary with the exact total paid in cash at handover.
class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  String _fulfillment = 'pickup';
  int? _pickupId;
  int? _zoneId;
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _address = TextEditingController();
  final _note = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final session = ref.read(sessionProvider);
    if (session is SignedIn) {
      _name.text = session.me.displayName ?? '';
      _phone.text = session.me.phone ?? '';
    }
  }

  @override
  void dispose() {
    for (final c in [_name, _phone, _address, _note]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _place(Bag bag, FulfillmentOptions options, int total) async {
    final l10n = context.l10n;
    final delivery = _fulfillment == 'delivery';
    if (!delivery && _pickupId == null) return setState(() => _error = l10n.checkoutChoosePickup);
    if (delivery && _zoneId == null) return setState(() => _error = l10n.checkoutChooseArea);
    if (delivery && (_name.text.trim().length < 2 || _phone.text.trim().length < 6 || _address.text.trim().length < 5)) {
      return setState(() => _error = l10n.checkoutDeliveryDetails);
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final order = await ref.read(storeRepositoryProvider).checkout(CheckoutRequest(
            fulfillment: _fulfillment,
            expectedTotalMinor: total,
            pickupLocationId: delivery ? null : _pickupId,
            deliveryZoneId: delivery ? _zoneId : null,
            recipientName: delivery ? _name.text.trim() : null,
            recipientPhone: delivery ? _phone.text.trim() : null,
            deliveryAddress: delivery ? _address.text.trim() : null,
            note: _note.text,
          ));
      HapticFeedback.heavyImpact();
      ref.invalidate(bagProvider);
      ref.invalidate(myOrdersProvider);
      ref.invalidate(storeProductsProvider);
      if (mounted) context.pushReplacement(Routes.orderPlaced(order.number));
    } on ApiException catch (e) {
      // Price or stock changed: refresh the bag so the screen shows the truth.
      if (e.code == 'price_changed' || e.code == 'insufficient_stock' || e.code == 'product_unavailable') {
        ref.invalidate(bagProvider);
        ref.invalidate(fulfillmentOptionsProvider);
      }
      if (mounted) setState(() => _error = errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final bag = ref.watch(bagProvider);
    final options = ref.watch(fulfillmentOptionsProvider);

    return RDetailPage(
      title: l10n.checkoutTitle,
      child: RAsyncView<FulfillmentOptions>(
        value: options,
        onRetry: () => ref.invalidate(fulfillmentOptionsProvider),
        data: (o) => RAsyncView<Bag>(
          value: bag,
          onRetry: () => ref.invalidate(bagProvider),
          data: (b) => _form(context, b, o),
        ),
      ),
    );
  }

  Widget _form(BuildContext context, Bag b, FulfillmentOptions o) {
    final l10n = context.l10n;
    if (o.pickupPoints.length == 1) _pickupId ??= o.pickupPoints.first.id;
    final delivery = _fulfillment == 'delivery';
    final zone = o.deliveryAreas.where((z) => z.id == _zoneId).firstOrNull;
    final fee = delivery ? (zone?.feeMinor ?? 0) : 0;
    final total = b.subtotalMinor + fee;
    final canDeliver = o.deliveryAreas.isNotEmpty;

    if (b.lines.isEmpty) {
      return REmptyState(icon: CupertinoIcons.bag, title: l10n.bagEmptyTitle, message: l10n.bagEmptyBody);
    }

    Widget choice({required bool selected, required String title, String? subtitle, String? value, required VoidCallback onTap}) => Semantics(
          selected: selected,
          inMutuallyExclusiveGroup: true,
          child: RListRow(
            title: title,
            subtitle: subtitle,
            value: value,
            showChevron: false,
            leading: Icon(selected ? CupertinoIcons.largecircle_fill_circle : CupertinoIcons.circle, color: selected ? RColors.accent : RColors.textTertiary),
            onTap: onTap,
          ),
        );

    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      CupertinoSlidingSegmentedControl<String>(
        groupValue: _fulfillment,
        thumbColor: RColors.accent,
        backgroundColor: RColors.surface,
        children: {
          'pickup': Padding(
            padding: const EdgeInsets.symmetric(vertical: RSpace.sm),
            child: Text(l10n.checkoutPickup, style: RType.subheadline.copyWith(color: !delivery ? RColors.onAccent : RColors.textPrimary, fontWeight: FontWeight.w600)),
          ),
          'delivery': Padding(
            padding: const EdgeInsets.symmetric(vertical: RSpace.sm),
            child: Text(l10n.checkoutDelivery, style: RType.subheadline.copyWith(color: !canDeliver ? RColors.textTertiary : (delivery ? RColors.onAccent : RColors.textPrimary), fontWeight: FontWeight.w600)),
          ),
        },
        onValueChanged: (v) {
          if (v == 'delivery' && !canDeliver) {
            showToast(context, l10n.checkoutNoDelivery);
            return;
          }
          setState(() {
            _fulfillment = v ?? 'pickup';
            _error = null;
          });
        },
      ),
      if (!delivery)
        RGroupedSection(
          header: l10n.checkoutPickupWhere,
          footer: l10n.checkoutPickupFooter,
          children: [
            for (final p in o.pickupPoints)
              choice(
                selected: _pickupId == p.id,
                title: p.name,
                subtitle: [p.address, ?p.openingHours].join('\n'),
                onTap: () => setState(() => _pickupId = p.id),
              ),
          ],
        )
      else ...[
        RGroupedSection(
          header: l10n.checkoutDeliveryArea,
          footer: l10n.checkoutDeliveryFooter,
          children: [
            for (final z in o.deliveryAreas)
              choice(
                selected: _zoneId == z.id,
                title: z.name,
                subtitle: z.estimate,
                value: z.feeMinor == 0 ? l10n.checkoutFree : formatMoney(context, z.feeMinor, o.currency),
                onTap: () => setState(() => _zoneId = z.id),
              ),
          ],
        ),
        RTextField(label: l10n.checkoutRecipient, controller: _name, textInputAction: TextInputAction.next, autofillHints: const [AutofillHints.name]),
        const SizedBox(height: RSpace.md),
        RTextField(label: l10n.checkoutPhone, controller: _phone, keyboardType: TextInputType.phone, textInputAction: TextInputAction.next, autofillHints: const [AutofillHints.telephoneNumber]),
        const SizedBox(height: RSpace.md),
        RTextField(label: l10n.checkoutAddress, controller: _address, maxLines: 2, autofillHints: const [AutofillHints.fullStreetAddress], helper: l10n.checkoutAddressHelper),
      ],
      const SizedBox(height: RSpace.md),
      RTextField(label: l10n.checkoutNote, controller: _note, maxLines: 2, maxLength: 500),
      RGroupedSection(
        header: l10n.checkoutSummary,
        children: [
          for (final line in b.lines) RListRow(title: '${line.productName} · ${line.optionLabel}', subtitle: '× ${line.quantity}', value: formatMoney(context, line.lineTotalMinor, b.currency), showChevron: false),
          RListRow(title: l10n.bagSubtotal, value: formatMoney(context, b.subtotalMinor, b.currency), showChevron: false),
          if (delivery) RListRow(title: l10n.checkoutDeliveryFee, value: zone == null ? '—' : (fee == 0 ? l10n.checkoutFree : formatMoney(context, fee, b.currency)), showChevron: false),
          RListRow(title: l10n.checkoutTotal, value: formatMoney(context, total, b.currency), showChevron: false),
        ],
      ),
      RNotice(
        title: delivery ? l10n.checkoutPayOnDelivery : l10n.checkoutPayOnPickup,
        message: l10n.checkoutPayBody(formatMoney(context, total, b.currency)),
        icon: CupertinoIcons.money_dollar_circle,
        color: RColors.accent,
      ),
      if (_error != null) ...[
        const SizedBox(height: RSpace.md),
        Semantics(liveRegion: true, child: Text(_error!, style: RType.footnote.copyWith(color: RColors.error))),
      ],
      const SizedBox(height: RSpace.lg),
      RButton(label: l10n.checkoutPlace, icon: CupertinoIcons.checkmark_seal, loading: _busy, onPressed: _busy || !b.ready ? null : () => _place(b, o, total)),
    ]);
  }
}
