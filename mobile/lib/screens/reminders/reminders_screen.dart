import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api_exception.dart';
import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../core/native_bridge.dart';
import '../../core/reminder_notifications.dart';
import '../../l10n/strings.dart';
import '../../models/memory.dart';
import '../../models/responses.dart';
import '../../widgets/common.dart';
import 'reminder_form_sheet.dart';

class RemindersScreen extends StatefulWidget {
  const RemindersScreen({super.key});

  @override
  State<RemindersScreen> createState() => _RemindersScreenState();
}

class _RemindersScreenState extends State<RemindersScreen> {
  RemindersData? _data;
  bool _loading = true;
  String? _error;
  int? _busyReminderId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<AppState>().api.reminders();
      if (mounted) {
        setState(() {
          _data = data;
          _loading = false;
        });
        await syncReminderNotifications(
          [...data.overdue, ...data.upcoming],
          enabled: context.read<AppState>().settings?.notificationsEnabled ?? true,
        );
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = describeError(error, context.s);
        });
      }
    }
  }

  Future<void> _addReminder() async {
    final data = _data;
    final reminder = await showModalBottomSheet<Reminder>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => ReminderFormSheet(memories: data?.memories ?? const []),
    );

    if (reminder != null && mounted) {
      context.read<AppState>().bumpData();
      await const NativeBridge().requestNotificationPermission();
      if (!mounted) {
        return;
      }
      await _load();
    }
  }

  Future<void> _complete(Reminder reminder) async {
    setState(() => _busyReminderId = reminder.id);
    try {
      await context.read<AppState>().api.completeReminder(reminder.id);
      if (mounted) {
        context.read<AppState>().bumpData();
        await _load();
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    } finally {
      if (mounted) {
        setState(() => _busyReminderId = null);
      }
    }
  }

  Future<void> _snooze(Reminder reminder) async {
    setState(() => _busyReminderId = reminder.id);
    try {
      await context.read<AppState>().api.snoozeReminder(reminder.id);
      if (mounted) {
        context.read<AppState>().bumpData();
        await _load();
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    } finally {
      if (mounted) {
        setState(() => _busyReminderId = null);
      }
    }
  }

  Future<void> _delete(Reminder reminder) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.s.tr('remindersDeleteConfirm')),
        content: Text(reminder.title),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.s.tr('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(context.s.tr('delete')),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) {
      return;
    }

    setState(() => _busyReminderId = reminder.id);
    try {
      await context.read<AppState>().api.deleteReminder(reminder.id);
      if (mounted) {
        context.read<AppState>().bumpData();
        await _load();
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    } finally {
      if (mounted) {
        setState(() => _busyReminderId = null);
      }
    }
  }

  void _showError(Object error) {
    final message = error is ApiException ? error.detail : describeError(error, context.s);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;
    final data = _data;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.tr('remindersTitle')),
        actions: [
          IconButton(
            tooltip: s.tr('remindersAdd'),
            onPressed: _addReminder,
            icon: const Icon(Icons.add_alert_outlined),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addReminder,
        icon: const Icon(Icons.add_rounded),
        label: Text(s.tr('remindersAdd')),
      ),
      body: _loading && data == null
          ? const LoadingView()
          : _error != null && data == null
              ? ErrorState(message: _error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 4, 20, 110),
                    children: [
                      Text(
                        s.tr('remindersSubtitle'),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: Theme.of(context).colorScheme.onSurfaceVariant,
                            ),
                      ),
                      const SizedBox(height: 16),
                      if (data == null ||
                          (data.overdue.isEmpty &&
                              data.upcoming.isEmpty &&
                              data.completed.isEmpty))
                        Padding(
                          padding: const EdgeInsets.only(top: 50),
                          child: EmptyState(
                            icon: Icons.notifications_none_rounded,
                            title: s.tr('remindersEmpty'),
                            body: s.tr('remindersEmptyBody'),
                            actionLabel: s.tr('remindersAdd'),
                            onAction: _addReminder,
                          ),
                        )
                      else ...[
                        if (data.overdue.isNotEmpty) ..._section(
                          s.tr('remindersOverdue'),
                          data.overdue,
                          app,
                          danger: true,
                        ),
                        if (data.upcoming.isNotEmpty) ..._section(
                          s.tr('remindersUpcoming'),
                          data.upcoming,
                          app,
                        ),
                        if (data.completed.isNotEmpty) ..._section(
                          s.tr('remindersCompleted'),
                          data.completed,
                          app,
                          completed: true,
                        ),
                      ],
                    ],
                  ),
                ),
    );
  }

  List<Widget> _section(
    String title,
    List<Reminder> reminders,
    AppState app, {
    bool danger = false,
    bool completed = false,
  }) {
    final scheme = Theme.of(context).colorScheme;
    return [
      SectionHeader(title: '$title (${reminders.length})'),
      ...reminders.map(
        (reminder) => Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: (danger ? scheme.error : completed ? scheme.secondary : scheme.tertiary)
                          .withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(
                      completed
                          ? Icons.check_circle_outline
                          : danger
                              ? Icons.warning_amber_rounded
                              : Icons.notifications_active_outlined,
                      color: danger ? scheme.error : completed ? scheme.secondary : scheme.tertiary,
                      size: 21,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          reminder.title,
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                                decoration: completed ? TextDecoration.lineThrough : null,
                              ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          completed
                              ? context.s.tr('remindersCompletedAt', {
                                  'date': formatDateTime(reminder.completedAt, app.locale),
                                })
                              : context.s.tr('remindersDue', {
                                  'date': formatDateTime(reminder.dueAt, app.locale),
                                }),
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                        ),
                        if (reminder.memory != null) ...[
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              Icon(Icons.link_rounded, size: 13, color: scheme.secondary),
                              const SizedBox(width: 3),
                              Expanded(
                                child: Text(
                                  reminder.memory!.title,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                        color: scheme.secondary,
                                        fontWeight: FontWeight.w600,
                                      ),
                                ),
                              ),
                            ],
                          ),
                        ],
                        if (reminder.notes != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            reminder.notes!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: scheme.onSurfaceVariant,
                                ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (_busyReminderId == reminder.id)
                    const Padding(
                      padding: EdgeInsets.all(8),
                      child: SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    )
                  else
                    PopupMenuButton<String>(
                      onSelected: (value) {
                        switch (value) {
                          case 'complete':
                            _complete(reminder);
                          case 'snooze':
                            _snooze(reminder);
                          case 'delete':
                            _delete(reminder);
                        }
                      },
                      itemBuilder: (context) => [
                        if (!completed)
                          PopupMenuItem(
                            value: 'complete',
                            child: Text(context.s.tr('remindersComplete')),
                          ),
                        if (!completed)
                          PopupMenuItem(
                            value: 'snooze',
                            child: Text(context.s.tr('remindersSnooze')),
                          ),
                        PopupMenuItem(
                          value: 'delete',
                          child: Text(context.s.tr('remindersDelete')),
                        ),
                      ],
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
      const SizedBox(height: 10),
    ];
  }
}
