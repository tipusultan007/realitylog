import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Content handed to RealityLog through the Android share sheet.
class SharedContent {
  const SharedContent({this.text, this.subject, this.filePath, this.fileName});

  final String? text;
  final String? subject;
  final String? filePath;
  final String? fileName;

  bool get isEmpty {
    return (text?.trim().isEmpty ?? true) && (filePath?.isEmpty ?? true);
  }

  String get suggestedTitle {
    final subjectText = subject?.trim();
    if (subjectText != null && subjectText.isNotEmpty) {
      return subjectText;
    }
    final body = text?.trim();
    if (body == null || body.isEmpty) {
      return fileName ?? '';
    }
    final firstLine = body.split('\n').first.trim();
    return firstLine.length > 120 ? firstLine.substring(0, 120) : firstLine;
  }

  bool get looksLikeLink {
    final body = text?.trim() ?? '';
    return body.startsWith('http://') || body.startsWith('https://');
  }
}

/// Bridge to the small amount of Android-specific work RealityLog needs:
/// voice memo recording, reminder notifications, and shared content.
class NativeBridge {
  const NativeBridge();

  static const _channel = MethodChannel('app.realitylog/mobile');

  bool get isSupported => defaultTargetPlatform == TargetPlatform.android;

  Future<bool> notificationPermissionGranted() async {
    if (!isSupported) {
      return false;
    }
    try {
      return await _channel.invokeMethod<bool>('notificationPermissionGranted') ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }

  Future<bool> requestNotificationPermission() async {
    if (!isSupported) {
      return false;
    }
    try {
      return await _channel.invokeMethod<bool>('requestNotificationPermission') ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }

  /// Replaces the device's scheduled reminder alarms with [reminders].
  ///
  /// Each item needs an `id`, `title`, `due_at` (epoch milliseconds), and an
  /// optional `body`. Returns the number of future alarms scheduled.
  Future<int> syncReminders(List<Map<String, Object?>> reminders) async {
    if (!isSupported) {
      return 0;
    }
    try {
      return await _channel.invokeMethod<int>('syncReminders', reminders) ?? 0;
    } on PlatformException {
      return 0;
    } on MissingPluginException {
      return 0;
    }
  }

  Future<void> cancelAllReminders() async {
    if (!isSupported) {
      return;
    }
    try {
      await _channel.invokeMethod<void>('cancelAllReminders');
    } catch (_) {
      // Nothing to cancel.
    }
  }

  Future<bool> startRecording() async {
    if (!isSupported) {
      return false;
    }
    try {
      return await _channel.invokeMethod<bool>('startRecording') ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }

  Future<({String path, String name})?> stopRecording() async {
    if (!isSupported) {
      return null;
    }
    try {
      final result = await _channel.invokeMapMethod<String, dynamic>('stopRecording');
      final path = result?['path'];
      final name = result?['name'];
      if (path is! String || path.isEmpty) {
        return null;
      }
      return (path: path, name: name is String ? name : 'voice.m4a');
    } on PlatformException {
      return null;
    } on MissingPluginException {
      return null;
    }
  }

  Future<void> cancelRecording() async {
    if (!isSupported) {
      return;
    }
    try {
      await _channel.invokeMethod<void>('cancelRecording');
    } catch (_) {
      // Nothing to cancel.
    }
  }

  Future<SharedContent?> takeSharedContent() async {
    if (!isSupported) {
      return null;
    }
    try {
      final result = await _channel.invokeMapMethod<String, dynamic>('takeSharedContent');
      if (result == null) {
        return null;
      }
      final content = SharedContent(
        text: result['text'] is String ? result['text'] as String : null,
        subject: result['subject'] is String ? result['subject'] as String : null,
        filePath: result['file_path'] is String ? result['file_path'] as String : null,
        fileName: result['file_name'] is String ? result['file_name'] as String : null,
      );
      return content.isEmpty ? null : content;
    } on PlatformException {
      return null;
    } on MissingPluginException {
      return null;
    }
  }
}
