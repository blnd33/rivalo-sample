import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../tokens.dart';

/// Text field with a visible label above the input (never placeholder-only), helper text and
/// an inline error placed directly under the field. Supports autofill and paste.
class RTextField extends StatelessWidget {
  const RTextField({
    super.key,
    required this.label,
    this.controller,
    this.hint,
    this.helper,
    this.errorText,
    this.obscure = false,
    this.keyboardType,
    this.textInputAction,
    this.autofillHints,
    this.onSubmitted,
    this.onChanged,
    this.enabled = true,
    this.maxLines = 1,
    this.maxLength,
    this.inputFormatters,
    this.suffix,
    this.autofocus = false,
    this.focusNode,
    this.textCapitalization = TextCapitalization.none,
    this.autocorrect = true,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final String? helper;
  final String? errorText;
  final bool obscure;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final Iterable<String>? autofillHints;
  final ValueChanged<String>? onSubmitted;
  final ValueChanged<String>? onChanged;
  final bool enabled;
  final int maxLines;
  final int? maxLength;
  final List<TextInputFormatter>? inputFormatters;
  final Widget? suffix;
  final bool autofocus;
  final FocusNode? focusNode;
  final TextCapitalization textCapitalization;
  final bool autocorrect;

  @override
  Widget build(BuildContext context) {
    // The label is always visible above the text (never placeholder-only) and is also the
    // field's accessible name.
    return TextField(
      controller: controller,
      focusNode: focusNode,
      autofocus: autofocus,
      enabled: enabled,
      obscureText: obscure,
      enableSuggestions: !obscure,
      autocorrect: autocorrect && !obscure,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      autofillHints: autofillHints,
      onSubmitted: onSubmitted,
      onChanged: onChanged,
      maxLines: obscure ? 1 : maxLines,
      maxLength: maxLength,
      inputFormatters: inputFormatters,
      textCapitalization: textCapitalization,
      style: RType.body,
      decoration: InputDecoration(
        labelText: label,
        floatingLabelBehavior: FloatingLabelBehavior.always,
        hintText: hint,
        helperText: helper,
        errorText: errorText,
        suffixIcon: suffix,
        counterText: '',
      ),
    );
  }
}

/// Adaptive switch row for settings (Apple switch look on iOS, Material on Android).
class RSwitchRow extends StatelessWidget {
  const RSwitchRow({super.key, required this.title, this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String? subtitle;
  final bool value;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) {
    return MergeSemantics(
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: RSize.minTouchTarget + 4),
        child: Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(RSpace.lg, RSpace.sm, RSpace.md, RSpace.sm),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: RType.body),
                    if (subtitle != null) ...[
                      const SizedBox(height: RSpace.xxs),
                      Text(subtitle!, style: RType.footnote.copyWith(color: RColors.textSecondary)),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: RSpace.sm),
              Switch.adaptive(value: value, onChanged: onChanged, activeTrackColor: RColors.accent),
            ],
          ),
        ),
      ),
    );
  }
}
