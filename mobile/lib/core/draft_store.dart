import 'dart:convert';
import 'dart:io';

import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'json_utils.dart';

/// A capture that has not reached the server yet, kept on this device only.
class CaptureDraft {
  const CaptureDraft({
    required this.sourceType,
    required this.title,
    required this.body,
    required this.sourceUrl,
    required this.tags,
    required this.language,
    required this.eventDate,
    required this.collectionId,
    required this.filePath,
    required this.fileName,
    required this.savedAt,
  });

  factory CaptureDraft.fromJson(Map<String, dynamic> json) {
    return CaptureDraft(
      sourceType: asText(json['source_type'], 'text'),
      title: asText(json['title']),
      body: asText(json['body']),
      sourceUrl: asText(json['source_url']),
      tags: asText(json['tags']),
      language: asText(json['language'], 'en'),
      eventDate: asNullableText(json['event_date']),
      collectionId: json['collection_id'] == null ? null : asInt(json['collection_id']),
      filePath: asNullableText(json['file_path']),
      fileName: asNullableText(json['file_name']),
      savedAt: asDate(json['saved_at']) ?? DateTime.now(),
    );
  }

  final String sourceType;
  final String title;
  final String body;
  final String sourceUrl;
  final String tags;
  final String language;
  final String? eventDate;
  final int? collectionId;
  final String? filePath;
  final String? fileName;
  final DateTime savedAt;

  bool get isEmpty {
    return title.trim().isEmpty &&
        body.trim().isEmpty &&
        sourceUrl.trim().isEmpty &&
        filePath == null &&
        tags.trim().isEmpty;
  }

  Map<String, dynamic> toJson() {
    return {
      'source_type': sourceType,
      'title': title,
      'body': body,
      'source_url': sourceUrl,
      'tags': tags,
      'language': language,
      'event_date': eventDate,
      'collection_id': collectionId,
      'file_path': filePath,
      'file_name': fileName,
      'saved_at': savedAt.toIso8601String(),
    };
  }

  CaptureDraft copyWith({String? filePath, String? fileName}) {
    return CaptureDraft(
      sourceType: sourceType,
      title: title,
      body: body,
      sourceUrl: sourceUrl,
      tags: tags,
      language: language,
      eventDate: eventDate,
      collectionId: collectionId,
      filePath: filePath ?? this.filePath,
      fileName: fileName ?? this.fileName,
      savedAt: savedAt,
    );
  }
}

/// Stores a single capture draft in shared preferences, copying any attached
/// file into app-private storage so it survives a restart.
class DraftStore {
  static const _key = 'realitylog_capture_draft';
  static const _folder = 'capture_drafts';

  Future<CaptureDraft> save(CaptureDraft draft) async {
    final persisted = await _persistFile(draft);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(persisted.toJson()));
    return persisted;
  }

  Future<CaptureDraft?> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_key);
      if (raw == null || raw.isEmpty) {
        return null;
      }
      final draft = CaptureDraft.fromJson(asMap(jsonDecode(raw)));
      return draft.isEmpty ? null : draft;
    } catch (_) {
      return null;
    }
  }

  Future<void> clear() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_key);
      await prefs.remove(_key);
      if (raw == null) {
        return;
      }
      final draft = CaptureDraft.fromJson(asMap(jsonDecode(raw)));
      final path = draft.filePath;
      if (path != null && path.contains(_folder)) {
        final file = File(path);
        if (await file.exists()) {
          await file.delete();
        }
      }
    } catch (_) {
      // Clearing a draft must never block the capture flow.
    }
  }

  Future<CaptureDraft> _persistFile(CaptureDraft draft) async {
    final path = draft.filePath;
    if (path == null || path.isEmpty || path.contains(_folder)) {
      return draft;
    }

    try {
      final documents = await getApplicationDocumentsDirectory();
      final directory = Directory('${documents.path}/$_folder');
      await directory.create(recursive: true);
      final safeName = (draft.fileName ?? 'attachment')
          .replaceAll(RegExp(r'[^\w.\-]'), '_');
      final target = '${directory.path}/${DateTime.now().millisecondsSinceEpoch}_$safeName';
      final copied = await File(path).copy(target);
      return draft.copyWith(filePath: copied.path, fileName: draft.fileName);
    } catch (_) {
      return draft;
    }
  }
}
