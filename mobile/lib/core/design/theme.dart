import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'tokens.dart';

/// Dark-first RIVALO theme with Apple-style behavior on every platform:
/// Cupertino page transitions (edge swipe-back), HIG type scale, no Material ink splash
/// (pressed feedback is handled by [RPressable] with opacity), adaptive controls.
abstract final class RivaloTheme {
  static ThemeData dark() {
    const scheme = ColorScheme(
      brightness: Brightness.dark,
      primary: RColors.accent,
      onPrimary: RColors.onAccent,
      secondary: RColors.info,
      onSecondary: RColors.onAccent,
      error: RColors.error,
      onError: RColors.onAccent,
      surface: RColors.background,
      onSurface: RColors.textPrimary,
      surfaceContainerLowest: RColors.backgroundDeep,
      surfaceContainerLow: RColors.surfaceSunken,
      surfaceContainer: RColors.surface,
      surfaceContainerHigh: RColors.surfaceRaised,
      surfaceContainerHighest: RColors.surfaceRaised,
      onSurfaceVariant: RColors.textSecondary,
      outline: RColors.borderStrong,
      outlineVariant: RColors.border,
    );

    final textTheme = TextTheme(
      displaySmall: RType.largeTitle,
      headlineLarge: RType.largeTitle,
      headlineMedium: RType.title1,
      headlineSmall: RType.title2,
      titleLarge: RType.title3,
      titleMedium: RType.headline,
      titleSmall: RType.subheadline.copyWith(fontWeight: FontWeight.w600),
      bodyLarge: RType.body,
      bodyMedium: RType.callout,
      bodySmall: RType.footnote,
      labelLarge: RType.headline,
      labelMedium: RType.footnote.copyWith(fontWeight: FontWeight.w600),
      labelSmall: RType.caption2,
    ).apply(bodyColor: RColors.textPrimary, displayColor: RColors.textPrimary);

    const cupertino = CupertinoThemeData(
      brightness: Brightness.dark,
      primaryColor: RColors.accent,
      primaryContrastingColor: RColors.onAccent,
      barBackgroundColor: RColors.barBackground,
      scaffoldBackgroundColor: RColors.background,
      textTheme: CupertinoTextThemeData(
        primaryColor: RColors.accent,
        textStyle: TextStyle(color: RColors.textPrimary, fontSize: 17, letterSpacing: -0.41),
        navTitleTextStyle: TextStyle(color: RColors.textPrimary, fontSize: 17, fontWeight: FontWeight.w600, letterSpacing: -0.41),
        navLargeTitleTextStyle: TextStyle(color: RColors.textPrimary, fontSize: 34, fontWeight: FontWeight.w800, letterSpacing: 0.37),
        navActionTextStyle: TextStyle(color: RColors.accent, fontSize: 17, letterSpacing: -0.41),
      ),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme,
      scaffoldBackgroundColor: RColors.background,
      canvasColor: RColors.background,
      textTheme: textTheme,
      cupertinoOverrideTheme: cupertino,
      splashFactory: NoSplash.splashFactory,
      highlightColor: Colors.transparent,
      dividerColor: RColors.border,
      dividerTheme: const DividerThemeData(color: RColors.border, thickness: 0.5, space: 0.5),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: CupertinoPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
          TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
        },
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: RColors.background,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(color: RColors.textPrimary, fontSize: 17, fontWeight: FontWeight.w600),
        iconTheme: IconThemeData(color: RColors.accent),
      ),
      iconTheme: const IconThemeData(color: RColors.textPrimary, size: RSize.iconLg),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: RColors.surfaceSunken,
        contentPadding: const EdgeInsets.symmetric(horizontal: RSpace.lg, vertical: 14),
        hintStyle: RType.body.copyWith(color: RColors.textTertiary),
        labelStyle: RType.subheadline.copyWith(color: RColors.textSecondary),
        floatingLabelStyle: RType.subheadline.copyWith(color: RColors.accent),
        helperStyle: RType.footnote.copyWith(color: RColors.textSecondary),
        errorStyle: RType.footnote.copyWith(color: RColors.error),
        helperMaxLines: 3,
        errorMaxLines: 3,
        border: _inputBorder(RColors.border),
        enabledBorder: _inputBorder(RColors.border),
        focusedBorder: _inputBorder(RColors.accent, width: 1.5),
        errorBorder: _inputBorder(RColors.error),
        focusedErrorBorder: _inputBorder(RColors.error, width: 1.5),
      ),
      textSelectionTheme: const TextSelectionThemeData(
        cursorColor: RColors.accent,
        selectionColor: Color(0x55C7FF3D),
        selectionHandleColor: RColors.accent,
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? RColors.onAccent : RColors.textSecondary),
        trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? RColors.accent : RColors.surfaceRaised),
        trackOutlineColor: WidgetStateProperty.all(Colors.transparent),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: RColors.accent),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: RColors.surfaceRaised,
        contentTextStyle: RType.subheadline.copyWith(color: RColors.textPrimary),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(RRadius.md)),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: RColors.surface,
        surfaceTintColor: Colors.transparent,
        showDragHandle: true,
        dragHandleColor: RColors.borderStrong,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(RRadius.sheet))),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: RColors.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(RRadius.lg)),
      ),
    );
  }

  static OutlineInputBorder _inputBorder(Color color, {double width = 1}) => OutlineInputBorder(
        borderRadius: BorderRadius.circular(RRadius.md),
        borderSide: BorderSide(color: color, width: width),
      );
}
