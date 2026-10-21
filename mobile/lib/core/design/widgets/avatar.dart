import 'package:flutter/material.dart';

import '../tokens.dart';

/// Circular avatar: network image when available, otherwise initials on a slate surface.
class RAvatar extends StatelessWidget {
  const RAvatar({super.key, required this.name, this.imageUrl, this.size = RSize.avatarMd, this.ring = false});

  final String name;
  final String? imageUrl;
  final double size;

  /// Lime ring for the signed-in user's own avatar.
  final bool ring;

  String get _initials {
    // Letters and digits only, so "[SAMPLE] Player 1" becomes "SP" rather than "[P".
    final parts = name
        .trim()
        .split(RegExp(r'[\s_.]+'))
        .map((p) => p.replaceAll(RegExp(r'[^\p{L}\p{N}]', unicode: true), ''))
        .where((p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    final first = parts.first.characters.first;
    final second = parts.length > 1 ? parts[1].characters.first : '';
    return (first + second).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final fallback = Center(
      child: Text(_initials, style: RType.headline.copyWith(fontSize: size * 0.36, color: RColors.textPrimary)),
    );

    return ExcludeSemantics(
      child: Container(
        width: size,
        height: size,
        padding: EdgeInsets.all(ring ? 2 : 0),
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: ring ? RColors.accent : RColors.border, width: ring ? 2 : 1),
        ),
        child: ClipOval(
          child: ColoredBox(
            color: RColors.surfaceRaised,
            child: imageUrl == null
                ? fallback
                : Image.network(
                    imageUrl!,
                    fit: BoxFit.cover,
                    width: size,
                    height: size,
                    errorBuilder: (_, _, _) => fallback,
                  ),
          ),
        ),
      ),
    );
  }
}
