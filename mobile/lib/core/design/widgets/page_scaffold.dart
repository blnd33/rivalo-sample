import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import '../tokens.dart';
import 'pressable.dart';

/// Apple-style page: collapsing large title over a translucent blurred bar, pull-to-refresh,
/// readable content width on tablets, and bottom padding that clears the tab bar and home indicator.
class RLargeTitlePage extends StatelessWidget {
  const RLargeTitlePage({
    super.key,
    required this.title,
    required this.slivers,
    this.trailing,
    this.leading,
    this.onRefresh,
    this.previousPageTitle,
  });

  final String title;
  final List<Widget> slivers;
  final Widget? trailing;
  final Widget? leading;
  final Future<void> Function()? onRefresh;
  final String? previousPageTitle;

  @override
  Widget build(BuildContext context) {
    final gutter = RSpace.gutter(context);
    final width = MediaQuery.sizeOf(context).width;
    final extra = width > RSize.readableWidth + gutter * 2 ? (width - RSize.readableWidth) / 2 : gutter;
    final bottomInset = MediaQuery.paddingOf(context).bottom;

    final navBar = CupertinoSliverNavigationBar(
      largeTitle: Text(title),
      trailing: trailing,
      leading: leading,
      previousPageTitle: previousPageTitle,
      border: const Border(bottom: BorderSide(color: RColors.border, width: 0.5)),
      stretch: true,
    );

    return Scaffold(
      backgroundColor: RColors.background,
      body: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          navBar,
          if (onRefresh != null) CupertinoSliverRefreshControl(onRefresh: onRefresh),
          SliverPadding(
            padding: EdgeInsets.fromLTRB(extra, RSpace.sm, extra, 0),
            sliver: SliverMainAxisGroup(slivers: slivers),
          ),
          SliverToBoxAdapter(child: SizedBox(height: bottomInset + RSpace.xxxl)),
        ],
      ),
    );
  }
}

/// Standard pushed page (compact centered title, back chevron) for forms and details.
class RDetailPage extends StatelessWidget {
  const RDetailPage({super.key, required this.title, required this.child, this.actions, this.bottomBar, this.scrollable = true});

  final String title;
  final Widget child;
  final List<Widget>? actions;

  /// Sticky bottom action area (e.g. "Register"), placed above the home indicator.
  final Widget? bottomBar;
  final bool scrollable;

  @override
  Widget build(BuildContext context) {
    final gutter = RSpace.gutter(context);
    final width = MediaQuery.sizeOf(context).width;
    final side = width > RSize.readableWidth + gutter * 2 ? (width - RSize.readableWidth) / 2 : gutter;

    final body = Padding(padding: EdgeInsets.symmetric(horizontal: side), child: child);

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: actions,
        shape: const Border(bottom: BorderSide(color: RColors.border, width: 0.5)),
      ),
      body: SafeArea(
        bottom: bottomBar == null,
        child: scrollable
            ? SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.only(top: RSpace.lg, bottom: RSpace.xxxl),
                child: body,
              )
            : body,
      ),
      bottomNavigationBar: bottomBar == null
          ? null
          : Container(
              decoration: const BoxDecoration(
                color: RColors.background,
                border: Border(top: BorderSide(color: RColors.border, width: 0.5)),
              ),
              padding: EdgeInsets.fromLTRB(side, RSpace.md, side, RSpace.md + MediaQuery.paddingOf(context).bottom),
              child: bottomBar,
            ),
    );
  }
}

/// Detail page with a full-bleed photo header (App Store / Apple TV style): the image stretches
/// when pulled down, fades as it collapses, and the title slides into the bar.
class RHeroPage extends StatelessWidget {
  const RHeroPage({
    super.key,
    required this.title,
    required this.header,
    required this.child,
    this.bottomBar,
    this.onRefresh,
    this.actions,
  });

  final String title;
  final Widget header;
  final Widget child;
  final Widget? bottomBar;
  final Future<void> Function()? onRefresh;
  final List<Widget>? actions;

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    final width = media.size.width;
    final gutter = RSpace.gutter(context);
    final side = width > RSize.readableWidth + gutter * 2 ? (width - RSize.readableWidth) / 2 : gutter;
    final expanded = (width * 0.78).clamp(260.0, 440.0);
    final canPop = Navigator.of(context).canPop();

    Widget scroll = CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
      slivers: [
        SliverAppBar(
          pinned: true,
          stretch: true,
          expandedHeight: expanded,
          backgroundColor: RColors.background,
          surfaceTintColor: Colors.transparent,
          automaticallyImplyLeading: false,
          leading: canPop
              ? Center(
                  child: RGlassIconButton(
                    icon: CupertinoIcons.back,
                    semanticLabel: MaterialLocalizations.of(context).backButtonTooltip,
                    onPressed: () => Navigator.of(context).maybePop(),
                  ),
                )
              : null,
          actions: actions,
          flexibleSpace: _HeroFlexible(title: title, header: header),
        ),
        SliverPadding(
          padding: EdgeInsets.fromLTRB(side, RSpace.xl, side, RSpace.xxxl + (bottomBar == null ? media.padding.bottom : 0)),
          sliver: SliverToBoxAdapter(child: child),
        ),
      ],
    );

    if (onRefresh != null) {
      scroll = RefreshIndicator.adaptive(onRefresh: onRefresh!, edgeOffset: kToolbarHeight + media.padding.top, child: scroll);
    }

    return Scaffold(
      backgroundColor: RColors.background,
      body: scroll,
      bottomNavigationBar: bottomBar == null
          ? null
          : Container(
              decoration: const BoxDecoration(
                color: RColors.background,
                border: Border(top: BorderSide(color: RColors.border, width: 0.5)),
              ),
              padding: EdgeInsets.fromLTRB(side, RSpace.md, side, RSpace.md + media.padding.bottom),
              child: bottomBar,
            ),
    );
  }
}

class _HeroFlexible extends StatelessWidget {
  const _HeroFlexible({required this.title, required this.header});

  final String title;
  final Widget header;

  @override
  Widget build(BuildContext context) {
    final settings = context.dependOnInheritedWidgetOfExactType<FlexibleSpaceBarSettings>();
    return LayoutBuilder(
      builder: (context, c) {
        final min = settings?.minExtent ?? kToolbarHeight;
        final max = settings?.maxExtent ?? c.maxHeight;
        // 1 = fully expanded (or stretched), 0 = collapsed into the bar.
        final t = max <= min ? 0.0 : ((c.maxHeight - min) / (max - min)).clamp(0.0, 1.0);
        final imageOpacity = Curves.easeOut.transform(((t - 0.05) / 0.6).clamp(0.0, 1.0));
        final titleOpacity = (1 - t / 0.22).clamp(0.0, 1.0);

        return Stack(
          fit: StackFit.expand,
          children: [
            Opacity(opacity: imageOpacity, child: header),
            if (titleOpacity > 0)
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                child: Opacity(opacity: titleOpacity, child: const Divider(height: 0.5)),
              ),
            SafeArea(
              bottom: false,
              child: Align(
                alignment: Alignment.topCenter,
                child: SizedBox(
                  height: kToolbarHeight,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 64),
                    child: Center(
                      child: ExcludeSemantics(
                        excluding: titleOpacity < 0.5,
                        child: Opacity(
                          opacity: titleOpacity,
                          child: Text(title, style: RType.headline, maxLines: 1, overflow: TextOverflow.ellipsis),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

/// Round translucent button that stays legible over photos (back, share).
class RGlassIconButton extends StatelessWidget {
  const RGlassIconButton({super.key, required this.icon, required this.semanticLabel, required this.onPressed});

  final IconData icon;
  final String semanticLabel;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return RPressable(
      onPressed: onPressed,
      semanticLabel: semanticLabel,
      pressedScale: 0.9,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: const Color(0x8C0B1020),
          shape: BoxShape.circle,
          border: Border.all(color: const Color(0x33FFFFFF), width: 0.5),
        ),
        alignment: Alignment.center,
        child: Icon(icon, size: 20, color: RColors.textPrimary),
      ),
    );
  }
}
