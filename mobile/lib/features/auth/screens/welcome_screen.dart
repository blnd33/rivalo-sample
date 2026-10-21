import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../state/session_controller.dart';

class WelcomeScreen extends ConsumerWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final gutter = RSpace.gutter(context);

    return Scaffold(
      body: SafeArea(
        // Fills tall screens (brand at the top, actions at the bottom) and scrolls on short ones
        // (small phones, landscape, large text sizes).
        child: LayoutBuilder(
          builder: (context, constraints) => SingleChildScrollView(
            child: Center(
              child: ConstrainedBox(
                constraints: BoxConstraints(maxWidth: 480, minHeight: constraints.maxHeight),
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: gutter + RSpace.sm, vertical: RSpace.xxl),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const SizedBox(height: RSpace.xxxl),
                          const RivaloWordmark(height: 44, showTagline: true),
                          const SizedBox(height: RSpace.hero),
                          Text(l10n.welcomeHeadline, style: RType.largeTitle),
                          const SizedBox(height: RSpace.md),
                          Text(l10n.welcomeBody, style: RType.body.copyWith(color: RColors.textSecondary)),
                          const SizedBox(height: RSpace.xxl),
                          const _SportRow(),
                          const SizedBox(height: RSpace.xxxl),
                        ],
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          RButton(label: l10n.welcomeCreateAccount, onPressed: () => context.push(Routes.register)),
                          const SizedBox(height: RSpace.md),
                          RButton.secondary(label: l10n.welcomeSignIn, onPressed: () => context.push(Routes.login)),
                          const SizedBox(height: RSpace.sm),
                          // Look around first: tournaments, rankings and profiles are public.
                          Center(
                            child: RButton.tertiary(
                              label: l10n.welcomeBrowseGuest,
                              onPressed: () => ref.read(sessionProvider.notifier).continueAsGuest(),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SportRow extends StatelessWidget {
  const _SportRow();

  @override
  Widget build(BuildContext context) {
    return const ExcludeSemantics(
      child: Wrap(
        spacing: RSpace.md,
        runSpacing: RSpace.md,
        children: [
          _Chip(icon: 'football'),
          _Chip(icon: 'padel'),
          _Chip(icon: 'cue'),
          _Chip(icon: 'controller'),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.icon});

  final String icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 52,
      height: 52,
      decoration: BoxDecoration(
        color: RColors.surface,
        borderRadius: BorderRadius.circular(RRadius.md),
        border: Border.all(color: RColors.border),
      ),
      alignment: Alignment.center,
      child: SportGlyph(icon, color: RColors.textPrimary),
    );
  }
}
