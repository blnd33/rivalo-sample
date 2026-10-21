import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/providers.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../../auth/widgets/account_required.dart';
import '../../catalog/data/catalog.dart';
import '../data/store.dart';
import '../widgets/store_widgets.dart';

/// Store tab: featured products, category filter and the product grid.
class StoreScreen extends ConsumerStatefulWidget {
  const StoreScreen({super.key});

  @override
  ConsumerState<StoreScreen> createState() => _StoreScreenState();
}

class _StoreScreenState extends ConsumerState<StoreScreen> {
  String? _category;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final online = ref.watch(connectivityProvider).value ?? true;
    final products = ref.watch(storeProductsProvider(_category));
    final signedIn = ref.watch(sessionProvider) is SignedIn;
    final marketplace = ref.watch(appConfigProvider).value?.marketplaceEnabled ?? false;

    return RLargeTitlePage(
      title: l10n.storeTitle,
      trailing: Row(mainAxisSize: MainAxisSize.min, children: [
        if (signedIn)
          IconButton(
            tooltip: l10n.ordersTitle,
            icon: const Icon(CupertinoIcons.doc_text, color: RColors.accent),
            onPressed: () => context.push(Routes.orders),
          ),
        const BagButton(),
      ]),
      onRefresh: () async {
        ref.invalidate(storeProductsProvider(_category));
        ref.invalidate(bagProvider);
        if (marketplace) ref.invalidate(storeShopsProvider);
        await ref.read(storeProductsProvider(_category).future).catchError((_) => <StoreProduct>[]);
      },
      slivers: [
        SliverList.list(children: [
          if (!online) ...[ROfflineBanner(message: l10n.offlineBanner), const SizedBox(height: RSpace.md)],
          if (_category == null)
            products.maybeWhen(
              data: (list) {
                final featured = list.where((p) => p.isFeatured).toList();
                return featured.isEmpty ? const SizedBox.shrink() : _FeaturedRail(products: featured);
              },
              orElse: () => const SizedBox.shrink(),
            ),
          _CategoryChips(selected: _category, onChanged: (c) => setState(() => _category = c)),
          const SizedBox(height: RSpace.lg),
          if (marketplace && _category == null) const _ShopsRail(),
          RAsyncView<List<StoreProduct>>(
            value: products,
            onRetry: () => ref.invalidate(storeProductsProvider(_category)),
            data: (list) {
              if (list.isEmpty) {
                return REmptyState(icon: CupertinoIcons.bag, title: l10n.storeEmptyTitle, message: l10n.storeEmptyBody);
              }
              return LayoutBuilder(builder: (context, c) {
                final columns = c.maxWidth >= 600 ? 3 : 2;
                const gap = RSpace.lg;
                final width = (c.maxWidth - gap * (columns - 1)) / columns;
                return Wrap(
                  spacing: gap,
                  runSpacing: RSpace.xl,
                  children: [
                    for (final (i, p) in list.indexed)
                      SizedBox(width: width, child: RFadeIn(index: i, child: ProductCard(product: p, seed: i))),
                  ],
                );
              });
            },
          ),
          const SizedBox(height: RSpace.xl),
          const CashOnHandoverNotice(),
          if (marketplace) ...[const SizedBox(height: RSpace.lg), _SellCard(signedIn: signedIn)],
        ]),
      ],
    );
  }
}

/// Approved sellers with something on sale. Hidden when there are none.
class _ShopsRail extends ConsumerWidget {
  const _ShopsRail();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final shops = ref.watch(storeShopsProvider).value ?? const <StoreShop>[];
    if (shops.isEmpty) return const SizedBox.shrink();
    final screen = MediaQuery.sizeOf(context).width;
    return Padding(
      padding: const EdgeInsets.only(bottom: RSpace.xl),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        RSectionHeader(context.l10n.storeShopsTitle, padding: const EdgeInsets.only(bottom: RSpace.sm)),
        LayoutBuilder(
          builder: (context, c) => SizedBox(
            height: 112,
            child: OverflowBox(
              minWidth: screen,
              maxWidth: screen,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                padding: EdgeInsets.symmetric(horizontal: (screen - c.maxWidth) / 2),
                itemCount: shops.length,
                separatorBuilder: (_, _) => const SizedBox(width: RSpace.md),
                itemBuilder: (context, i) {
                  final shop = shops[i];
                  return RPressable(
                    onPressed: () => context.push(Routes.shop(shop.slug)),
                    semanticLabel: shop.name,
                    child: SizedBox(
                      width: 92,
                      child: Column(children: [
                        RAvatar(name: shop.name, imageUrl: shop.logoUrl, size: RSize.avatarLg),
                        const SizedBox(height: RSpace.xs),
                        Text(shop.name, style: RType.caption1, maxLines: 2, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis),
                      ]),
                    ),
                  );
                },
              ),
            ),
          ),
        ),
      ]),
    );
  }
}

class _SellCard extends StatelessWidget {
  const _SellCard({required this.signedIn});

  final bool signedIn;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return RCard(
      semanticLabel: '${l10n.sellTitle}. ${l10n.sellTeaser}',
      onTap: () => signedIn ? context.push(Routes.selling) : showAccountRequired(context, reason: l10n.sellAccountRequired),
      child: Row(children: [
        const Icon(CupertinoIcons.building_2_fill, color: RColors.accent, size: RSize.iconLg),
        const SizedBox(width: RSpace.md),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l10n.sellTitle, style: RType.headline),
            const SizedBox(height: 2),
            Text(l10n.sellTeaser, style: RType.footnote.copyWith(color: RColors.textSecondary)),
          ]),
        ),
        const Icon(CupertinoIcons.chevron_forward, color: RColors.textTertiary, size: 18),
      ]),
    );
  }
}

class _FeaturedRail extends StatelessWidget {
  const _FeaturedRail({required this.products});

  final List<StoreProduct> products;

  @override
  Widget build(BuildContext context) {
    final screen = MediaQuery.sizeOf(context).width;
    return Padding(
      padding: const EdgeInsets.only(bottom: RSpace.xl),
      child: LayoutBuilder(builder: (context, c) {
        final cardWidth = (c.maxWidth * 0.82).clamp(220.0, 420.0);
        return SizedBox(
          height: cardWidth * 0.62,
          child: OverflowBox(
            minWidth: screen,
            maxWidth: screen,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              padding: EdgeInsets.symmetric(horizontal: (screen - c.maxWidth) / 2),
              itemCount: products.length,
              separatorBuilder: (_, _) => const SizedBox(width: RSpace.md),
              itemBuilder: (context, i) => SizedBox(width: cardWidth, child: _FeaturedCard(product: products[i], seed: i)),
            ),
          ),
        );
      }),
    );
  }
}

class _FeaturedCard extends StatelessWidget {
  const _FeaturedCard({required this.product, required this.seed});

  final StoreProduct product;
  final int seed;

  @override
  Widget build(BuildContext context) {
    final p = product;
    final price = formatMoney(context, p.priceMinor, p.currency);
    return RPressable(
      onPressed: () => context.push(Routes.product(p.slug)),
      semanticLabel: '${p.name}. $price',
      pressedScale: 0.97,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(RRadius.xl),
        child: Stack(fit: StackFit.expand, children: [
          ProductImage(url: p.images.firstOrNull, category: p.category, sportIcon: p.sportIcon, seed: seed + 7, radius: 0),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.transparent, Color(0xE60B1020)], stops: [0.45, 1]),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(RSpace.lg),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.end,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                RGlassChip(label: context.l10n.storeFeatured, icon: CupertinoIcons.star_fill),
                const SizedBox(height: RSpace.sm),
                Text(p.name, style: RType.title3.copyWith(fontWeight: FontWeight.w800), maxLines: 2, overflow: TextOverflow.ellipsis),
                Text(price, style: RType.subheadline.copyWith(color: RColors.accent, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ]),
      ),
    );
  }
}

class _CategoryChips extends StatelessWidget {
  const _CategoryChips({required this.selected, required this.onChanged});

  final String? selected;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final screen = MediaQuery.sizeOf(context).width;
    return LayoutBuilder(
      builder: (context, c) => SizedBox(
        height: RSize.minTouchTarget,
        child: OverflowBox(
          minWidth: screen,
          maxWidth: screen,
          child: ListView(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            padding: EdgeInsets.symmetric(horizontal: (screen - c.maxWidth) / 2),
            children: [
              _Chip(label: context.l10n.filterAll, icon: storeCategoryIcon(null), selected: selected == null, onTap: () => onChanged(null)),
              for (final cat in storeCategories)
                _Chip(label: storeCategoryLabel(context, cat), icon: storeCategoryIcon(cat), selected: selected == cat, onTap: () => onChanged(cat)),
            ],
          ),
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.icon, required this.selected, required this.onTap});

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: RSpace.sm),
      child: Semantics(
        selected: selected,
        button: true,
        label: label,
        excludeSemantics: true,
        child: RPressable(
          isButton: false,
          pressedScale: 0.94,
          onPressed: () {
            HapticFeedback.selectionClick();
            onTap();
          },
          child: AnimatedContainer(
            duration: RMotion.of(context, RMotion.base),
            curve: RMotion.standard,
            padding: const EdgeInsets.symmetric(horizontal: RSpace.md),
            decoration: BoxDecoration(
              color: selected ? RColors.accent : RColors.surface,
              borderRadius: BorderRadius.circular(RRadius.pill),
              border: Border.all(color: selected ? RColors.accent : RColors.border),
            ),
            child: Row(children: [
              Icon(icon, size: 15, color: selected ? RColors.onAccent : RColors.accent),
              const SizedBox(width: RSpace.xs + 2),
              Text(label, style: RType.subheadline.copyWith(color: selected ? RColors.onAccent : RColors.textPrimary, fontWeight: FontWeight.w600)),
            ]),
          ),
        ),
      ),
    );
  }
}
