import 'package:flutter/material.dart';

import '../tokens.dart';
import 'sport_art.dart';

/// A cover image: the real photo when there is one (fades in when loaded), otherwise generated
/// sport artwork. An optional bottom scrim keeps overlaid text readable on any photo.
class RCover extends StatelessWidget {
  const RCover({
    super.key,
    required this.imageUrl,
    required this.sportIcon,
    this.seed = 0,
    this.scrim = RCoverScrim.bottom,
    this.semanticLabel,
    this.parallax = 0,
  });

  final String? imageUrl;
  final String? sportIcon;
  final int seed;
  final RCoverScrim scrim;

  /// Describes a real photo. Artwork is always decorative.
  final String? semanticLabel;

  /// Horizontal shift in logical pixels (used by carousels for a depth effect).
  final double parallax;

  @override
  Widget build(BuildContext context) {
    final art = RSportArt(sportIcon: sportIcon, seed: seed);

    Widget image = art;
    if (imageUrl != null && imageUrl!.isNotEmpty) {
      image = Stack(
        fit: StackFit.expand,
        children: [
          art,
          Image.network(
            imageUrl!,
            fit: BoxFit.cover,
            semanticLabel: semanticLabel,
            excludeFromSemantics: semanticLabel == null,
            // On the web preview, fall back to an <img> element when the API host sends no CORS headers.
            webHtmlElementStrategy: WebHtmlElementStrategy.fallback,
            frameBuilder: (context, child, frame, wasSynchronouslyLoaded) {
              if (wasSynchronouslyLoaded) return child;
              return AnimatedOpacity(
                opacity: frame == null ? 0 : 1,
                duration: RMotion.of(context, RMotion.slow),
                curve: RMotion.easeOut,
                child: child,
              );
            },
            errorBuilder: (_, _, _) => const SizedBox.shrink(),
          ),
        ],
      );
    }

    if (parallax != 0) {
      image = Transform.translate(offset: Offset(parallax, 0), child: Transform.scale(scale: 1.12, child: image));
    }

    return Stack(
      fit: StackFit.expand,
      children: [
        ClipRect(child: image),
        if (scrim != RCoverScrim.none)
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: scrim == RCoverScrim.strong
                    ? [const Color(0x330B1020), const Color(0x000B1020), const Color(0xE60B1020)]
                    : [const Color(0x000B1020), const Color(0x000B1020), const Color(0xB30B1020)],
                stops: const [0, 0.35, 1],
              ),
            ),
          ),
      ],
    );
  }
}

enum RCoverScrim { none, bottom, strong }
