import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';

/// "Sell on RIVALO" (Milestone 9). Players apply in the app; approved sellers manage their
/// products, sales and statement in the Seller Hub on the web. Nothing about money happens here.
class MyShop {
  const MyShop({required this.slug, required this.name, required this.status, this.statusReason});

  final String slug;
  final String name;

  /// active | suspended | closed
  final String status;
  final String? statusReason;

  factory MyShop.fromJson(Json j) =>
      MyShop(slug: j.str('slug'), name: j.str('name'), status: j.str('status', 'active'), statusReason: j.strOrNull('status_reason'));
}

class MyApplication {
  const MyApplication({required this.status, required this.businessName, this.decisionNote, this.submittedAt});

  /// pending | approved | rejected | withdrawn
  final String status;
  final String businessName;

  /// Only for rejected applications: the reason staff gave.
  final String? decisionNote;
  final DateTime? submittedAt;

  factory MyApplication.fromJson(Json j) => MyApplication(
    status: j.str('status'),
    businessName: j.str('business_name'),
    decisionNote: j.strOrNull('decision_note'),
    submittedAt: j.date('submitted_at'),
  );
}

class SellingStatus {
  const SellingStatus({required this.open, required this.canApply, required this.hubUrl, this.shop, this.application});

  /// Selling on RIVALO is switched on by the server.
  final bool open;
  final bool canApply;

  /// Where approved sellers sign in (web).
  final String hubUrl;
  final MyShop? shop;

  /// The latest application, if any.
  final MyApplication? application;

  factory SellingStatus.fromJson(Json j) {
    final shop = j.objOrNull('seller');
    final application = j.objOrNull('application');
    return SellingStatus(
      open: j.boolean('open'),
      canApply: j.boolean('can_apply'),
      hubUrl: j.str('hub_url'),
      shop: shop == null ? null : MyShop.fromJson(shop),
      application: application == null ? null : MyApplication.fromJson(application),
    );
  }
}

class SellerApplicationRequest {
  const SellerApplicationRequest({
    required this.businessName,
    required this.contactPhone,
    required this.city,
    required this.categories,
    required this.description,
    this.link,
  });

  final String businessName;
  final String contactPhone;
  final String city;
  final List<String> categories;
  final String description;
  final String? link;

  Json toJson() => {
    'business_name': businessName.trim(),
    'contact_phone': contactPhone.trim(),
    'city': city.trim(),
    'categories': categories,
    'description': description.trim(),
    if (link != null && link!.trim().isNotEmpty) 'link': link!.trim(),
  };
}

class SellingRepository {
  SellingRepository(this._api);

  final ApiClient _api;

  Future<SellingStatus> status() async => SellingStatus.fromJson((await _api.get('/me/selling')).obj('data'));

  Future<SellingStatus> apply(SellerApplicationRequest request) async =>
      SellingStatus.fromJson((await _api.post('/me/seller-application', body: request.toJson())).obj('data'));

  Future<SellingStatus> withdraw() async => SellingStatus.fromJson((await _api.post('/me/seller-application/withdraw')).obj('data'));
}

final sellingRepositoryProvider = Provider((ref) => SellingRepository(ref.watch(apiClientProvider)));

final sellingStatusProvider = FutureProvider.autoDispose<SellingStatus>((ref) => ref.watch(sellingRepositoryProvider).status());
