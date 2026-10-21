import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/check_in_pass.dart';

/// Full-screen check-in pass. Dark-on-white QR for reliable scanning in bright venues; works
/// offline from the copy saved on the device.
class CheckInPassScreen extends ConsumerWidget {
  const CheckInPassScreen({super.key, required this.entryId});

  final int entryId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final pass = ref.watch(checkInPassProvider(entryId));

    return RDetailPage(
      title: l10n.checkInPassTitle,
      child: RAsyncView<CheckInPass>(
        value: pass,
        onRetry: () => ref.invalidate(checkInPassProvider(entryId)),
        data: (p) => _PassBody(pass: p),
      ),
    );
  }
}

class _PassBody extends StatelessWidget {
  const _PassBody({required this.pass});

  final CheckInPass pass;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final name = pass.displayName ?? pass.username;
    final width = MediaQuery.sizeOf(context).width;
    final qrSize = (width - RSpace.gutter(context) * 2 - RSpace.xxxl * 2).clamp(200.0, 320.0);
    final checkedIn = pass.checkInStatus == 'checked_in';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (pass.fromCache) ...[
          ROfflineBanner(message: l10n.checkInPassOffline),
          const SizedBox(height: RSpace.lg),
        ],
        Semantics(header: true, child: Text(pass.tournamentName, style: RType.title2, textAlign: TextAlign.center)),
        const SizedBox(height: RSpace.xs),
        Text(
          [formatDateTime(context, pass.startsAt), if (pass.venueName != null) pass.venueName!].join(' · '),
          style: RType.subheadline.copyWith(color: RColors.textSecondary),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: RSpace.xl),
        Center(
          child: Container(
            padding: const EdgeInsets.all(RSpace.xl),
            decoration: BoxDecoration(
              color: Colors.white, // QR codes scan best dark-on-white, even in a dark app.
              borderRadius: BorderRadius.circular(RRadius.xl),
              border: Border.all(color: checkedIn ? RColors.success : RColors.accent, width: 3),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                QrImageView(
                  data: pass.qrPayload,
                  size: qrSize,
                  backgroundColor: Colors.white,
                  semanticsLabel: l10n.checkInPassQrLabel(name),
                  errorCorrectionLevel: QrErrorCorrectLevel.M,
                  eyeStyle: const QrEyeStyle(eyeShape: QrEyeShape.square, color: Color(0xFF0B1020)),
                  dataModuleStyle: const QrDataModuleStyle(dataModuleShape: QrDataModuleShape.square, color: Color(0xFF0B1020)),
                ),
                const SizedBox(height: RSpace.md),
                Text(name, style: RType.headline.copyWith(color: const Color(0xFF0B1020))),
                Text('@${pass.username} · ${pass.entryName}', style: RType.footnote.copyWith(color: const Color(0xFF3A4868))),
              ],
            ),
          ),
        ),
        const SizedBox(height: RSpace.lg),
        if (checkedIn)
          RNotice(message: l10n.checkInPassDone, icon: CupertinoIcons.checkmark_seal_fill, color: RColors.success)
        else ...[
          RNotice(message: l10n.checkInPassInstruction, icon: CupertinoIcons.qrcode_viewfinder),
          if (pass.checkInClosesAt != null) ...[
            const SizedBox(height: RSpace.sm),
            Text(l10n.checkInClosesAt(formatDateTime(context, pass.checkInClosesAt)),
                style: RType.footnote.copyWith(color: RColors.warning), textAlign: TextAlign.center),
          ],
        ],
        const SizedBox(height: RSpace.md),
        Text(l10n.checkInPassBrightness, style: RType.footnote.copyWith(color: RColors.textSecondary), textAlign: TextAlign.center),
        const SizedBox(height: RSpace.xs),
        // Readable fallback if scanning fails: staff can type this.
        SelectableText(l10n.checkInPassCode(pass.code),
            style: RType.footnote.copyWith(color: RColors.textTertiary, fontFamily: 'monospace'), textAlign: TextAlign.center),
      ],
    );
  }
}
