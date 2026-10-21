import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../tokens.dart';

/// Apple-style pressed feedback: a quick opacity dip and, for cards, a slight shrink that springs
/// back on release (no layout shift, no ink splash).
/// Exposes button semantics and keeps a minimum 44pt hit target.
class RPressable extends StatefulWidget {
  const RPressable({
    super.key,
    required this.child,
    required this.onPressed,
    this.semanticLabel,
    this.haptic = false,
    this.minSize = RSize.minTouchTarget,
    this.pressedOpacity = 0.55,
    this.pressedScale = 1,
    this.isButton = true,
  });

  final Widget child;
  final VoidCallback? onPressed;
  final String? semanticLabel;
  final bool haptic;
  final double minSize;
  final double pressedOpacity;

  /// 1 = no scaling. Cards use ~0.97 for a tactile press.
  final double pressedScale;
  final bool isButton;

  @override
  State<RPressable> createState() => _RPressableState();
}

class _RPressableState extends State<RPressable> {
  bool _pressed = false;

  void _set(bool value) {
    if (_pressed != value && mounted) setState(() => _pressed = value);
  }

  @override
  Widget build(BuildContext context) {
    final enabled = widget.onPressed != null;

    return Semantics(
      button: widget.isButton,
      enabled: enabled,
      label: widget.semanticLabel,
      child: FocusableActionDetector(
        enabled: enabled,
        mouseCursor: enabled ? SystemMouseCursors.click : SystemMouseCursors.basic,
        actions: {
          ActivateIntent: CallbackAction<ActivateIntent>(onInvoke: (_) {
            widget.onPressed?.call();
            return null;
          }),
        },
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTapDown: enabled ? (_) => _set(true) : null,
          onTapCancel: () => _set(false),
          onTapUp: (_) => _set(false),
          onTap: enabled
              ? () {
                  if (widget.haptic) HapticFeedback.lightImpact();
                  widget.onPressed!();
                }
              : null,
          child: ConstrainedBox(
            constraints: BoxConstraints(minWidth: widget.minSize, minHeight: widget.minSize),
            child: AnimatedScale(
              scale: _pressed ? widget.pressedScale : 1,
              duration: RMotion.of(context, _pressed ? RMotion.instant : RMotion.slow),
              curve: _pressed ? RMotion.easeOut : Curves.easeOutBack,
              child: AnimatedOpacity(
                opacity: !enabled ? 0.4 : (_pressed ? widget.pressedOpacity : 1),
                duration: _pressed ? Duration.zero : RMotion.of(context, RMotion.fast),
                curve: RMotion.easeOut,
                child: widget.child,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
