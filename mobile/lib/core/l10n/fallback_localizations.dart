import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

/// Flutter's built-in Material/Cupertino widgets have no Kurmanji (`kmr`) translations.
/// For those system strings (date picker labels, "Paste", back-button tooltips) we fall back to
/// English. Kurmanji is written left-to-right in Latin script, so English layout fits.
/// All RIVALO text still comes from `app_kmr.arb`.
const _fallbackLanguages = {'kmr'};
const _fallbackLocale = Locale('en');

class FallbackMaterialLocalizationsDelegate extends LocalizationsDelegate<MaterialLocalizations> {
  const FallbackMaterialLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => _fallbackLanguages.contains(locale.languageCode);

  @override
  Future<MaterialLocalizations> load(Locale locale) => GlobalMaterialLocalizations.delegate.load(_fallbackLocale);

  @override
  bool shouldReload(covariant LocalizationsDelegate<MaterialLocalizations> old) => false;
}

class FallbackCupertinoLocalizationsDelegate extends LocalizationsDelegate<CupertinoLocalizations> {
  const FallbackCupertinoLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => _fallbackLanguages.contains(locale.languageCode);

  @override
  Future<CupertinoLocalizations> load(Locale locale) => GlobalCupertinoLocalizations.delegate.load(_fallbackLocale);

  @override
  bool shouldReload(covariant LocalizationsDelegate<CupertinoLocalizations> old) => false;
}

/// Maps device languages onto the app's supported locales. A phone set to Kurdish ("ku", any
/// script/region) gets Kurmanji; anything unsupported gets English.
Locale resolveAppLocale(Locale? device, Iterable<Locale> supported) {
  if (device == null) return const Locale('en');
  final code = device.languageCode == 'ku' ? 'kmr' : device.languageCode;
  for (final locale in supported) {
    if (locale.languageCode == code) return locale;
  }
  if (kDebugMode) debugPrint('Unsupported device locale $device, using English.');
  return const Locale('en');
}
