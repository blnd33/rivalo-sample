import 'package:flutter/widgets.dart';

/// RIVALO design tokens. Mirrors `design-system/tokens.json` (the source of truth).
/// When a value changes there, update it here in the same commit.
abstract final class RColors {
  // Brand
  static const background = Color(0xFF0B1020);
  static const backgroundDeep = Color(0xFF070B17);
  static const surface = Color(0xFF182238);
  static const surfaceRaised = Color(0xFF1F2B45);
  static const surfaceSunken = Color(0xFF111A2E);
  static const accent = Color(0xFFC7FF3D);
  static const onAccent = Color(0xFF0B1020);
  static const textPrimary = Color(0xFFF5F7FA);
  static const textSecondary = Color(0xFFA6B0C3);
  static const textTertiary = Color(0xFF7D88A0);
  static const border = Color(0xFF2A3650);
  static const borderStrong = Color(0xFF3A4868);

  // Status (always paired with a label or icon, never color alone)
  static const success = Color(0xFF35D07F);
  static const warning = Color(0xFFFFBF47);
  static const error = Color(0xFFFF626D);
  static const info = Color(0xFF4DA3FF);

  // Podium
  static const gold = Color(0xFFFFD166);
  static const silver = Color(0xFFC5CEDB);
  static const bronze = Color(0xFFD99A63);

  /// Translucent bar background used under the blur of nav/tab bars (72% opaque).
  static const barBackground = Color(0xB80B1020);
}

/// 4/8-based spacing scale.
abstract final class RSpace {
  static const double xxs = 2;
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double xxxl = 32;
  static const double section = 40;
  static const double hero = 56;

  /// Horizontal page gutter; grows on wide screens (tablets, landscape).
  static double gutter(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    if (width >= 900) return 48;
    if (width >= 600) return 32;
    return 16;
  }
}

abstract final class RRadius {
  static const double xs = 6;
  static const double sm = 10;
  static const double md = 14;
  static const double lg = 18;
  static const double xl = 24;
  static const double sheet = 28;
  static const double pill = 999;
}

abstract final class RSize {
  /// Apple HIG minimum hit target (also exceeds Android's 48dp when padded).
  static const double minTouchTarget = 44;
  static const double iconSm = 16;
  static const double iconMd = 20;
  static const double iconLg = 24;
  static const double iconXl = 32;
  static const double avatarSm = 32;
  static const double avatarMd = 44;
  static const double avatarLg = 72;
  static const double buttonHeight = 52;
  static const double buttonHeightCompact = 40;
  static const double tabBarHeight = 56;

  /// Max width for readable long-form content on tablets.
  static const double readableWidth = 680;
}

/// Motion tokens. Exits are faster than entrances. Respect reduced motion via [RMotion.of].
abstract final class RMotion {
  static const instant = Duration(milliseconds: 100);
  static const fast = Duration(milliseconds: 180);
  static const base = Duration(milliseconds: 260);
  static const slow = Duration(milliseconds: 400);

  static const Curve standard = Cubic(0.2, 0, 0, 1);
  static const Curve easeOut = Cubic(0.22, 1, 0.36, 1);
  static const Curve easeIn = Cubic(0.4, 0, 1, 1);

  /// Returns [duration], or near-zero when the OS "reduce motion" setting is on.
  static Duration of(BuildContext context, Duration duration) {
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    return reduce ? Duration.zero : duration;
  }
}

/// Apple HIG default Dynamic Type sizes, rendered in the platform system font
/// (SF Pro on iOS, Roboto on Android). Scales with the user's text-size setting.
abstract final class RType {
  static const largeTitle = TextStyle(fontSize: 34, height: 41 / 34, fontWeight: FontWeight.w800, letterSpacing: 0.37);
  static const title1 = TextStyle(fontSize: 28, height: 34 / 28, fontWeight: FontWeight.w700, letterSpacing: 0.36);
  static const title2 = TextStyle(fontSize: 22, height: 28 / 22, fontWeight: FontWeight.w700, letterSpacing: 0.35);
  static const title3 = TextStyle(fontSize: 20, height: 25 / 20, fontWeight: FontWeight.w600, letterSpacing: 0.38);
  static const headline = TextStyle(fontSize: 17, height: 22 / 17, fontWeight: FontWeight.w600, letterSpacing: -0.41);
  static const body = TextStyle(fontSize: 17, height: 22 / 17, fontWeight: FontWeight.w400, letterSpacing: -0.41);
  static const callout = TextStyle(fontSize: 16, height: 21 / 16, fontWeight: FontWeight.w400, letterSpacing: -0.32);
  static const subheadline = TextStyle(fontSize: 15, height: 20 / 15, fontWeight: FontWeight.w400, letterSpacing: -0.24);
  static const footnote = TextStyle(fontSize: 13, height: 18 / 13, fontWeight: FontWeight.w400, letterSpacing: -0.08);
  static const caption1 = TextStyle(fontSize: 12, height: 16 / 12, fontWeight: FontWeight.w500);
  static const caption2 = TextStyle(fontSize: 11, height: 13 / 11, fontWeight: FontWeight.w600, letterSpacing: 0.07);
}
