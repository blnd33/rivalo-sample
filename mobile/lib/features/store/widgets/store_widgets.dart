import 'dart:math' as math;

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/store.dart';

String storeCategoryLabel(BuildContext context, String c) => switch (c) {
      'jerseys' => context.l10n.storeCatJerseys,
      'apparel' => context.l10n.storeCatApparel,
      'accessories' => context.l10n.storeCatAccessories,
      'equipment' => context.l10n.storeCatEquipment,
      _ => c,
    };

IconData storeCategoryIcon(String? c) => switch (c) {
      'jerseys' => Icons.sports_soccer_rounded,
      'apparel' => Icons.checkroom_rounded,
      'accessories' => CupertinoIcons.bag_fill,
      'equipment' => CupertinoIcons.sportscourt_fill,
      _ => CupertinoIcons.square_grid_2x2_fill,
    };

StatusView orderStatusView(BuildContext context, String status) {
  final l = context.l10n;
  return switch (status) {
    'placed' => (label: l.orderStatusPlaced, tone: RTone.warning),
    'confirmed' => (label: l.orderStatusConfirmed, tone: RTone.info),
    'ready' => (label: l.orderStatusReady, tone: RTone.accent),
    'out_for_delivery' => (label: l.orderStatusOutForDelivery, tone: RTone.accent),
    'completed' => (label: l.orderStatusCompleted, tone: RTone.success),
    _ => (label: l.orderStatusCancelled, tone: RTone.neutral),
  };
}

/// Product photo, or a drawn illustration when there is none (never presented as a photo).
class ProductImage extends StatelessWidget {
  const ProductImage({super.key, required this.url, required this.category, this.sportIcon, this.seed = 0, this.radius = RRadius.lg});

  final String? url;
  final String category;
  final String? sportIcon;
  final int seed;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final art = ProductArt(category: category, sportIcon: sportIcon, seed: seed);
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: url == null || url!.isEmpty
          ? art
          : Stack(fit: StackFit.expand, children: [
              art,
              Image.network(url!, fit: BoxFit.cover, excludeFromSemantics: true, errorBuilder: (_, _, _) => const SizedBox.shrink()),
            ]),
    );
  }
}

/// Illustration for products without a photo: a jersey or T-shirt drawn in the brand colours,
/// or the category symbol on a lit gradient.
class ProductArt extends StatelessWidget {
  const ProductArt({super.key, required this.category, this.sportIcon, this.seed = 0});

  final String category;
  final String? sportIcon;
  final int seed;

  @override
  Widget build(BuildContext context) {
    final rnd = math.Random(seed);
    final hue = [const Color(0xFF1F2B45), const Color(0xFF182238), const Color(0xFF243156)][rnd.nextInt(3)];
    final shirt = category == 'jerseys' || category == 'apparel';
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: RadialGradient(center: const Alignment(-0.4, -0.6), radius: 1.2, colors: [hue.withValues(alpha: 1), RColors.backgroundDeep]),
      ),
      child: Center(
        child: FractionallySizedBox(
          widthFactor: 0.62,
          heightFactor: 0.62,
          child: shirt
              ? CustomPaint(painter: _ShirtPainter(jersey: category == 'jersey' || category == 'jerseys'))
              : FittedBox(child: Icon(storeCategoryIcon(category), color: RColors.accent.withValues(alpha: 0.9))),
        ),
      ),
    );
  }
}

class _ShirtPainter extends CustomPainter {
  const _ShirtPainter({required this.jersey});

  final bool jersey;

  @override
  void paint(Canvas canvas, Size s) {
    final w = s.width, h = s.height;
    final body = Path()
      ..moveTo(w * 0.32, h * 0.08)
      ..quadraticBezierTo(w * 0.5, h * 0.2, w * 0.68, h * 0.08)
      ..lineTo(w * 0.98, h * 0.26)
      ..lineTo(w * 0.86, h * 0.44)
      ..lineTo(w * 0.76, h * 0.38)
      ..lineTo(w * 0.76, h * 0.96)
      ..lineTo(w * 0.24, h * 0.96)
      ..lineTo(w * 0.24, h * 0.38)
      ..lineTo(w * 0.14, h * 0.44)
      ..lineTo(w * 0.02, h * 0.26)
      ..close();
    canvas.drawShadow(body, Colors.black, 10, true);
    canvas.drawPath(
      body,
      Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: jersey ? [RColors.accent, const Color(0xFF9BD122)] : [const Color(0xFF2A3650), const Color(0xFF1F2B45)],
        ).createShader(Offset.zero & s),
    );
    if (jersey) {
      // Side stripes and a big "R" crest, like a match shirt.
      final stripe = Paint()..color = RColors.background.withValues(alpha: 0.85);
      canvas.drawRect(Rect.fromLTWH(w * 0.24, h * 0.38, w * 0.05, h * 0.58), stripe);
      canvas.drawRect(Rect.fromLTWH(w * 0.71, h * 0.38, w * 0.05, h * 0.58), stripe);
    }
    final tp = TextPainter(
      text: TextSpan(text: 'R', style: TextStyle(fontSize: h * 0.34, fontWeight: FontWeight.w900, color: jersey ? RColors.background : RColors.accent)),
      textDirection: TextDirection.ltr,
    )..layout();
    tp.paint(canvas, Offset(w * 0.5 - tp.width / 2, h * 0.42));
    canvas.drawPath(body, Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2
      ..color = Colors.white.withValues(alpha: 0.12));
  }

  @override
  bool shouldRepaint(covariant _ShirtPainter old) => old.jersey != jersey;
}

/// Grid card: square image, name, price, "Sold out" when nothing is left.
class ProductCard extends StatelessWidget {
  const ProductCard({super.key, required this.product, this.seed = 0});

  final StoreProduct product;
  final int seed;

  @override
  Widget build(BuildContext context) {
    final p = product;
    final l10n = context.l10n;
    final price = formatMoney(context, p.priceMinor, p.currency);
    return RPressable(
      onPressed: () => context.push(Routes.product(p.slug)),
      semanticLabel: [p.name, p.priceVaries ? l10n.storePriceFrom(price) : price, if (p.seller != null) l10n.storeSoldBy(p.seller!.name), if (p.soldOut) l10n.storeSoldOut].join('. '),
      pressedScale: 0.97,
      child: ExcludeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1,
              child: Stack(fit: StackFit.expand, children: [
                ProductImage(url: p.images.firstOrNull, category: p.category, sportIcon: p.sportIcon, seed: seed),
                if (p.soldOut)
                  PositionedDirectional(
                    top: RSpace.sm,
                    start: RSpace.sm,
                    child: RStatusPill(label: l10n.storeSoldOut, tone: RTone.neutral),
                  ),
              ]),
            ),
            const SizedBox(height: RSpace.sm),
            Text(p.name, style: RType.subheadline.copyWith(fontWeight: FontWeight.w600), maxLines: 2, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 2),
            Text(p.priceVaries ? l10n.storePriceFrom(price) : price, style: RType.footnote.copyWith(color: RColors.accent, fontWeight: FontWeight.w700)),
            if (p.seller != null)
              Text(l10n.storeSoldBy(p.seller!.name), style: RType.caption1.copyWith(color: RColors.textTertiary), maxLines: 1, overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
}

/// Bag button with item count, for the store's navigation bar.
class BagButton extends ConsumerWidget {
  const BagButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final count = ref.watch(bagProvider).value?.itemCount ?? 0;
    final l10n = context.l10n;
    return Semantics(
      button: true,
      label: count > 0 ? l10n.bagWithCount(count) : l10n.bagTitle,
      excludeSemantics: true,
      child: RPressable(
        onPressed: () => context.push(Routes.bag),
        pressedScale: 0.9,
        child: SizedBox(
          width: 44,
          height: 44,
          child: Stack(alignment: Alignment.center, clipBehavior: Clip.none, children: [
            Icon(count > 0 ? CupertinoIcons.bag_fill : CupertinoIcons.bag, color: RColors.accent, size: 24),
            if (count > 0)
              PositionedDirectional(
                top: 6,
                end: 2,
                child: Container(
                  constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  decoration: BoxDecoration(color: RColors.accent, borderRadius: BorderRadius.circular(RRadius.pill), border: Border.all(color: RColors.background, width: 1.5)),
                  alignment: Alignment.center,
                  child: Text(count > 99 ? '99+' : '$count', style: RType.caption2.copyWith(color: RColors.onAccent, fontWeight: FontWeight.w800)),
                ),
              ),
          ]),
        ),
      ),
    );
  }
}

/// − quantity + with a minimum of 1 (removing is a separate action).
class QuantityStepper extends StatelessWidget {
  const QuantityStepper({super.key, required this.value, required this.max, required this.onChanged, this.label});

  final int value;
  final int max;
  final ValueChanged<int>? onChanged;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    Widget button(IconData icon, String semantic, int? next) => IconButton(
          tooltip: semantic,
          onPressed: onChanged == null || next == null ? null : () => onChanged!(next),
          icon: Icon(icon, size: 18),
          color: RColors.accent,
          disabledColor: RColors.textTertiary,
          visualDensity: VisualDensity.compact,
        );
    return Semantics(
      label: label,
      value: '$value',
      child: Container(
        decoration: BoxDecoration(color: RColors.surfaceRaised, borderRadius: BorderRadius.circular(RRadius.pill)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          button(CupertinoIcons.minus, l10n.quantityDecrease, value > 1 ? value - 1 : null),
          ConstrainedBox(
            constraints: const BoxConstraints(minWidth: 28),
            child: Text('$value', textAlign: TextAlign.center, style: RType.headline),
          ),
          button(CupertinoIcons.plus, l10n.quantityIncrease, value < max ? value + 1 : null),
        ]),
      ),
    );
  }
}

/// Who sells a product: an approved seller (opens their shop) or official RIVALO merchandise.
class SoldByRow extends StatelessWidget {
  const SoldByRow({super.key, required this.seller});

  final SellerRef? seller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final s = seller;
    if (s == null) {
      return RListRow(
        title: l10n.storeOfficialProduct,
        leading: const Icon(CupertinoIcons.checkmark_seal_fill, color: RColors.accent),
        showChevron: false,
      );
    }
    return RListRow(
      title: l10n.storeSoldBy(s.name),
      subtitle: l10n.storeApprovedSeller,
      leading: RAvatar(name: s.name, size: RSize.avatarSm),
      onTap: () => context.push(Routes.shop(s.slug)),
    );
  }
}

/// "Pay in cash when you collect it or when it's delivered. No card payment in the app."
class CashOnHandoverNotice extends StatelessWidget {
  const CashOnHandoverNotice({super.key});

  @override
  Widget build(BuildContext context) =>
      RNotice(message: context.l10n.storeCashNotice, icon: CupertinoIcons.money_dollar_circle, color: RColors.accent);
}
