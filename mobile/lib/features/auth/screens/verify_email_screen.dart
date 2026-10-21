import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/auth_repository.dart';
import '../state/session_controller.dart';

/// Shown right after registration. Verification happens by tapping the emailed link on any
/// device; this screen lets the player resend it or continue and verify later.
class VerifyEmailScreen extends ConsumerStatefulWidget {
  const VerifyEmailScreen({super.key});

  @override
  ConsumerState<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends ConsumerState<VerifyEmailScreen> {
  bool _resending = false;
  bool _checking = false;

  Future<void> _resend() async {
    setState(() => _resending = true);
    try {
      await ref.read(authRepositoryProvider).resendVerification();
      if (mounted) showToast(context, context.l10n.verifyResent);
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _resending = false);
    }
  }

  Future<void> _check() async {
    setState(() => _checking = true);
    try {
      await ref.read(sessionProvider.notifier).refreshMe();
      if (!mounted) return;
      if (ref.read(meProvider)?.emailVerified ?? false) {
        _continue();
      } else {
        showToast(context, context.l10n.verifyStillPending);
      }
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  void _continue() {
    final me = ref.read(meProvider);
    context.go(me != null && me.disciplineIds.isEmpty ? Routes.onboarding : Routes.home);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final email = ref.watch(meProvider)?.email ?? '';

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Padding(
              padding: EdgeInsets.all(RSpace.gutter(context) + RSpace.sm),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Spacer(),
                  const ExcludeSemantics(child: Icon(CupertinoIcons.envelope_badge, size: 56, color: RColors.accent)),
                  const SizedBox(height: RSpace.xxl),
                  Semantics(header: true, child: Text(l10n.verifyEmailTitle, style: RType.title1, textAlign: TextAlign.center)),
                  const SizedBox(height: RSpace.md),
                  Text(l10n.verifyEmailBody(email), style: RType.body.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
                  const Spacer(),
                  RButton(label: l10n.verifyCheck, onPressed: _check, loading: _checking),
                  const SizedBox(height: RSpace.md),
                  RButton.secondary(label: l10n.verifyResend, onPressed: _resend, loading: _resending),
                  const SizedBox(height: RSpace.sm),
                  Center(child: RButton.tertiary(label: l10n.verifyLater, onPressed: _continue)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
