import 'package:flutter/material.dart';

import '../tokens.dart';

/// Line icons for sports (24px grid, 1.75 stroke), ported from the website's SVG sprite
/// so the app and site share one icon language. Decorative by default.
class SportGlyph extends StatelessWidget {
  const SportGlyph(this.iconKey, {super.key, this.size = RSize.iconLg, this.color, this.semanticLabel});

  /// Matches `sports.icon` from the API: football | padel | cue | controller.
  final String? iconKey;
  final double size;
  final Color? color;

  /// Provide when the glyph is not accompanied by visible text.
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    final glyph = SizedBox.square(
      dimension: size,
      child: CustomPaint(painter: _GlyphPainter(iconKey ?? '', color ?? IconTheme.of(context).color ?? RColors.textPrimary)),
    );
    return semanticLabel == null ? ExcludeSemantics(child: glyph) : Semantics(label: semanticLabel, image: true, child: glyph);
  }
}

class _GlyphPainter extends CustomPainter {
  _GlyphPainter(this.key, this.color);

  final String key;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    canvas.scale(size.width / 24);
    final stroke = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.75
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final fill = Paint()..color = color;

    switch (key) {
      case 'football':
        canvas.drawCircle(const Offset(12, 12), 9, stroke);
        canvas.drawPath(
          Path()
            ..moveTo(12, 8.2)
            ..lineTo(15.6, 10.8)
            ..lineTo(14.2, 15)
            ..lineTo(9.8, 15)
            ..lineTo(8.4, 10.8)
            ..close()
            ..moveTo(12, 8.2)
            ..lineTo(12, 3)
            ..moveTo(15.6, 10.8)
            ..lineTo(20.5, 9.3)
            ..moveTo(14.2, 15)
            ..lineTo(17.2, 19.2)
            ..moveTo(9.8, 15)
            ..lineTo(6.8, 19.2)
            ..moveTo(8.4, 10.8)
            ..lineTo(3.5, 9.3),
          stroke,
        );
      case 'padel':
        canvas.drawOval(Rect.fromCenter(center: const Offset(12, 8.6), width: 12.4, height: 13.6), stroke);
        canvas.drawPath(
          Path()
            ..moveTo(9.4, 14.6)
            ..lineTo(11, 17.6)
            ..lineTo(13, 17.6)
            ..lineTo(14.6, 14.6)
            ..moveTo(11, 17.6)
            ..lineTo(11, 21.5)
            ..moveTo(13, 17.6)
            ..lineTo(13, 21.5),
          stroke,
        );
        for (final o in const [Offset(10, 6.4), Offset(14, 6.4), Offset(12, 8.8), Offset(10, 11.1), Offset(14, 11.1)]) {
          canvas.drawCircle(o, 0.85, fill);
        }
      case 'cue':
        canvas.drawCircle(const Offset(8.5, 15.5), 5.5, stroke);
        canvas.drawCircle(const Offset(8.5, 15.5), 2.1, stroke);
        canvas.drawLine(const Offset(14.6, 9.4), const Offset(21, 3), stroke);
        canvas.drawLine(const Offset(17.2, 3.6), const Offset(20.4, 6.8), Paint.from(stroke)..color = color.withValues(alpha: 0.55));
      case 'controller':
        canvas.drawPath(
          Path()
            ..moveTo(7.2, 7.5)
            ..lineTo(16.8, 7.5)
            ..arcToPoint(const Offset(20.9, 10.8), radius: const Radius.circular(4.2))
            ..lineTo(21.9, 15.5)
            ..arcToPoint(const Offset(17.4, 17.8), radius: const Radius.circular(2.6))
            ..lineTo(15.6, 15.8)
            ..lineTo(8.4, 15.8)
            ..lineTo(6.6, 17.8)
            ..arcToPoint(const Offset(2.1, 15.5), radius: const Radius.circular(2.6))
            ..lineTo(3.1, 10.8)
            ..arcToPoint(const Offset(7.2, 7.5), radius: const Radius.circular(4.2))
            ..close()
            ..moveTo(7.5, 10.4)
            ..lineTo(7.5, 13.6)
            ..moveTo(5.9, 12)
            ..lineTo(9.1, 12),
          stroke,
        );
        canvas.drawCircle(const Offset(15.6, 11), 1, fill);
        canvas.drawCircle(const Offset(17.8, 13.1), 1, fill);
      default:
        // Generic trophy for unknown sports.
        canvas.drawPath(
          Path()
            ..moveTo(8, 4)
            ..lineTo(16, 4)
            ..lineTo(16, 9)
            ..arcToPoint(const Offset(8, 9), radius: const Radius.circular(4))
            ..close()
            ..moveTo(12, 13)
            ..lineTo(12, 17)
            ..moveTo(9, 20.5)
            ..lineTo(15, 20.5),
          stroke,
        );
    }
  }

  @override
  bool shouldRepaint(covariant _GlyphPainter old) => old.key != key || old.color != color;
}
