import 'dart:ui';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/design/design_system.dart';
import '../core/ui/ui_helpers.dart';
import '../features/catalog/data/catalog.dart';

/// Tab shell with an Apple-style frosted tab bar. Each tab keeps its own navigation stack.
/// Branches: 0 Home, 1 Compete, 2 Rankings, 3 News, 4 Store, 5 Profile. The fourth tab shows
/// Store while the server's store_enabled flag is on, otherwise News (never a dead tab).
/// With the Store tab showing, News is opened from Home and highlights the Home tab.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  static const _news = 3;
  static const _store = 4;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final storeEnabled = ref.watch(appConfigProvider).value?.storeEnabled ?? false;
    final tabs = [
      (branch: 0, label: l10n.tabHome, icon: CupertinoIcons.house, active: CupertinoIcons.house_fill),
      (branch: 1, label: l10n.tabCompete, icon: CupertinoIcons.sportscourt, active: CupertinoIcons.sportscourt_fill),
      (branch: 2, label: l10n.tabRankings, icon: CupertinoIcons.chart_bar, active: CupertinoIcons.chart_bar_fill),
      storeEnabled
          ? (branch: _store, label: l10n.tabStore, icon: CupertinoIcons.bag, active: CupertinoIcons.bag_fill)
          : (branch: _news, label: l10n.tabNews, icon: CupertinoIcons.news, active: CupertinoIcons.news_solid),
      (branch: 5, label: l10n.tabProfile, icon: CupertinoIcons.person_crop_circle, active: CupertinoIcons.person_crop_circle_fill),
    ];
    final current = navigationShell.currentIndex;
    var selected = tabs.indexWhere((t) => t.branch == current);
    if (selected < 0) selected = 0; // News while the Store tab shows: it belongs to Home.

    return Scaffold(
      extendBody: true,
      body: navigationShell,
      bottomNavigationBar: _FrostedTabBar(
        currentIndex: selected,
        items: [for (final t in tabs) (label: t.label, icon: t.icon, active: t.active)],
        onTap: (index) {
          HapticFeedback.selectionClick();
          final branch = tabs[index].branch;
          // Tapping the active tab returns to its root, like iOS.
          navigationShell.goBranch(branch, initialLocation: branch == current);
        },
      ),
    );
  }
}

class _FrostedTabBar extends StatelessWidget {
  const _FrostedTabBar({required this.currentIndex, required this.items, required this.onTap});

  final int currentIndex;
  final List<({String label, IconData icon, IconData active})> items;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;

    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
        child: Container(
          decoration: const BoxDecoration(
            color: RColors.barBackground,
            border: Border(top: BorderSide(color: RColors.border, width: 0.5)),
          ),
          padding: EdgeInsets.only(bottom: bottom),
          child: SizedBox(
            height: RSize.tabBarHeight,
            child: Row(
              children: [
                for (var i = 0; i < items.length; i++)
                  Expanded(
                    child: _TabItem(
                      label: items[i].label,
                      icon: i == currentIndex ? items[i].active : items[i].icon,
                      selected: i == currentIndex,
                      index: i,
                      count: items.length,
                      onTap: () => onTap(i),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TabItem extends StatelessWidget {
  const _TabItem({required this.label, required this.icon, required this.selected, required this.index, required this.count, required this.onTap});

  final String label;
  final IconData icon;
  final bool selected;
  final int index;
  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? RColors.accent : RColors.textSecondary;
    return Semantics(
      selected: selected,
      button: true,
      label: label,
      // e.g. "Tab 2 of 5" so screen-reader users know where they are.
      hint: '${index + 1} / $count',
      excludeSemantics: true,
      child: RPressable(
        onPressed: onTap,
        isButton: false,
        pressedOpacity: 0.6,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 24, color: color),
            const SizedBox(height: 2),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              // Tab labels stay legible but don't grow beyond 1.3x so the bar never breaks.
              textScaler: MediaQuery.textScalerOf(context).clamp(maxScaleFactor: 1.3),
              style: RType.caption2.copyWith(color: color, fontWeight: selected ? FontWeight.w700 : FontWeight.w500),
            ),
          ],
        ),
      ),
    );
  }
}
