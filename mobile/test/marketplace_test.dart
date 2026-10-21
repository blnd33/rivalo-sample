import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:rivalo/core/design/design_system.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/features/auth/state/session_controller.dart';
import 'package:rivalo/features/catalog/data/catalog.dart';
import 'package:rivalo/features/selling/data/selling.dart';
import 'package:rivalo/features/selling/screens/selling_screen.dart';
import 'package:rivalo/features/store/data/store.dart';
import 'package:rivalo/features/store/screens/product_screen.dart';
import 'package:rivalo/features/store/screens/shop_screen.dart';
import 'package:rivalo/features/store/widgets/store_widgets.dart';

class _Session extends SessionController {
  _Session(this.initial);

  final SessionState initial;

  @override
  SessionState build() => initial;
}

class _FakeSelling extends Fake implements SellingRepository {
  SellerApplicationRequest? sent;
  bool withdrawn = false;

  @override
  Future<SellingStatus> apply(SellerApplicationRequest request) async {
    sent = request;
    return _status(application: {'status': 'pending', 'business_name': request.businessName});
  }

  @override
  Future<SellingStatus> withdraw() async {
    withdrawn = true;
    return _status();
  }
}

const _config = AppConfig(features: {'store_enabled': true, 'marketplace_enabled': true});

Json _productJson({Map<String, dynamic>? seller}) => {
  'slug': 'maple-cue',
  'name': 'Maple cue',
  'category': 'equipment',
  'currency': 'IQD',
  'price_minor': 60000,
  'price_varies': false,
  'max_per_order': 2,
  'images': [],
  'sold_out': false,
  'seller': seller,
  'variants': [
    {'id': 7, 'label': '19 oz', 'price_minor': 60000, 'availability': 'in_stock', 'left': null},
  ],
};

SellingStatus _status({Map<String, dynamic>? seller, Map<String, dynamic>? application, bool open = true, bool canApply = true}) => SellingStatus.fromJson({
  'open': open,
  'can_apply': canApply,
  'seller': seller,
  'application': application,
  'hub_url': 'https://rivalo.example/seller',
  'currency': 'IQD',
});

// A new ProviderScope (UniqueKey) each time, so a second pumpWidget gets fresh overrides.
Widget _app(Widget home, List overrides, {List<GoRoute> extra = const [], List<GoRoute> children = const [], String initial = '/'}) => ProviderScope(
  key: UniqueKey(),
  overrides: [appConfigProvider.overrideWith((ref) async => _config), ...overrides.cast()],
  child: MaterialApp.router(
    theme: RivaloTheme.dark(),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    routerConfig: GoRouter(
      initialLocation: initial,
      routes: [
        GoRoute(path: '/', builder: (_, _) => home, routes: children),
        ...extra,
      ],
    ),
  ),
);

typedef Json = Map<String, dynamic>;

void main() {
  testWidgets('seller products say who sells them and open the shop', (tester) async {
    await tester.pumpWidget(
      _app(
        const ProductScreen(slug: 'maple-cue'),
        [
          sessionProvider.overrideWith(() => _Session(const GuestSession())),
          storeProductProvider(
            'maple-cue',
          ).overrideWith((ref) async => StoreProduct.fromJson(_productJson(seller: {'slug': 'corner-pocket', 'name': 'Corner Pocket'}))),
        ],
        extra: [
          GoRoute(
            path: '/store/s/:slug',
            builder: (_, s) => Scaffold(body: Text('shop ${s.pathParameters['slug']}')),
          ),
        ],
      ),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Sold by Corner Pocket'));
    expect(find.text('Approved seller'), findsOneWidget);
    expect(find.textContaining('RIVALO keeps the stock'), findsOneWidget);
    // The row sits under the "Add to bag" bar in the small test window: use its own tap handler.
    tester.widget<RListRow>(find.widgetWithText(RListRow, 'Sold by Corner Pocket')).onTap!();
    await tester.pumpAndSettle();
    expect(find.text('shop corner-pocket'), findsOneWidget);
  });

  testWidgets('official products are labelled as official', (tester) async {
    await tester.pumpWidget(
      _app(const ProductScreen(slug: 'maple-cue'), [
        sessionProvider.overrideWith(() => _Session(const GuestSession())),
        storeProductProvider('maple-cue').overrideWith((ref) async => StoreProduct.fromJson(_productJson())),
      ]),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Official RIVALO product'));
    expect(find.text('Official RIVALO product'), findsOneWidget);
    expect(find.textContaining('Sold by'), findsNothing);
  });

  testWidgets('product cards and the shop page show the seller', (tester) async {
    final product = StoreProduct.fromJson(_productJson(seller: {'slug': 'corner-pocket', 'name': 'Corner Pocket'}));
    await tester.pumpWidget(
      _app(const ShopScreen(slug: 'corner-pocket'), [
        sessionProvider.overrideWith(() => _Session(const GuestSession())),
        shopPageProvider('corner-pocket').overrideWith(
          (ref) async => ShopPage(
            shop: const StoreShop(slug: 'corner-pocket', name: 'Corner Pocket', bio: 'Cues and cases for local clubs.', isSampleData: true),
            products: [product],
          ),
        ),
      ]),
    );
    await tester.pumpAndSettle();

    expect(find.text('Corner Pocket'), findsWidgets);
    expect(find.text('Cues and cases for local clubs.'), findsOneWidget);
    expect(find.text('Sample data'), findsOneWidget, reason: 'Sample shops are labelled.');
    expect(find.byType(ProductCard), findsOneWidget);
    expect(find.text('Sold by Corner Pocket'), findsOneWidget);
  });

  testWidgets('apply to sell: the form checks fields, then sends the application', (tester) async {
    final selling = _FakeSelling();
    await tester.pumpWidget(
      _app(
        const Scaffold(body: Text('selling')),
        [sessionProvider.overrideWith(() => _Session(const GuestSession())), sellingRepositoryProvider.overrideWithValue(selling)],
        children: [GoRoute(path: 'apply', builder: (_, _) => const SellApplyScreen())],
        initial: '/apply',
      ),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Send application'));
    await tester.tap(find.text('Send application'));
    await tester.pumpAndSettle();
    expect(find.text('Choose at least one.'), findsOneWidget);
    expect(selling.sent, isNull);

    await tester.enterText(find.widgetWithText(TextField, 'Shop or business name'), 'Corner Pocket');
    await tester.enterText(find.widgetWithText(TextField, 'Phone number'), '+964 750 000 0000');
    await tester.enterText(find.widgetWithText(TextField, 'City'), 'Duhok');
    await tester.ensureVisible(find.text('Equipment'));
    await tester.tap(find.text('Equipment'));
    await tester.enterText(find.widgetWithText(TextField, 'Tell us about your products'), 'Billiard cues, cases and chalk for clubs.');
    await tester.ensureVisible(find.text('Send application'));
    await tester.tap(find.text('Send application'));
    await tester.pumpAndSettle();

    expect(selling.sent?.businessName, 'Corner Pocket');
    expect(selling.sent?.categories, ['equipment']);
    expect(find.text('selling'), findsOneWidget, reason: 'The form closes after sending.');
  });

  testWidgets('selling status: pending, rejected with the reason, and approved with the Seller Hub address', (tester) async {
    Future<void> show(SellingStatus s) async {
      await tester.pumpWidget(
        _app(const SellingScreen(), [sessionProvider.overrideWith(() => _Session(const GuestSession())), sellingStatusProvider.overrideWith((ref) async => s)]),
      );
      await tester.pumpAndSettle();
    }

    await show(_status(application: {'status': 'pending', 'business_name': 'Corner Pocket', 'submitted_at': '2026-10-18T10:00:00Z'}, canApply: false));
    expect(find.text('Your application is being reviewed'), findsOneWidget);
    expect(find.text('Withdraw application'), findsOneWidget);

    await show(_status(application: {'status': 'rejected', 'business_name': 'Corner Pocket', 'decision_note': 'We already stock these brands.'}));
    expect(find.text('We already stock these brands.'), findsOneWidget);
    expect(find.text('Apply again'), findsOneWidget);

    await show(_status(seller: {'slug': 'corner-pocket', 'name': 'Corner Pocket', 'status': 'active'}, canApply: false));
    expect(find.text('You sell on RIVALO as Corner Pocket'), findsOneWidget);
    expect(find.text('https://rivalo.example/seller'), findsOneWidget);
    expect(find.text('Apply to sell'), findsNothing);

    await show(_status(open: false, canApply: false));
    expect(find.text('Selling is not open yet'), findsOneWidget);
  });
}
