import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../state/session_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _login = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  bool _busy = false;
  String? _loginError;
  String? _passwordError;
  String? _formError;

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l10n = context.l10n;
    setState(() {
      _loginError = _login.text.trim().isEmpty ? l10n.validationRequired : null;
      _passwordError = _password.text.isEmpty ? l10n.validationRequired : null;
      _formError = null;
    });
    if (_loginError != null || _passwordError != null) return;

    setState(() => _busy = true);
    TextInput.finishAutofillContext();
    try {
      await ref.read(sessionProvider.notifier).login(_login.text.trim(), _password.text);
      // Router redirects to Home on success.
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loginError = e.fieldError('login');
        _formError = _loginError == null ? errorMessage(context, e) : null;
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return RDetailPage(
      title: l10n.signInTitle,
      child: AutofillGroup(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: RSpace.lg),
            const Align(alignment: AlignmentDirectional.centerStart, child: RivaloWordmark(height: 26)),
            const SizedBox(height: RSpace.xxxl),
            if (_formError != null) ...[
              RNotice(message: _formError!, icon: CupertinoIcons.exclamationmark_circle, color: RColors.error),
              const SizedBox(height: RSpace.lg),
            ],
            RTextField(
              label: l10n.loginFieldLabel,
              controller: _login,
              errorText: _loginError,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.email, AutofillHints.username],
              autocorrect: false,
            ),
            const SizedBox(height: RSpace.xl),
            RTextField(
              label: l10n.passwordLabel,
              controller: _password,
              errorText: _passwordError,
              obscure: _obscure,
              textInputAction: TextInputAction.done,
              autofillHints: const [AutofillHints.password],
              onSubmitted: (_) => _submit(),
              suffix: RIconButton(
                icon: _obscure ? CupertinoIcons.eye : CupertinoIcons.eye_slash,
                semanticLabel: _obscure ? l10n.showPassword : l10n.hidePassword,
                color: RColors.textSecondary,
                size: RSize.iconMd,
                onPressed: () => setState(() => _obscure = !_obscure),
              ),
            ),
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: RButton.tertiary(label: l10n.forgotPasswordLink, onPressed: () => context.push(Routes.forgotPassword)),
            ),
            const SizedBox(height: RSpace.lg),
            RButton(label: l10n.signInButton, onPressed: _submit, loading: _busy),
            const SizedBox(height: RSpace.lg),
            Center(child: RButton.tertiary(label: l10n.noAccountPrompt, onPressed: () => context.pushReplacement(Routes.register))),
          ],
        ),
      ),
    );
  }
}
