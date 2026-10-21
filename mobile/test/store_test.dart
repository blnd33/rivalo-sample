import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:rivalo/core/design/design_system.dart';
import 'package:rivalo/core/l10n/generated/app_localizations.dart';
import 'package:rivalo/features/auth/data/me.dart';
import 'package:rivalo/features/auth/state/session_controller.dart';
import 'package:rivalo/features/store/data/store.dart';
import 'package:rivalo/features/store/screens/bag_screen.dart';
import 'package:rivalo/features/store/screens/checkout_screen.dart';
import 'package:rivalo/features/store/screens/orders_screen.dart';
import 'package:rivalo/features/store/screens/product_screen.dart';

class _Session extends SessionController {
  _Session(this.initial);

  final SessionState initial;

  @override
  SessionState build() => initial;
}

const _me = Me(
  id: 1,
  username: 'buyer',
  displayName: 'Buyer One',
  email: 'buyer@example.test',
  emailVerified: true,
  locale: 'en',
  status: 'active',
  privacy: {},
  notificationPreferences: {},
  disciplineIds: [],
  isStaff: false,
);

class _FakeStore extends Fake implements StoreRepository {
  final List<(int, int)> quantities = [];
  CheckoutRequest? placed;

  @override
  Future<Bag> setQuantity(int variantId, int quantity) async {
    quantities.add((variantId, quantity));
    return Bag.empty;
  }

  @override
  Future<StoreOrder> checkout(CheckoutRequest request) async {
    placed = request;
    return _order();
  }
}

StoreProduct _product() => StoreProduct.fromJson({
      'slug': 'home-jersey',
      'name': 'Home jersey',
      'description': 'Match shirt.',
      'category': 'jerseys',
      'currency': 'IQD',
      'price_minor': 25000,
      'price_varies': false,
      'max_per_order': 5,
      'images': [],
      'sold_out': false,
      'variants': [
        {'id': 1, 'label': 'S', 'price_minor': 25000, 'availability': 'in_stock', 'left': null},
        {'id': 2, 'label': 'M', 'price_minor': 25000, 'availability': 'low_stock', 'left': 2},
        {'id': 3, 'label': 'XL', 'price_minor': 25000, 'availability': 'sold_out', 'left': null},
      ],
    });

Bag _bag({bool ready = true, String status = 'ok'}) => Bag.fromJson({
      'currency': 'IQD',
      'subtotal_minor': ready ? 50000 : 0,
      'ready': ready,
      'lines': [
        {'variant_id': 2, 'product_slug': 'home-jersey', 'product_name': 'Home jersey', 'option_label': 'M', 'category': 'jerseys', 'unit_price_minor': 25000, 'quantity': 2, 'line_total_minor': 50000, 'status': status, 'available': status == 'ok' ? 2 : 1},
      ],
    });

StoreOrder _order({String status = 'placed', bool canCancel = true}) => StoreOrder.fromJson({
      'number': 'ORD-000042',
      'status': status,
      'fulfillment': 'pickup',
      'pickup_location': {'name': 'Venue desk', 'address': 'Main street 1', 'opening_hours': 'Sat–Thu 16–22'},
      'currency': 'IQD',
      'subtotal_minor': 50000,
      'delivery_fee_minor': 0,
      'total_minor': 50000,
      'payment_method': 'cash_on_pickup',
      'payment_status': 'unpaid',
      'items': [
        {'product_name': 'Home jersey', 'option_label': 'M', 'unit_price_minor': 25000, 'quantity': 2, 'line_total_minor': 50000, 'returned_quantity': 0},
      ],
      'can_cancel': canCancel,
      'placed_at': '2026-10-18T10:00:00Z',
      'confirmed_at': status == 'placed' ? null : '2026-10-18T11:00:00Z',
    });

const _options = FulfillmentOptions(currency: 'IQD', pickupPoints: [PickupPoint(id: 9, name: 'Venue desk', address: 'Main street 1')], deliveryAreas: []);

Widget _app(Widget home, List overrides, {List<GoRoute> extra = const []}) => ProviderScope(
      overrides: [...overrides.cast()],
      child: MaterialApp.router(
        theme: RivaloTheme.dark(),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => home), ...extra]),
      ),
    );

void main() {
  testWidgets('product page: sold-out sizes are disabled, low stock is shown, adding goes to the bag', (tester) async {
    final store = _FakeStore();
    await tester.pumpWidget(_app(const ProductScreen(slug: 'home-jersey'), [
      sessionProvider.overrideWith(() => _Session(const SignedIn(_me))),
      storeRepositoryProvider.overrideWithValue(store),
      storeProductProvider('home-jersey').overrideWith((ref) async => _product()),
      bagProvider.overrideWith((ref) async => Bag.empty),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('25,000 IQD'), findsWidgets);
    expect(find.bySemanticsLabel('XL. Sold out'), findsOneWidget);

    await tester.tap(find.text('Add to bag'));
    await tester.pump();
    expect(find.text('Choose a size'), findsWidgets, reason: 'A size must be chosen first.');
    expect(store.quantities, isEmpty);

    await tester.ensureVisible(find.text('M'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('M'));
    await tester.pumpAndSettle();
    expect(find.text('Only 2 left'), findsOneWidget);

    await tester.tap(find.text('Add to bag'));
    await tester.pumpAndSettle();
    expect(store.quantities, [(2, 1)]);
    expect(find.text('Added to your bag.'), findsOneWidget);
  });

  testWidgets('guests are asked to create an account before adding to the bag', (tester) async {
    await tester.pumpWidget(_app(const ProductScreen(slug: 'home-jersey'), [
      sessionProvider.overrideWith(() => _Session(const GuestSession())),
      storeProductProvider('home-jersey').overrideWith((ref) async => _product()),
    ]));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('S'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('S'));
    await tester.tap(find.text('Add to bag'));
    await tester.pumpAndSettle();
    expect(find.text('Create a free account to buy from the RIVALO store.'), findsOneWidget);
  });

  testWidgets('bag warns about reduced stock and blocks checkout until fixed', (tester) async {
    await tester.pumpWidget(_app(const BagScreen(), [bagProvider.overrideWith((ref) async => _bag(ready: false, status: 'reduced_stock'))]));
    await tester.pumpAndSettle();

    expect(find.text('Only 1 left: lower the quantity'), findsOneWidget);
    expect(find.text('Something in your bag changed. Update or remove it first.'), findsOneWidget);
    final checkout = tester.widget<RButton>(find.widgetWithText(RButton, 'Checkout'));
    expect(checkout.onPressed, isNull);
  });

  testWidgets('checkout sends the total the customer saw and pays in cash on pickup', (tester) async {
    final store = _FakeStore();
    await tester.pumpWidget(_app(const CheckoutScreen(), [
      sessionProvider.overrideWith(() => _Session(const SignedIn(_me))),
      storeRepositoryProvider.overrideWithValue(store),
      bagProvider.overrideWith((ref) async => _bag()),
      fulfillmentOptionsProvider.overrideWith((ref) async => _options),
    ], extra: [
      GoRoute(path: '/store/orders/:number', builder: (_, s) => Scaffold(body: Text('order ${s.pathParameters['number']}'))),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('Pay in cash on pickup'), findsOneWidget);
    expect(find.textContaining('You pay 50,000 IQD in cash'), findsOneWidget);

    await tester.tap(find.text('Delivery'));
    await tester.pump();
    expect(find.text('Delivery is not available yet. Choose pickup.'), findsOneWidget, reason: 'No delivery areas configured.');

    await tester.ensureVisible(find.text('Place order'));
    await tester.tap(find.text('Place order'));
    await tester.pumpAndSettle();
    expect(store.placed?.fulfillment, 'pickup');
    expect(store.placed?.pickupLocationId, 9, reason: 'The only pickup point is chosen for you.');
    expect(store.placed?.expectedTotalMinor, 50000);
    expect(find.text('order ORD-000042'), findsOneWidget);
  });

  testWidgets('order page shows progress, where to collect, and lets the customer cancel early', (tester) async {
    await tester.pumpWidget(_app(const OrderScreen(number: 'ORD-000042', justPlaced: true), [
      orderProvider('ORD-000042').overrideWith((ref) async => _order(status: 'confirmed')),
    ]));
    await tester.pumpAndSettle();

    expect(find.text('Order placed'), findsWidgets);
    expect(find.text('Venue desk'), findsOneWidget);
    expect(find.bySemanticsLabel(RegExp(r'^Confirmed, ')), findsOneWidget);
    expect(find.text('Pay in cash on pickup'), findsOneWidget);
    expect(find.text('Cancel order'), findsOneWidget);
  });
}
