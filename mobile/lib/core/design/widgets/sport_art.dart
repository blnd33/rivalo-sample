import 'dart:math' as math;

import 'package:flutter/widgets.dart';

import '../tokens.dart';
import 'sport_glyph.dart';

/// Colour story for each sport's artwork: deep base, lit mid-tone, and line colour.
typedef _Palette = ({Color deep, Color mid, Color glow});

_Palette _palette(String? icon) => switch (icon) {
      'football' => (deep: const Color(0xFF06261C), mid: const Color(0xFF138A55), glow: const Color(0xFF35D07F)),
      'padel' => (deep: const Color(0xFF071A3D), mid: const Color(0xFF1E63D6), glow: const Color(0xFF4DA3FF)),
      'cue' => (deep: const Color(0xFF04211F), mid: const Color(0xFF0E7A66), glow: const Color(0xFF2EE6B8)),
      'controller' => (deep: const Color(0xFF16093A), mid: const Color(0xFF6A2EE0), glow: const Color(0xFFB57BFF)),
      _ => (deep: RColors.backgroundDeep, mid: RColors.surfaceRaised, glow: RColors.accent),
    };

/// Generated cover artwork for a sport: lit gradient, the sport's playing surface in perspective,
/// and a large glyph. Shown whenever no real photo exists. It is an illustration, so it never
/// pretends to be a photo of a real event. Decorative (excluded from semantics).
class RSportArt extends StatelessWidget {
  const RSportArt({super.key, required this.sportIcon, this.seed = 0, this.showGlyph = true});

  /// `sports.icon` from the API: football | padel | cue | controller.
  final String? sportIcon;

  /// Varies lighting and angle slightly so neighbouring cards don't look identical.
  final int seed;
  final bool showGlyph;

  @override
  Widget build(BuildContext context) {
    final p = _palette(sportIcon);
    final rnd = math.Random(seed);
    final glowX = -0.6 + rnd.nextDouble() * 1.2;

    return ExcludeSemantics(
      child: ClipRect(
        child: Stack(
          fit: StackFit.expand,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [p.mid, p.deep, RColors.backgroundDeep],
                  stops: const [0, 0.62, 1],
                ),
              ),
            ),
            CustomPaint(painter: _SurfacePainter(sportIcon, p.glow, tilt: 0.95 + rnd.nextDouble() * 0.2)),
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(glowX, -0.9),
                  radius: 1.1,
                  colors: [p.glow.withValues(alpha: 0.35), p.glow.withValues(alpha: 0)],
                ),
              ),
            ),
            if (showGlyph)
              LayoutBuilder(
                builder: (context, c) {
                  final size = c.maxHeight * 0.72;
                  return Align(
                    alignment: const AlignmentDirectional(0.92, -0.55),
                    child: Opacity(
                      opacity: 0.16,
                      child: SportGlyph(sportIcon, size: size, color: RColors.textPrimary),
                    ),
                  );
                },
              ),
          ],
        ),
      ),
    );
  }
}

/// Draws the playing surface (pitch, court, table, arena grid) tilted away from the viewer.
class _SurfacePainter extends CustomPainter {
  _SurfacePainter(this.icon, this.glow, {required this.tilt});

  final String? icon;
  final Color glow;
  final double tilt;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final cx = w / 2;
    final cy = h * 0.78;

    // Perspective: surface recedes towards the top of the image.
    final m = Matrix4.translationValues(cx, cy, 0)
        .multiplied(Matrix4.identity()..setEntry(3, 2, 0.0016))
        .multiplied(Matrix4.rotationX(-tilt))
        .multiplied(Matrix4.translationValues(-cx, -cy, 0));

    canvas.save();
    canvas.transform(m.storage);

    final line = Paint()
      ..color = RColors.textPrimary.withValues(alpha: 0.22)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    final accent = Paint()
      ..color = glow.withValues(alpha: 0.55)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.5;

    final court = Rect.fromCenter(center: Offset(cx, cy - h * 0.05), width: w * 0.92, height: h * 1.5);

    switch (icon) {
      case 'football':
        // Mown stripes, touchlines, halfway line, centre circle and penalty boxes.
        final stripe = Paint()..color = RColors.textPrimary.withValues(alpha: 0.035);
        for (var i = 0; i < 8; i += 2) {
          canvas.drawRect(Rect.fromLTWH(court.left, court.top + court.height / 8 * i, court.width, court.height / 8), stripe);
        }
        canvas.drawRect(court, line);
        canvas.drawLine(Offset(court.left, court.center.dy), Offset(court.right, court.center.dy), line);
        canvas.drawCircle(court.center, court.width * 0.14, accent);
        canvas.drawRect(Rect.fromCenter(center: Offset(cx, court.top + court.height * 0.08), width: court.width * 0.5, height: court.height * 0.16), line);
        canvas.drawRect(Rect.fromCenter(center: Offset(cx, court.bottom - court.height * 0.08), width: court.width * 0.5, height: court.height * 0.16), line);
      case 'padel':
        // Court with glass-wall outline, net, service lines and centre line.
        final glass = Paint()..color = glow.withValues(alpha: 0.08);
        canvas.drawRect(court, glass);
        canvas.drawRect(court, line);
        canvas.drawLine(Offset(court.left, court.center.dy), Offset(court.right, court.center.dy), accent..strokeWidth = 4);
        final service = court.height * 0.3;
        canvas.drawLine(Offset(court.left, court.center.dy - service), Offset(court.right, court.center.dy - service), line);
        canvas.drawLine(Offset(court.left, court.center.dy + service), Offset(court.right, court.center.dy + service), line);
        canvas.drawLine(Offset(cx, court.center.dy - service), Offset(cx, court.center.dy + service), line);
      case 'cue':
        // Table cushion, pockets, head string and a racked set of balls.
        final table = Rect.fromCenter(center: court.center, width: court.width * 0.8, height: court.height * 0.9);
        canvas.drawRRect(RRect.fromRectAndRadius(table, const Radius.circular(18)), line..strokeWidth = 3);
        final pocket = Paint()..color = RColors.backgroundDeep.withValues(alpha: 0.7);
        for (final o in [table.topLeft, table.topRight, table.bottomLeft, table.bottomRight, table.centerLeft, table.centerRight]) {
          canvas.drawCircle(o, 14, pocket);
        }
        canvas.drawLine(Offset(table.left, table.top + table.height * 0.75), Offset(table.right, table.top + table.height * 0.75), line..strokeWidth = 1.5);
        const colors = [Color(0xFFFFD166), Color(0xFF4DA3FF), Color(0xFFFF626D), Color(0xFF9B5DE5), Color(0xFFFF8C42), Color(0xFF35D07F), Color(0xFF8B2E2E), Color(0xFF0B1020), Color(0xFFFFD166)];
        final r = table.width * 0.035;
        var k = 0;
        final apex = Offset(cx, table.top + table.height * 0.3);
        for (var row = 0; row < 4 && k < colors.length; row++) {
          for (var i = 0; i <= row && k < colors.length; i++) {
            final o = apex + Offset((i - row / 2) * r * 2.05, -row * r * 1.8);
            canvas.drawCircle(o, r, Paint()..color = colors[k++]);
          }
        }
        canvas.drawCircle(Offset(cx, table.top + table.height * 0.82), r, Paint()..color = RColors.textPrimary.withValues(alpha: 0.9));
      case 'controller':
        // Arena floor: neon grid.
        final grid = Paint()
          ..color = glow.withValues(alpha: 0.28)
          ..strokeWidth = 1.5;
        final big = court.inflate(w * 0.4);
        for (var x = big.left; x <= big.right; x += w / 10) {
          canvas.drawLine(Offset(x, big.top), Offset(x, big.bottom), grid);
        }
        for (var y = big.top; y <= big.bottom; y += w / 10) {
          canvas.drawLine(Offset(big.left, y), Offset(big.right, y), grid);
        }
      default:
        canvas.drawRect(court, line);
        canvas.drawLine(Offset(court.left, court.center.dy), Offset(court.right, court.center.dy), accent);
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _SurfacePainter old) => old.icon != icon || old.glow != glow || old.tilt != tilt;
}
