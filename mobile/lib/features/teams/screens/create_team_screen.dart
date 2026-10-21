import 'package:flutter/cupertino.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/ui_helpers.dart';
import '../../catalog/data/catalog.dart';
import '../data/teams.dart';

class CreateTeamScreen extends ConsumerStatefulWidget {
  const CreateTeamScreen({super.key, this.initialSportId});

  final int? initialSportId;

  @override
  ConsumerState<CreateTeamScreen> createState() => _CreateTeamScreenState();
}

class _CreateTeamScreenState extends ConsumerState<CreateTeamScreen> {
  final _name = TextEditingController();
  late int? _sportId = widget.initialSportId;
  bool _busy = false;
  String? _nameError;
  String? _formError;

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _create() async {
    final l10n = context.l10n;
    setState(() {
      _nameError = _name.text.trim().length < 2 ? l10n.validationRequired : null;
      _formError = _sportId == null ? '${l10n.teamSportLabel}: ${l10n.validationRequired}' : null;
    });
    if (_nameError != null || _formError != null) return;

    setState(() => _busy = true);
    try {
      final team = await ref.read(teamsRepositoryProvider).create(sportId: _sportId!, name: _name.text.trim());
      ref.invalidate(myTeamsProvider);
      if (!mounted) return;
      context.pop();
      context.push(Routes.team(team.slug));
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _nameError = e.fieldError('name');
          _formError = _nameError == null ? errorMessage(context, e) : null;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final sports = ref.watch(sportsProvider);

    return RDetailPage(
      title: l10n.teamCreateTitle,
      bottomBar: RButton(label: l10n.teamsCreate, onPressed: _create, loading: _busy),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_formError != null) ...[
            RNotice(message: _formError!, icon: CupertinoIcons.exclamationmark_circle, color: RColors.error),
            const SizedBox(height: RSpace.lg),
          ],
          RTextField(
            label: l10n.teamNameLabel,
            controller: _name,
            errorText: _nameError,
            maxLength: 60,
            textCapitalization: TextCapitalization.words,
            autofocus: true,
          ),
          RAsyncView<List<Sport>>(
            value: sports,
            onRetry: () => ref.invalidate(sportsProvider),
            data: (list) => RGroupedSection(
              header: l10n.teamSportLabel,
              footer: l10n.teamCreateHelper,
              children: [
                for (final s in list.where((s) => s.supportsTeams))
                  Semantics(
                    selected: _sportId == s.id,
                    child: RListRow(
                      title: s.name,
                      leading: SportGlyph(s.icon, size: RSize.iconMd),
                      trailing: _sportId == s.id ? const Icon(CupertinoIcons.checkmark_alt, color: RColors.accent) : null,
                      showChevron: false,
                      onTap: () => setState(() => _sportId = s.id),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
