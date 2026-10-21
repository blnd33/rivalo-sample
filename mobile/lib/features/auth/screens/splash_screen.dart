import 'package:flutter/material.dart';

import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';

/// Shown while the stored session is restored.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const RivaloWordmark(height: 40, showTagline: true),
            const SizedBox(height: RSpace.section),
            Semantics(label: context.l10n.loading, child: const CircularProgressIndicator.adaptive()),
          ],
        ),
      ),
    );
  }
}
