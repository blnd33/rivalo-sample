import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../data/profile_repository.dart';

/// Account deletion with a grace period (server: 14 days). Cancelable until then.
class DeleteAccountScreen extends ConsumerStatefulWidget {
  const DeleteAccountScreen({super.key});

  static const graceDays = 14;

  @override
  ConsumerState<DeleteAccountScreen> createState() => _DeleteAccountScreenState();
}

class _DeleteAccountScreenState extends ConsumerState<DeleteAccountScreen> {
  final _password = TextEditingController();
  final _reason = TextEditingController();
  bool _busy = false;
  String? _passwordError;

  @override
  void dispose() {
    _password.dispose();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _schedule() async {
    final l10n = context.l10n;
    if (_password.text.isEmpty) {
      setState(() => _passwordError = l10n.validationRequired);
      return;
    }
    if (!await confirm(context, title: l10n.deleteTitle, message: l10n.deleteBody(DeleteAccountScreen.graceDays), confirmLabel: l10n.deleteButton, destructive: true)) {
      return;
    }
    setState(() {
      _busy = true;
      _passwordError = null;
    });
    try {
      await ref.read(profileRepositoryProvider).requestDeletion(_password.text, reason: _reason.text.trim().isEmpty ? null : _reason.text.trim());
      await ref.read(sessionProvider.notifier).refreshMe();
      _password.clear();
    } on ApiException catch (e) {
      if (mounted) setState(() => _passwordError = e.code == 'invalid_password' ? e.message : errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel() async {
    setState(() => _busy = true);
    try {
      await ref.read(profileRepositoryProvider).cancelDeletion();
      await ref.read(sessionProvider.notifier).refreshMe();
      if (mounted) showToast(context, context.l10n.deleteCancelled);
    } on ApiException catch (e) {
      if (mounted) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final me = ref.watch(meProvider);

    if (me != null && me.deletionPending) {
      return RDetailPage(
        title: l10n.deleteTitle,
        bottomBar: RButton(label: l10n.deleteCancel, onPressed: _cancel, loading: _busy),
        child: RNotice(
          message: l10n.deleteScheduled(formatDate(context, me.deletionScheduledFor)),
          icon: CupertinoIcons.exclamationmark_triangle,
          color: RColors.warning,
        ),
      );
    }

    return RDetailPage(
      title: l10n.deleteTitle,
      bottomBar: RButton.destructive(label: l10n.deleteButton, onPressed: _schedule, loading: _busy),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          RNotice(message: l10n.deleteBody(DeleteAccountScreen.graceDays), icon: CupertinoIcons.info_circle, color: RColors.warning),
          const SizedBox(height: RSpace.xl),
          RTextField(label: l10n.deleteReasonLabel, controller: _reason, maxLines: 3, maxLength: 1000),
          const SizedBox(height: RSpace.xl),
          RTextField(label: l10n.deletePasswordLabel, controller: _password, errorText: _passwordError, obscure: true, autofillHints: const [AutofillHints.password]),
        ],
      ),
    );
  }
}
