import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/profile_repository.dart';

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _next = TextEditingController();
  bool _busy = false;
  String? _currentError;
  String? _nextError;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l10n = context.l10n;
    final pw = _next.text;
    setState(() {
      _currentError = _current.text.isEmpty ? l10n.validationRequired : null;
      _nextError = (pw.length >= 10 && RegExp(r'[A-Za-z]').hasMatch(pw) && RegExp(r'\d').hasMatch(pw)) ? null : l10n.validationPassword;
    });
    if (_currentError != null || _nextError != null) return;

    setState(() => _busy = true);
    try {
      await ref.read(profileRepositoryProvider).changePassword(_current.text, pw);
      if (!mounted) return;
      showToast(context, l10n.passwordChanged);
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _currentError = e.code == 'invalid_password' ? e.message : e.fieldError('current_password');
        _nextError = e.fieldError('password');
      });
      if (_currentError == null && _nextError == null) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return RDetailPage(
      title: l10n.passwordTitle,
      bottomBar: RButton(label: l10n.actionSave, onPressed: _submit, loading: _busy),
      child: AutofillGroup(
        child: Column(
          children: [
            RTextField(label: l10n.currentPasswordLabel, controller: _current, errorText: _currentError, obscure: true, autofillHints: const [AutofillHints.password]),
            const SizedBox(height: RSpace.xl),
            RTextField(
              label: l10n.newPasswordLabel,
              controller: _next,
              helper: l10n.passwordHelper,
              errorText: _nextError,
              obscure: true,
              autofillHints: const [AutofillHints.newPassword],
              onSubmitted: (_) => _submit(),
            ),
          ],
        ),
      ),
    );
  }
}
