import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/design/design_system.dart';
import '../../../core/ui/ui_helpers.dart';
import '../data/engagement.dart';

/// The inbox: everything RIVALO told you, newest first. Unread messages have a lime dot.
/// Tapping a message marks it read and opens what it is about.
class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  final Set<int> _readLocally = {};

  Future<void> _open(InboxMessage m) async {
    HapticFeedback.selectionClick();
    if (!m.read && !_readLocally.contains(m.id)) {
      setState(() => _readLocally.add(m.id));
      ref.read(engagementRepositoryProvider).markRead(m.id).ignore();
      ref.invalidate(unreadNotificationsProvider);
    }
    final ref0 = m.linkRef;
    switch (m.linkKind) {
      case 'tournament' when ref0 != null:
        context.push(Routes.tournament(ref0));
      case 'match' when ref0 != null && int.tryParse(ref0) != null:
        context.push(Routes.match(int.parse(ref0)));
      case 'team' when ref0 != null:
        context.push(Routes.team(ref0));
      case 'news' when ref0 != null:
        context.push(Routes.newsArticle(ref0));
      case 'invitations':
        context.push(Routes.invitations);
      case 'achievements':
        context.push(Routes.badges);
      case 'page' when ref0 != null:
        context.push(Routes.policy(ref0));
      case 'order' when ref0 != null:
        context.push(Routes.order(ref0));
      case 'seller' when m.type.startsWith('seller_application'):
        context.push(Routes.selling);
      default:
        _showFull(m);
    }
  }

  void _showFull(InboxMessage m) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: RColors.background,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(RRadius.sheet))),
      builder: (_) => SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(RSpace.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(m.title, style: RType.title2),
              const SizedBox(height: RSpace.xs),
              Text(formatDateTime(context, m.createdAt), style: RType.footnote.copyWith(color: RColors.textTertiary)),
              if (m.body != null) ...[const SizedBox(height: RSpace.lg), SelectableText(m.body!, style: RType.body.copyWith(height: 1.45))],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _markAll() async {
    HapticFeedback.mediumImpact();
    await ref.read(engagementRepositoryProvider).markAllRead();
    ref.invalidate(notificationsProvider);
    ref.invalidate(unreadNotificationsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final inbox = ref.watch(notificationsProvider);
    final unread = (inbox.value?.messages ?? const []).where((m) => !m.read && !_readLocally.contains(m.id)).length;

    return RDetailPage(
      title: l10n.inboxNotificationsTitle,
      actions: [
        if (unread > 0) TextButton(onPressed: _markAll, child: Text(l10n.inboxMarkAllRead, style: const TextStyle(color: RColors.accent))),
      ],
      scrollable: false,
      child: RefreshIndicator.adaptive(
        onRefresh: () async {
          ref.invalidate(notificationsProvider);
          ref.invalidate(unreadNotificationsProvider);
          await ref.read(notificationsProvider.future).catchError((_) => const InboxPage(messages: [], unread: 0));
        },
        child: RAsyncView<InboxPage>(
          value: inbox,
          onRetry: () => ref.invalidate(notificationsProvider),
          data: (page) {
            if (page.messages.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [REmptyState(icon: CupertinoIcons.bell, title: l10n.notificationsEmptyTitle, message: l10n.notificationsEmptyBody)],
              );
            }
            return ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
              padding: const EdgeInsets.symmetric(vertical: RSpace.md),
              itemCount: page.messages.length,
              separatorBuilder: (_, _) => const Divider(height: 1, indent: 64),
              itemBuilder: (context, i) {
                final m = page.messages[i];
                return RFadeIn(index: i, child: _MessageRow(message: m.copyWith(read: m.read || _readLocally.contains(m.id)), onTap: () => _open(m)));
              },
            );
          },
        ),
      ),
    );
  }
}

class _MessageRow extends StatelessWidget {
  const _MessageRow({required this.message, required this.onTap});

  final InboxMessage message;
  final VoidCallback onTap;

  static (IconData, Color) look(String type) => switch (type) {
        'team_invitation' => (CupertinoIcons.person_badge_plus_fill, RColors.info),
        'waitlist_promoted' => (CupertinoIcons.checkmark_circle_fill, RColors.success),
        'fixtures_published' => (CupertinoIcons.square_split_2x1_fill, RColors.accent),
        'new_tournament' => (CupertinoIcons.flag_fill, RColors.accent),
        'check_in_reminder' => (CupertinoIcons.qrcode, RColors.warning),
        'match_reminder' => (CupertinoIcons.alarm_fill, RColors.warning),
        'match_scheduled' => (CupertinoIcons.calendar_badge_plus, RColors.info),
        'result_approved' || 'result_corrected' => (CupertinoIcons.sportscourt_fill, RColors.success),
        'dispute_upheld' || 'dispute_rejected' => (CupertinoIcons.scope, RColors.warning),
        'achievement_unlocked' => (CupertinoIcons.rosette, RColors.gold),
        'report_reviewed' => (CupertinoIcons.checkmark_shield_fill, RColors.success),
        'order_placed' || 'order_completed' => (CupertinoIcons.bag_fill, RColors.accent),
        'order_ready' || 'order_out_for_delivery' => (CupertinoIcons.cube_box_fill, RColors.success),
        'order_cancelled' => (CupertinoIcons.bag_badge_minus, RColors.textSecondary),
        'seller_application_approved' || 'seller_listing_approved' || 'seller_reinstated' => (CupertinoIcons.checkmark_seal_fill, RColors.accent),
        'seller_payout_recorded' || 'seller_statement_adjusted' => (CupertinoIcons.money_dollar_circle_fill, RColors.success),
        'seller_listing_changes_requested' || 'seller_suspended' => (CupertinoIcons.exclamationmark_circle_fill, RColors.warning),
        'seller_application_rejected' || 'seller_closed' => (CupertinoIcons.building_2_fill, RColors.textSecondary),
        'moderation_warning' => (CupertinoIcons.exclamationmark_shield_fill, RColors.warning),
        'moderation_photo_removed' || 'moderation_logo_removed' || 'moderation_name_reset' || 'moderation_team_renamed' =>
          (CupertinoIcons.shield_lefthalf_fill, RColors.warning),
        _ => (CupertinoIcons.speaker_2_fill, RColors.accent),
      };

  @override
  Widget build(BuildContext context) {
    final m = message;
    final (icon, color) = look(m.type);
    return RPressable(
      onPressed: onTap,
      semanticLabel: [if (!m.read) context.l10n.inboxUnreadLabel, m.title, ?m.body].join('. '),
      pressedOpacity: 0.7,
      child: ExcludeSemantics(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: RSpace.md),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.14), shape: BoxShape.circle),
                alignment: Alignment.center,
                child: Icon(icon, color: color, size: 20),
              ),
              const SizedBox(width: RSpace.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Expanded(
                        child: Text(m.title,
                            style: RType.subheadline.copyWith(fontWeight: m.read ? FontWeight.w500 : FontWeight.w700), maxLines: 2, overflow: TextOverflow.ellipsis),
                      ),
                      const SizedBox(width: RSpace.sm),
                      Text(_ago(context, m.createdAt), style: RType.caption1.copyWith(color: RColors.textTertiary)),
                    ]),
                    if (m.body != null) ...[
                      const SizedBox(height: 2),
                      Text(m.body!, style: RType.footnote.copyWith(color: RColors.textSecondary), maxLines: 2, overflow: TextOverflow.ellipsis),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: RSpace.sm),
              AnimatedOpacity(
                opacity: m.read ? 0 : 1,
                duration: RMotion.of(context, RMotion.base),
                child: Container(width: 9, height: 9, margin: const EdgeInsets.only(top: 6), decoration: const BoxDecoration(color: RColors.accent, shape: BoxShape.circle)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _ago(BuildContext context, DateTime? t) {
    if (t == null) return '';
    final d = DateTime.now().difference(t);
    final l = context.l10n;
    if (d.inMinutes < 1) return l.timeNow;
    if (d.inHours < 1) return l.durationMinutes(d.inMinutes);
    if (d.inDays < 1) return l.durationHours(d.inHours);
    if (d.inDays < 7) return l.durationDays(d.inDays);
    return formatDate(context, t);
  }
}
