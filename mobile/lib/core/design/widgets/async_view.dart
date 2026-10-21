import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../network/api_exception.dart';
import '../../ui/ui_helpers.dart';
import 'states.dart';

/// Renders an [AsyncValue] with consistent loading, error (with retry) and offline handling.
/// While refreshing, previous data stays visible.
class RAsyncView<T> extends StatelessWidget {
  const RAsyncView({super.key, required this.value, required this.data, this.onRetry, this.loading, this.notFound});

  final AsyncValue<T> value;
  final Widget Function(T data) data;
  final VoidCallback? onRetry;
  final Widget? loading;

  /// Shown instead of the error state when the server answers 404 (something not published yet).
  final Widget? notFound;

  @override
  Widget build(BuildContext context) {
    return value.when(
      skipLoadingOnRefresh: true,
      skipLoadingOnReload: true,
      data: data,
      loading: () => loading ?? RLoadingList(semanticLabel: context.l10n.loading),
      error: (error, _) {
        if (notFound != null && error is ApiException && error.isNotFound) return notFound!;
        return RErrorState(
          title: isOffline(error) ? context.l10n.errorOfflineTitle : context.l10n.errorGenericTitle,
          message: errorMessage(context, error),
          retryLabel: context.l10n.actionRetry,
          onRetry: onRetry,
          offline: isOffline(error),
        );
      },
    );
  }
}
