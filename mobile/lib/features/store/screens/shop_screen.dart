import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/store.dart';
import '../widgets/store_widgets.dart';

/// An approved seller's shop: who they are and what they have on sale. RIVALO keeps the stock,
/// hands it over and takes the payment, exactly as for official products.
class ShopScreen extends ConsumerWidget {
  const ShopScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final page = ref.watch(shopPageProvider(slug));
    final title = page.value?.shop.name ?? l10n.storeShopsTitle;

    return RDetailPage(
      title: title,
      actions: const [BagButton()],
      child: RAsyncView<ShopPage>(
        value: page,
        onRetry: () => ref.invalidate(shopPageProvider(slug)),
        notFound: REmptyState(icon: CupertinoIcons.bag, title: l10n.storeShopUnavailableTitle, message: l10n.storeShopUnavailableBody),
        data: (p) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _ShopHeader(shop: p.shop),
            const SizedBox(height: RSpace.lg),
            RNotice(message: l10n.storeSellerNote, icon: CupertinoIcons.checkmark_shield, color: RColors.accent),
            RSectionHeader(l10n.storeShopProducts),
            if (p.products.isEmpty)
              REmptyState(icon: CupertinoIcons.bag, title: l10n.storeShopEmpty, message: l10n.storeShopEmptyBody)
            else
              LayoutBuilder(
                builder: (context, c) {
                  final columns = c.maxWidth >= 600 ? 3 : 2;
                  const gap = RSpace.lg;
                  final width = (c.maxWidth - gap * (columns - 1)) / columns;
                  return Wrap(
                    spacing: gap,
                    runSpacing: RSpace.xl,
                    children: [
                      for (final (i, product) in p.products.indexed)
                        SizedBox(
                          width: width,
                          child: RFadeIn(
                            index: i,
                            child: ProductCard(product: product, seed: i + 3),
                          ),
                        ),
                    ],
                  );
                },
              ),
            const SizedBox(height: RSpace.xl),
            const CashOnHandoverNotice(),
          ],
        ),
      ),
    );
  }
}

class _ShopHeader extends StatelessWidget {
  const _ShopHeader({required this.shop});

  final StoreShop shop;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            RAvatar(name: shop.name, imageUrl: shop.logoUrl, size: RSize.avatarLg),
            const SizedBox(width: RSpace.lg),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Semantics(
                    header: true,
                    child: Text(shop.name, style: RType.title2.copyWith(fontWeight: FontWeight.w800)),
                  ),
                  const SizedBox(height: RSpace.xs),
                  Wrap(
                    spacing: RSpace.sm,
                    runSpacing: RSpace.xs,
                    children: [
                      RStatusPill(label: l10n.storeApprovedSeller, tone: RTone.accent, icon: CupertinoIcons.checkmark_seal_fill),
                      if (shop.isSampleData) RSampleBadge(label: l10n.sampleData),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
        if (shop.bio != null && shop.bio!.trim().isNotEmpty) ...[
          const SizedBox(height: RSpace.lg),
          Text(shop.bio!, style: RType.body.copyWith(color: RColors.textSecondary, height: 1.45)),
        ],
      ],
    );
  }
}
