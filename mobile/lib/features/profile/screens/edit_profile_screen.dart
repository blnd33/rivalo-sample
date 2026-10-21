import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../auth/state/session_controller.dart';
import '../data/profile_repository.dart';

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  late final _me = ref.read(meProvider)!;
  late final _displayName = TextEditingController(text: _me.displayName ?? '');
  late final _city = TextEditingController(text: _me.city ?? '');
  late final _phone = TextEditingController(text: _me.phone ?? '');
  bool _saving = false;
  bool _uploading = false;
  Map<String, String?> _errors = {};

  @override
  void dispose() {
    _displayName.dispose();
    _city.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _errors = {};
    });
    try {
      final me = await ref.read(profileRepositoryProvider).update(
            displayName: _displayName.text.trim(),
            city: _city.text.trim(),
            phone: _phone.text.trim(),
          );
      ref.read(sessionProvider.notifier).updateMe(me);
      if (!mounted) return;
      showToast(context, context.l10n.profileSaved);
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = {'display_name': e.fieldError('display_name'), 'city': e.fieldError('city'), 'phone': e.fieldError('phone')});
      if (_errors.values.every((v) => v == null)) showToast(context, errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickPhoto(ImageSource source) async {
    final file = await ImagePicker().pickImage(source: source, maxWidth: 1600, maxHeight: 1600, imageQuality: 90);
    if (file == null || !mounted) return;
    setState(() => _uploading = true);
    try {
      final me = await ref.read(profileRepositoryProvider).uploadAvatar(file.path);
      ref.read(sessionProvider.notifier).updateMe(me);
      if (mounted) showToast(context, context.l10n.photoUpdated);
    } on ApiException catch (e) {
      if (mounted) showToast(context, e.fieldError('avatar') ?? errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _photoMenu() async {
    final l10n = context.l10n;
    final hasPhoto = ref.read(meProvider)?.avatarUrl != null;
    final choice = await showCupertinoModalPopup<String>(
      context: context,
      builder: (ctx) => CupertinoActionSheet(
        actions: [
          CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx, 'library'), child: Text(l10n.choosePhoto)),
          CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx, 'camera'), child: Text(l10n.takePhoto)),
          if (hasPhoto) CupertinoActionSheetAction(isDestructiveAction: true, onPressed: () => Navigator.pop(ctx, 'remove'), child: Text(l10n.removePhoto)),
        ],
        cancelButton: CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx), child: Text(l10n.actionCancel)),
      ),
    );
    switch (choice) {
      case 'library':
        await _pickPhoto(ImageSource.gallery);
      case 'camera':
        await _pickPhoto(ImageSource.camera);
      case 'remove':
        setState(() => _uploading = true);
        try {
          final me = await ref.read(profileRepositoryProvider).removeAvatar();
          ref.read(sessionProvider.notifier).updateMe(me);
        } on ApiException catch (e) {
          if (mounted) showToast(context, errorMessage(context, e));
        } finally {
          if (mounted) setState(() => _uploading = false);
        }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final me = ref.watch(meProvider) ?? _me;

    return RDetailPage(
      title: l10n.editProfileTitle,
      bottomBar: RButton(label: l10n.actionSave, onPressed: _save, loading: _saving),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: RPressable(
              onPressed: _uploading ? null : _photoMenu,
              semanticLabel: l10n.changePhoto,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  RAvatar(name: me.name, imageUrl: me.avatarUrl, size: 96, ring: true),
                  if (_uploading) const CircularProgressIndicator.adaptive(),
                ],
              ),
            ),
          ),
          Center(child: RButton.tertiary(label: l10n.changePhoto, onPressed: _uploading ? null : _photoMenu)),
          const SizedBox(height: RSpace.xl),
          RTextField(label: l10n.displayNameLabel, controller: _displayName, helper: l10n.displayNameHelper, errorText: _errors['display_name'], maxLength: 60, textCapitalization: TextCapitalization.words),
          const SizedBox(height: RSpace.xl),
          RTextField(label: l10n.cityLabel, controller: _city, errorText: _errors['city'], maxLength: 80, textCapitalization: TextCapitalization.words, autofillHints: const [AutofillHints.addressCity]),
          const SizedBox(height: RSpace.xl),
          RTextField(
            label: l10n.phoneLabel,
            controller: _phone,
            helper: l10n.phoneHelper,
            errorText: _errors['phone'],
            keyboardType: TextInputType.phone,
            autofillHints: const [AutofillHints.telephoneNumber],
            maxLength: 32,
          ),
        ],
      ),
    );
  }
}
