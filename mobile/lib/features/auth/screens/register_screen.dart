import 'package:flutter/cupertino.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../safety/data/safety.dart';
import '../state/session_controller.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _username = TextEditingController();
  final _email = TextEditingController();
  final _displayName = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  bool _busy = false;
  Map<String, String?> _errors = {};
  String? _formError;

  static final _usernamePattern = RegExp(r'^[A-Za-z0-9_.]{3,30}$');
  static final _emailPattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  @override
  void dispose() {
    for (final c in [_username, _email, _displayName, _password]) {
      c.dispose();
    }
    super.dispose();
  }

  bool _validate() {
    final l10n = context.l10n;
    final pw = _password.text;
    _errors = {
      'username': _usernamePattern.hasMatch(_username.text.trim()) ? null : l10n.validationUsername,
      'email': _emailPattern.hasMatch(_email.text.trim()) ? null : l10n.validationEmail,
      'password': (pw.length >= 10 && RegExp(r'[A-Za-z]').hasMatch(pw) && RegExp(r'\d').hasMatch(pw)) ? null : l10n.validationPassword,
    };
    setState(() {});
    return _errors.values.every((e) => e == null);
  }

  Future<void> _submit() async {
    setState(() => _formError = null);
    if (!_validate()) return;

    setState(() => _busy = true);
    TextInput.finishAutofillContext();
    try {
      await ref.read(sessionProvider.notifier).register(
            username: _username.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            displayName: _displayName.text,
            locale: Localizations.localeOf(context).languageCode,
          );
      // The router redirects to the verify-email screen.
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _errors = {
          'username': e.fieldError('username'),
          'email': e.fieldError('email'),
          'password': e.fieldError('password'),
          'display_name': e.fieldError('display_name'),
        };
        if (_errors.values.every((v) => v == null)) _formError = errorMessage(context, e);
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return RDetailPage(
      title: l10n.createAccountTitle,
      child: AutofillGroup(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_formError != null) ...[
              RNotice(message: _formError!, icon: CupertinoIcons.exclamationmark_circle, color: RColors.error),
              const SizedBox(height: RSpace.lg),
            ],
            RTextField(
              label: l10n.usernameLabel,
              controller: _username,
              helper: l10n.usernameHelper,
              errorText: _errors['username'],
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.newUsername],
              autocorrect: false,
            ),
            const SizedBox(height: RSpace.xl),
            RTextField(
              label: l10n.emailLabel,
              controller: _email,
              helper: l10n.emailHelper,
              errorText: _errors['email'],
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.email],
              autocorrect: false,
            ),
            const SizedBox(height: RSpace.xl),
            RTextField(
              label: l10n.displayNameLabel,
              controller: _displayName,
              helper: l10n.displayNameHelper,
              errorText: _errors['display_name'],
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.name],
              textCapitalization: TextCapitalization.words,
              maxLength: 60,
            ),
            const SizedBox(height: RSpace.xl),
            RTextField(
              label: l10n.passwordLabel,
              controller: _password,
              helper: l10n.passwordHelper,
              errorText: _errors['password'],
              obscure: _obscure,
              textInputAction: TextInputAction.done,
              autofillHints: const [AutofillHints.newPassword],
              onSubmitted: (_) => _submit(),
              suffix: RIconButton(
                icon: _obscure ? CupertinoIcons.eye : CupertinoIcons.eye_slash,
                semanticLabel: _obscure ? l10n.showPassword : l10n.hidePassword,
                color: RColors.textSecondary,
                size: RSize.iconMd,
                onPressed: () => setState(() => _obscure = !_obscure),
              ),
            ),
            const SizedBox(height: RSpace.xl),
            RNotice(message: l10n.registerLegalNotice),
            Wrap(
              alignment: WrapAlignment.center,
              children: [
                RButton.tertiary(label: l10n.policyTerms, onPressed: () => context.push(Routes.policy(PolicySlugs.terms))),
                RButton.tertiary(label: l10n.policyPrivacy, onPressed: () => context.push(Routes.policy(PolicySlugs.privacy))),
                RButton.tertiary(label: l10n.policyGuidelines, onPressed: () => context.push(Routes.policy(PolicySlugs.guidelines))),
              ],
            ),
            const SizedBox(height: RSpace.xl),
            RButton(label: l10n.createAccountButton, onPressed: _submit, loading: _busy),
            const SizedBox(height: RSpace.lg),
            Center(child: RButton.tertiary(label: l10n.haveAccountPrompt, onPressed: () => context.pushReplacement(Routes.login))),
          ],
        ),
      ),
    );
  }
}
