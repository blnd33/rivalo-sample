import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/design/theme.dart';
import '../core/l10n/fallback_localizations.dart';
import '../core/l10n/generated/app_localizations.dart';
import '../core/providers.dart';
import 'router.dart';

/// App languages: English, Arabic (right-to-left) and Kurdish Kurmanji (`kmr`, Latin script).
class RivaloApp extends ConsumerWidget {
  const RivaloApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);
    final locale = ref.watch(localeProvider);

    return MaterialApp.router(
      title: 'RIVALO',
      debugShowCheckedModeBanner: false,
      routerConfig: router,
      // Dark theme first (brand); used for both system modes until a light theme is designed.
      theme: RivaloTheme.dark(),
      darkTheme: RivaloTheme.dark(),
      themeMode: ThemeMode.dark,
      locale: locale,
      supportedLocales: AppLocalizations.supportedLocales,
      localeResolutionCallback: resolveAppLocale,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        FallbackMaterialLocalizationsDelegate(),
        FallbackCupertinoLocalizationsDelegate(),
      ],
      // Arabic switches the whole layout to right-to-left automatically via Directionality.
    );
  }
}
