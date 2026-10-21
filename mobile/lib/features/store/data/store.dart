import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';
import '../../auth/state/session_controller.dart';

const storeCategories = ['jerseys', 'apparel', 'accessories', 'equipment'];

class StoreVariant {
  const StoreVariant({required this.id, required this.label, required this.priceMinor, required this.availability, this.left});

  final int id;
  final String label;
  final int priceMinor;

  /// in_stock | low_stock | sold_out. Exact stock is never sent; [left] is set for 3 or fewer.
  final String availability;
  final int? left;

  bool get soldOut => availability == 'sold_out';

  factory StoreVariant.fromJson(Json j) => StoreVariant(
        id: j.integer('id'),
        label: j.str('label'),
        priceMinor: j.integer('price_minor'),
        availability: j.str('availability', 'sold_out'),
        left: j.intOrNull('left'),
      );
}

/// The approved seller of a product (Milestone 9). Null on a product means official RIVALO merchandise.
class SellerRef {
  const SellerRef({required this.slug, required this.name});

  final String slug;
  final String name;

  factory SellerRef.fromJson(Json j) => SellerRef(slug: j.str('slug'), name: j.str('name'));
}

/// An approved seller's public shop: name, description and logo only.
class StoreShop {
  const StoreShop({required this.slug, required this.name, this.bio, this.logoUrl, this.productCount, this.isSampleData = false});

  final String slug;
  final String name;
  final String? bio;
  final String? logoUrl;
  final int? productCount;
  final bool isSampleData;

  factory StoreShop.fromJson(Json j) => StoreShop(
        slug: j.str('slug'),
        name: j.str('name'),
        bio: j.strOrNull('bio'),
        logoUrl: j.strOrNull('logo_url'),
        productCount: j.intOrNull('product_count'),
        isSampleData: j.boolean('is_sample_data'),
      );
}

class ShopPage {
  const ShopPage({required this.shop, required this.products});

  final StoreShop shop;
  final List<StoreProduct> products;

  factory ShopPage.fromJson(Json j) => ShopPage(shop: StoreShop.fromJson(j.obj('seller')), products: j.list('products', StoreProduct.fromJson));
}

class StoreProduct {
  const StoreProduct({
    required this.slug,
    required this.name,
    required this.category,
    required this.currency,
    required this.priceMinor,
    required this.priceVaries,
    required this.images,
    required this.variants,
    required this.soldOut,
    required this.maxPerOrder,
    this.description,
    this.sportIcon,
    this.sportName,
    this.isFeatured = false,
    this.seller,
  });

  final String slug;
  final String name;
  final String? description;

  /// Null for official RIVALO products.
  final SellerRef? seller;
  final String category;
  final String currency;
  final int priceMinor;
  final bool priceVaries;
  final List<String> images;
  final List<StoreVariant> variants;
  final bool soldOut;
  final int maxPerOrder;
  final String? sportIcon;
  final String? sportName;
  final bool isFeatured;

  factory StoreProduct.fromJson(Json j) {
    final sport = j.objOrNull('sport');
    final seller = j.objOrNull('seller');
    return StoreProduct(
      slug: j.str('slug'),
      name: j.str('name'),
      description: j.strOrNull('description'),
      category: j.str('category'),
      currency: j.str('currency', 'IQD'),
      priceMinor: j.integer('price_minor'),
      priceVaries: j.boolean('price_varies'),
      images: j.list('images', (i) => i.str('url')),
      variants: j.list('variants', StoreVariant.fromJson),
      soldOut: j.boolean('sold_out'),
      maxPerOrder: j.integer('max_per_order', 5),
      sportIcon: sport?.strOrNull('icon'),
      sportName: sport?.strOrNull('name'),
      isFeatured: j.boolean('is_featured'),
      seller: seller == null ? null : SellerRef.fromJson(seller),
    );
  }
}

class BagLine {
  const BagLine({
    required this.variantId,
    required this.productSlug,
    required this.productName,
    required this.optionLabel,
    required this.category,
    required this.unitPriceMinor,
    required this.quantity,
    required this.lineTotalMinor,
    required this.status,
    required this.available,
    this.imageUrl,
    this.sellerName,
  });

  final int variantId;
  final String productSlug;
  final String productName;
  final String optionLabel;
  final String category;
  final String? imageUrl;
  final int unitPriceMinor;
  final int quantity;
  final int lineTotalMinor;

  /// Null for official RIVALO products.
  final String? sellerName;

  /// ok | reduced_stock | unavailable
  final String status;

  /// How many can be bought now (stock and per-order limit).
  final int available;

  factory BagLine.fromJson(Json j) => BagLine(
        variantId: j.integer('variant_id'),
        productSlug: j.str('product_slug'),
        productName: j.str('product_name'),
        optionLabel: j.str('option_label'),
        category: j.str('category'),
        imageUrl: j.strOrNull('image_url'),
        unitPriceMinor: j.integer('unit_price_minor'),
        quantity: j.integer('quantity'),
        lineTotalMinor: j.integer('line_total_minor'),
        status: j.str('status', 'ok'),
        available: j.integer('available'),
        sellerName: j.objOrNull('seller')?.strOrNull('name'),
      );
}

class Bag {
  const Bag({required this.lines, required this.subtotalMinor, required this.currency, required this.ready});

  final List<BagLine> lines;
  final int subtotalMinor;
  final String currency;
  final bool ready;

  int get itemCount => lines.fold(0, (sum, l) => sum + l.quantity);

  static const empty = Bag(lines: [], subtotalMinor: 0, currency: 'IQD', ready: false);

  factory Bag.fromJson(Json j) => Bag(
        lines: j.list('lines', BagLine.fromJson),
        subtotalMinor: j.integer('subtotal_minor'),
        currency: j.str('currency', 'IQD'),
        ready: j.boolean('ready'),
      );
}

class PickupPoint {
  const PickupPoint({required this.id, required this.name, required this.address, this.openingHours});

  final int id;
  final String name;
  final String address;
  final String? openingHours;

  factory PickupPoint.fromJson(Json j) =>
      PickupPoint(id: j.integer('id'), name: j.str('name'), address: j.str('address'), openingHours: j.strOrNull('opening_hours'));
}

class DeliveryArea {
  const DeliveryArea({required this.id, required this.name, required this.feeMinor, this.estimate});

  final int id;
  final String name;
  final int feeMinor;
  final String? estimate;

  factory DeliveryArea.fromJson(Json j) =>
      DeliveryArea(id: j.integer('id'), name: j.str('name'), feeMinor: j.integer('fee_minor'), estimate: j.strOrNull('estimate'));
}

class FulfillmentOptions {
  const FulfillmentOptions({required this.currency, required this.pickupPoints, required this.deliveryAreas});

  final String currency;
  final List<PickupPoint> pickupPoints;
  final List<DeliveryArea> deliveryAreas;

  factory FulfillmentOptions.fromJson(Json j) => FulfillmentOptions(
        currency: j.str('currency', 'IQD'),
        pickupPoints: j.list('pickup_locations', PickupPoint.fromJson),
        deliveryAreas: j.list('delivery_zones', DeliveryArea.fromJson),
      );
}

class OrderLine {
  const OrderLine({required this.productName, required this.optionLabel, required this.unitPriceMinor, required this.quantity, required this.lineTotalMinor, this.returnedQuantity = 0, this.imageUrl, this.productSlug, this.sellerName});

  final String productName;
  final String optionLabel;
  final int unitPriceMinor;
  final int quantity;
  final int lineTotalMinor;
  final int returnedQuantity;
  final String? imageUrl;
  final String? productSlug;

  /// Null for official RIVALO products.
  final String? sellerName;

  factory OrderLine.fromJson(Json j) => OrderLine(
        productName: j.str('product_name'),
        optionLabel: j.str('option_label'),
        unitPriceMinor: j.integer('unit_price_minor'),
        quantity: j.integer('quantity'),
        lineTotalMinor: j.integer('line_total_minor'),
        returnedQuantity: j.integer('returned_quantity'),
        imageUrl: j.strOrNull('image_url'),
        productSlug: j.strOrNull('product_slug'),
        sellerName: j.strOrNull('seller_name'),
      );
}

class StoreOrder {
  const StoreOrder({
    required this.number,
    required this.status,
    required this.fulfillment,
    required this.currency,
    required this.subtotalMinor,
    required this.deliveryFeeMinor,
    required this.totalMinor,
    required this.paymentStatus,
    required this.items,
    required this.canCancel,
    this.pickupName,
    this.pickupAddress,
    this.pickupHours,
    this.deliveryZone,
    this.deliveryEstimate,
    this.recipientName,
    this.recipientPhone,
    this.deliveryAddress,
    this.paymentReference,
    this.refundedMinor = 0,
    this.cancelReason,
    this.cancelledBy,
    this.placedAt,
    this.confirmedAt,
    this.readyAt,
    this.completedAt,
    this.cancelledAt,
  });

  final String number;

  /// placed | confirmed | ready | out_for_delivery | completed | cancelled
  final String status;

  /// pickup | delivery
  final String fulfillment;
  final String currency;
  final int subtotalMinor;
  final int deliveryFeeMinor;
  final int totalMinor;
  final String paymentStatus;
  final List<OrderLine> items;
  final bool canCancel;
  final String? pickupName;
  final String? pickupAddress;
  final String? pickupHours;
  final String? deliveryZone;
  final String? deliveryEstimate;
  final String? recipientName;
  final String? recipientPhone;
  final String? deliveryAddress;
  final String? paymentReference;
  final int refundedMinor;
  final String? cancelReason;
  final String? cancelledBy;
  final DateTime? placedAt;
  final DateTime? confirmedAt;
  final DateTime? readyAt;
  final DateTime? completedAt;
  final DateTime? cancelledAt;

  bool get isPickup => fulfillment == 'pickup';
  int get itemCount => items.fold(0, (s, i) => s + i.quantity);

  factory StoreOrder.fromJson(Json j) {
    final pickup = j.objOrNull('pickup_location');
    final delivery = j.objOrNull('delivery');
    return StoreOrder(
      number: j.str('number'),
      status: j.str('status'),
      fulfillment: j.str('fulfillment'),
      currency: j.str('currency', 'IQD'),
      subtotalMinor: j.integer('subtotal_minor'),
      deliveryFeeMinor: j.integer('delivery_fee_minor'),
      totalMinor: j.integer('total_minor'),
      paymentStatus: j.str('payment_status'),
      items: j.list('items', OrderLine.fromJson),
      canCancel: j.boolean('can_cancel'),
      pickupName: pickup?.strOrNull('name'),
      pickupAddress: pickup?.strOrNull('address'),
      pickupHours: pickup?.strOrNull('opening_hours'),
      deliveryZone: delivery?.strOrNull('zone'),
      deliveryEstimate: delivery?.strOrNull('estimate'),
      recipientName: delivery?.strOrNull('recipient_name'),
      recipientPhone: delivery?.strOrNull('recipient_phone'),
      deliveryAddress: delivery?.strOrNull('address'),
      paymentReference: j.strOrNull('payment_reference'),
      refundedMinor: j.integer('amount_refunded_minor'),
      cancelReason: j.strOrNull('cancel_reason'),
      cancelledBy: j.strOrNull('cancelled_by'),
      placedAt: j.date('placed_at'),
      confirmedAt: j.date('confirmed_at'),
      readyAt: j.date('ready_at'),
      completedAt: j.date('completed_at'),
      cancelledAt: j.date('cancelled_at'),
    );
  }
}

class CheckoutRequest {
  const CheckoutRequest({
    required this.fulfillment,
    required this.expectedTotalMinor,
    this.pickupLocationId,
    this.deliveryZoneId,
    this.recipientName,
    this.recipientPhone,
    this.deliveryAddress,
    this.note,
  });

  final String fulfillment;
  final int expectedTotalMinor;
  final int? pickupLocationId;
  final int? deliveryZoneId;
  final String? recipientName;
  final String? recipientPhone;
  final String? deliveryAddress;
  final String? note;

  Json toJson() => {
        'fulfillment': fulfillment,
        'expected_total_minor': expectedTotalMinor,
        'pickup_location_id': ?pickupLocationId,
        'delivery_zone_id': ?deliveryZoneId,
        'recipient_name': ?recipientName,
        'recipient_phone': ?recipientPhone,
        'delivery_address': ?deliveryAddress,
        if (note != null && note!.trim().isNotEmpty) 'note': note!.trim(),
      };
}

class StoreRepository {
  StoreRepository(this._api);

  final ApiClient _api;

  Future<List<StoreProduct>> products({String? category}) async =>
      (await _api.get('/store/products', query: {'category': category})).list('data', StoreProduct.fromJson);

  /// Approved sellers with something on sale (only while the marketplace is switched on).
  Future<List<StoreShop>> shops() async => (await _api.get('/store/sellers')).list('data', StoreShop.fromJson);

  Future<ShopPage> shop(String slug) async => ShopPage.fromJson((await _api.get('/store/sellers/$slug')).obj('data'));

  Future<StoreProduct> product(String slug) async => StoreProduct.fromJson((await _api.get('/store/products/$slug')).obj('data'));

  Future<FulfillmentOptions> fulfillment() async => FulfillmentOptions.fromJson((await _api.get('/store/fulfillment')).obj('data'));

  Future<Bag> bag() async => Bag.fromJson((await _api.get('/me/bag')).obj('data'));

  Future<Bag> setQuantity(int variantId, int quantity) async => Bag.fromJson((await _api.put('/me/bag/items/$variantId', body: {'quantity': quantity})).obj('data'));

  Future<Bag> remove(int variantId) async => Bag.fromJson((await _api.delete('/me/bag/items/$variantId')).obj('data'));

  Future<StoreOrder> checkout(CheckoutRequest request) async => StoreOrder.fromJson((await _api.post('/orders', body: request.toJson())).obj('data'));

  Future<List<StoreOrder>> orders() async => (await _api.get('/me/orders')).list('data', StoreOrder.fromJson);

  Future<StoreOrder> order(String number) async => StoreOrder.fromJson((await _api.get('/me/orders/$number')).obj('data'));

  Future<StoreOrder> cancel(String number) async => StoreOrder.fromJson((await _api.post('/me/orders/$number/cancel')).obj('data'));
}

final storeRepositoryProvider = Provider((ref) => StoreRepository(ref.watch(apiClientProvider)));

final storeProductsProvider = FutureProvider.autoDispose.family<List<StoreProduct>, String?>(
  (ref, category) => ref.watch(storeRepositoryProvider).products(category: category),
);

final storeShopsProvider = FutureProvider.autoDispose<List<StoreShop>>((ref) => ref.watch(storeRepositoryProvider).shops());

final shopPageProvider = FutureProvider.autoDispose.family<ShopPage, String>((ref, slug) => ref.watch(storeRepositoryProvider).shop(slug));

final storeProductProvider = FutureProvider.autoDispose.family<StoreProduct, String>((ref, slug) => ref.watch(storeRepositoryProvider).product(slug));

final fulfillmentOptionsProvider = FutureProvider.autoDispose<FulfillmentOptions>((ref) => ref.watch(storeRepositoryProvider).fulfillment());

/// The signed-in player's bag. Guests have an empty bag (adding asks them to create an account).
final bagProvider = FutureProvider<Bag>((ref) async {
  if (ref.watch(sessionProvider) is! SignedIn) return Bag.empty;
  return ref.watch(storeRepositoryProvider).bag();
});

final myOrdersProvider = FutureProvider.autoDispose<List<StoreOrder>>((ref) => ref.watch(storeRepositoryProvider).orders());

final orderProvider = FutureProvider.autoDispose.family<StoreOrder, String>((ref, number) => ref.watch(storeRepositoryProvider).order(number));
