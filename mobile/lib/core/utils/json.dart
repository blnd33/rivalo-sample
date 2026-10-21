/// Defensive JSON readers so a missing or mistyped field never crashes the UI.
typedef Json = Map<String, dynamic>;

extension JsonRead on Json {
  String str(String key, [String fallback = '']) => this[key] is String ? this[key] as String : (this[key]?.toString() ?? fallback);

  String? strOrNull(String key) => this[key] == null ? null : '${this[key]}';

  int integer(String key, [int fallback = 0]) {
    final v = this[key];
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse('$v') ?? fallback;
  }

  int? intOrNull(String key) => this[key] == null ? null : integer(key);

  bool boolean(String key, [bool fallback = false]) {
    final v = this[key];
    if (v is bool) return v;
    if (v is num) return v != 0;
    return fallback;
  }

  DateTime? date(String key) {
    final v = this[key];
    return v is String ? DateTime.tryParse(v)?.toLocal() : null;
  }

  Json obj(String key) => this[key] is Map ? Map<String, dynamic>.from(this[key] as Map) : <String, dynamic>{};

  Json? objOrNull(String key) => this[key] is Map ? Map<String, dynamic>.from(this[key] as Map) : null;

  List<T> list<T>(String key, T Function(Json) parse) {
    final v = this[key];
    if (v is! List) return <T>[];
    return v.whereType<Map>().map((e) => parse(Map<String, dynamic>.from(e))).toList();
  }

  List<int> intList(String key) {
    final v = this[key];
    if (v is! List) return const [];
    return v.map((e) => e is int ? e : int.tryParse('$e')).whereType<int>().toList();
  }

  Map<String, bool> boolMap(String key) {
    final v = this[key];
    if (v is! Map) return const {};
    return v.map((k, val) => MapEntry('$k', val == true));
  }
}
