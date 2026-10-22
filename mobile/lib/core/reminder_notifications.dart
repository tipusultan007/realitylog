import '../models/memory.dart';
import 'native_bridge.dart';

/// Mirrors the user's pending reminders into Android alarms.
///
/// The server remains the source of truth. This only keeps a local schedule so
/// reminders still arrive when the app is closed or offline.
Future<int> syncReminderNotifications(
  List<Reminder> reminders, {
  bool enabled = true,
  NativeBridge bridge = const NativeBridge(),
}) async {
  if (!bridge.isSupported) {
    return 0;
  }

  if (!enabled) {
    await bridge.cancelAllReminders();
    return 0;
  }

  final now = DateTime.now();
  final payload = reminders
      .where((reminder) => !reminder.isCompleted && reminder.dueAt.isAfter(now))
      .map(
        (reminder) => <String, Object?>{
          'id': reminder.id,
          'title': reminder.title,
          'body': reminder.notes,
          'due_at': reminder.dueAt.millisecondsSinceEpoch,
        },
      )
      .toList(growable: false);

  return bridge.syncReminders(payload);
}
