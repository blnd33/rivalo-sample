import 'package:flutter/material.dart';

import '../tokens.dart';

/// Temporary RIVALO wordmark: bold geometric monoline letters with a lime V.
/// Same geometry as the website (`website/index.html`, symbol #wordmark) so the brand is
/// consistent until final logo assets are supplied.
class RivaloWordmark extends StatelessWidget {
  const RivaloWordmark({super.key, this.height = 28, this.showTagline = false, this.taglineText = 'Play. Win. Rise.'});

  final double height;
  final bool showTagline;
  final String taglineText;

  static const double _aspect = 232 / 44;

  @override
  Widget build(BuildContext context) {
    final mark = SizedBox(
      height: height,
      width: height * _aspect,
      child: const CustomPaint(painter: _WordmarkPainter()),
    );

    return Semantics(
      label: showTagline ? 'RIVALO. $taglineText' : 'RIVALO',
      image: true,
      excludeSemantics: true,
      child: showTagline
          ? Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                mark,
                SizedBox(height: height * 0.28),
                Text(
                  taglineText.toUpperCase(),
                  style: RType.caption2.copyWith(
                    color: RColors.textSecondary,
                    letterSpacing: 2.2,
                    fontSize: (height * 0.36).clamp(11, 16).toDouble(),
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            )
          : mark,
    );
  }
}

class _WordmarkPainter extends CustomPainter {
  const _WordmarkPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final scale = size.height / 44;
    canvas
      ..save()
      ..clipRect(Offset.zero & size)
      ..scale(scale);

    final letters = Paint()
      ..color = RColors.textPrimary
      ..style = PaintingStyle.stroke
      ..strokeWidth = 8
      ..strokeJoin = StrokeJoin.miter
      ..strokeCap = StrokeCap.butt;

    // R
    final r = Path()
      ..moveTo(4, 48)
      ..lineTo(4, 4)
      ..lineTo(18, 4)
      ..arcToPoint(const Offset(18, 24), radius: const Radius.circular(10))
      ..lineTo(4, 24)
      ..moveTo(17, 24)
      ..lineTo(32, 48);
    // I
    final i = Path()
      ..moveTo(46, -4)
      ..lineTo(46, 48);
    // A
    final a = Path()
      ..moveTo(110, 48)
      ..lineTo(126, 0)
      ..lineTo(142, 48)
      ..moveTo(115, 31)
      ..lineTo(137, 31);
    // L
    final l = Path()
      ..moveTo(157, -4)
      ..lineTo(157, 40)
      ..lineTo(178, 40);

    canvas
      ..drawPath(r, letters)
      ..drawPath(i, letters)
      ..drawPath(a, letters)
      ..drawPath(l, letters)
      ..drawCircle(const Offset(208, 22), 18, letters);

    // V in electric lime: the forward-motion accent.
    final v = Path()
      ..moveTo(62, -4)
      ..lineTo(78, 44)
      ..lineTo(94, -4);
    canvas
      ..drawPath(v, letters..color = RColors.accent)
      ..restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
