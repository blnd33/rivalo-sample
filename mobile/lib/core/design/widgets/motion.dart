import 'package:flutter/widgets.dart';

import '../tokens.dart';

/// Entrance animation: fades in while rising a few points. [index] staggers items in a list.
/// Instant when the system "reduce motion" setting is on.
class RFadeIn extends StatefulWidget {
  const RFadeIn({super.key, required this.child, this.index = 0, this.distance = 14});

  final Widget child;
  final int index;
  final double distance;

  @override
  State<RFadeIn> createState() => _RFadeInState();
}

class _RFadeInState extends State<RFadeIn> with SingleTickerProviderStateMixin {
  static const _duration = Duration(milliseconds: 420);
  static const _step = Duration(milliseconds: 55);

  late final AnimationController _controller;
  late final Animation<double> _curve;

  @override
  void initState() {
    super.initState();
    // The stagger delay lives inside the animation (an Interval), so no timers are involved.
    final delay = _step * widget.index.clamp(0, 6);
    final total = delay + _duration;
    _controller = AnimationController(vsync: this, duration: total);
    _curve = CurvedAnimation(
      parent: _controller,
      curve: Interval(delay.inMilliseconds / total.inMilliseconds, 1, curve: RMotion.easeOut),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (reduce) {
      _controller.value = 1;
    } else if (!_controller.isAnimating && _controller.value == 0) {
      _controller.forward();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _curve,
      child: widget.child,
      builder: (context, child) => Opacity(
        opacity: _curve.value,
        child: Transform.translate(offset: Offset(0, (1 - _curve.value) * widget.distance), child: child),
      ),
    );
  }
}
