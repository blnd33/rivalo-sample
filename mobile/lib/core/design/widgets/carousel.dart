import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../tokens.dart';

/// Builds one slide. [offset] is how far the slide is from the centre (-1..1), for parallax.
typedef RCarouselItemBuilder = Widget Function(BuildContext context, int index, double offset);

/// Apple-style featured slider: full-width cards that peek into the page margins, gentle scale
/// on neighbours, parallax, page dots and calm auto-advance.
///
/// Auto-advance pauses while the user touches the slider, when the screen is not visible, and
/// is off entirely when the system "reduce motion" setting is on.
class RCarousel extends StatefulWidget {
  const RCarousel({
    super.key,
    required this.itemCount,
    required this.itemBuilder,
    this.aspectRatio = 16 / 10,
    this.maxHeight = 420,
    this.interval = const Duration(seconds: 6),
    required this.semanticLabel,
  });

  final int itemCount;
  final RCarouselItemBuilder itemBuilder;
  final double aspectRatio;
  final double maxHeight;
  final Duration interval;

  /// e.g. "Featured" (the slide position is added for screen readers).
  final String semanticLabel;

  @override
  State<RCarousel> createState() => _RCarouselState();
}

class _RCarouselState extends State<RCarousel> {
  static const _gap = 10.0;

  PageController? _controller;
  double _viewportFraction = 1;
  Timer? _timer;
  int _page = 0;
  bool _touching = false;

  /// Widget tests can't run periodic timers; auto-advance is skipped under the test binding.
  static bool get _inTest => WidgetsBinding.instance.runtimeType.toString().contains('TestWidgetsFlutterBinding');

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _restartTimer();
  }

  @override
  void didUpdateWidget(covariant RCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_page >= widget.itemCount) _page = 0;
    _restartTimer();
  }

  void _restartTimer() {
    _timer?.cancel();
    final reduceMotion = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (widget.itemCount < 2 || reduceMotion || _inTest) return;
    _timer = Timer.periodic(widget.interval, (_) => _advance());
  }

  void _advance() {
    final c = _controller;
    if (!mounted || _touching || c == null || !c.hasClients) return;
    if (!TickerMode.valuesOf(context).enabled) return; // Off-screen tab or covered route.
    final next = (_page + 1) % widget.itemCount;
    c.animateToPage(next, duration: const Duration(milliseconds: 650), curve: Curves.easeInOutCubic);
  }

  PageController _controllerFor(double fraction) {
    if (_controller == null || (_viewportFraction - fraction).abs() > 0.001) {
      final old = _controller;
      _controller = PageController(viewportFraction: fraction, initialPage: _page);
      _viewportFraction = fraction;
      if (old != null) WidgetsBinding.instance.addPostFrameCallback((_) => old.dispose());
    }
    return _controller!;
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final screen = MediaQuery.sizeOf(context).width;

    return LayoutBuilder(
      builder: (context, constraints) {
        final content = constraints.maxWidth;
        // Slides align with the page content; neighbours peek into the side margins.
        final fraction = ((content + _gap) / screen).clamp(0.5, 1.0);
        final controller = _controllerFor(fraction);
        final height = (content / widget.aspectRatio).clamp(180.0, widget.maxHeight);

        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              height: height,
              child: OverflowBox(
                minWidth: screen,
                maxWidth: screen,
                child: Listener(
                  onPointerDown: (_) => _touching = true,
                  onPointerUp: (_) => _touching = false,
                  onPointerCancel: (_) => _touching = false,
                  child: PageView.builder(
                    controller: controller,
                    itemCount: widget.itemCount,
                    physics: const BouncingScrollPhysics(),
                    onPageChanged: (i) {
                      HapticFeedback.selectionClick();
                      setState(() => _page = i);
                    },
                    itemBuilder: (context, index) => AnimatedBuilder(
                      animation: controller,
                      builder: (context, _) {
                        var offset = (_page - index).toDouble();
                        if (controller.hasClients && controller.position.haveDimensions) {
                          offset = (controller.page ?? _page.toDouble()) - index;
                        }
                        offset = offset.clamp(-1.0, 1.0);
                        final scale = 1 - offset.abs() * 0.06;
                        return Semantics(
                          label: '${widget.semanticLabel}, ${index + 1} / ${widget.itemCount}',
                          container: true,
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: _gap / 2),
                            child: Transform.scale(scale: scale, child: widget.itemBuilder(context, index, offset)),
                          ),
                        );
                      },
                    ),
                  ),
                ),
              ),
            ),
            if (widget.itemCount > 1) ...[
              const SizedBox(height: RSpace.md),
              ExcludeSemantics(child: RPageDots(count: widget.itemCount, index: _page)),
            ],
          ],
        );
      },
    );
  }
}

/// Page indicator: the current page is a lime pill, others are small dots.
class RPageDots extends StatelessWidget {
  const RPageDots({super.key, required this.count, required this.index});

  final int count;
  final int index;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < count; i++)
          AnimatedContainer(
            duration: RMotion.of(context, RMotion.base),
            curve: RMotion.standard,
            margin: const EdgeInsets.symmetric(horizontal: 3),
            width: i == index ? 20 : 6,
            height: 6,
            decoration: BoxDecoration(
              color: i == index ? RColors.accent : RColors.textTertiary.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(RRadius.pill),
            ),
          ),
      ],
    );
  }
}
