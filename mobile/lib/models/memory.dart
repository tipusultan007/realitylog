import '../core/json_utils.dart';

class Tag {
  const Tag({required this.id, required this.name, required this.color});

  factory Tag.fromJson(Map<String, dynamic> json) {
    return Tag(
      id: asInt(json['id']),
      name: asText(json['name']),
      color: asText(json['color'], '#68756C'),
    );
  }

  final int id;
  final String name;
  final String color;
}

class Collection {
  const Collection({
    required this.id,
    required this.name,
    required this.description,
    required this.color,
    required this.memoriesCount,
  });

  factory Collection.fromJson(Map<String, dynamic> json) {
    return Collection(
      id: asInt(json['id']),
      name: asText(json['name']),
      description: asNullableText(json['description']),
      color: asText(json['color'], '#68756C'),
      memoriesCount: asInt(json['memories_count']),
    );
  }

  final int id;
  final String name;
  final String? description;
  final String color;
  final int memoriesCount;
}

class MemoryEntity {
  const MemoryEntity({
    required this.id,
    required this.type,
    required this.name,
    required this.label,
    required this.metadata,
    required this.provenance,
    required this.confidence,
  });

  factory MemoryEntity.fromJson(Map<String, dynamic> json) {
    return MemoryEntity(
      id: asInt(json['id']),
      type: asText(json['type']),
      name: asText(json['name']),
      label: asText(json['label']),
      metadata: asMap(json['metadata']),
      provenance: asNullableText(json['provenance']),
      confidence: json['confidence'] is num ? (json['confidence'] as num).toDouble() : null,
    );
  }

  final int id;
  final String type;
  final String name;
  final String label;
  final Map<String, dynamic> metadata;
  final String? provenance;
  final double? confidence;
}

class MemoryAttachment {
  const MemoryAttachment({
    required this.id,
    required this.originalName,
    required this.mimeType,
    required this.size,
    required this.humanSize,
    required this.kind,
    required this.status,
    required this.isImage,
    required this.isDuplicate,
    required this.duplicateOfAttachmentId,
    required this.url,
    required this.downloadUrl,
  });

  factory MemoryAttachment.fromJson(Map<String, dynamic> json) {
    return MemoryAttachment(
      id: asInt(json['id']),
      originalName: asText(json['original_name']),
      mimeType: asText(json['mime_type']),
      size: asInt(json['size']),
      humanSize: asText(json['human_size']),
      kind: asText(json['kind']),
      status: asText(json['status']),
      isImage: asBool(json['is_image']),
      isDuplicate: asBool(json['is_duplicate']),
      duplicateOfAttachmentId: json['duplicate_of_attachment_id'] == null
          ? null
          : asInt(json['duplicate_of_attachment_id']),
      url: asText(json['url']),
      downloadUrl: asText(json['download_url']),
    );
  }

  final int id;
  final String originalName;
  final String mimeType;
  final int size;
  final String humanSize;
  final String kind;
  final String status;
  final bool isImage;
  final bool isDuplicate;
  final int? duplicateOfAttachmentId;
  final String url;
  final String downloadUrl;

  bool get isSvg {
    return mimeType.contains('svg') || originalName.toLowerCase().endsWith('.svg');
  }

  bool get isPdf {
    return mimeType == 'application/pdf' || originalName.toLowerCase().endsWith('.pdf');
  }
}

class Memory {
  const Memory({
    required this.id,
    required this.title,
    required this.memoryType,
    required this.typeLabel,
    required this.sourceType,
    required this.sourceLabel,
    required this.sourceUrl,
    required this.body,
    required this.summary,
    required this.excerpt,
    required this.extractedText,
    required this.detectedLanguage,
    required this.eventDate,
    required this.capturedAt,
    required this.displayDate,
    required this.processingStatus,
    required this.statusLabel,
    required this.processingError,
    required this.aiMetadata,
    required this.amount,
    required this.isFavorite,
    required this.isArchived,
    required this.tags,
    required this.collections,
    required this.attachments,
    required this.entities,
    required this.reminders,
    required this.cover,
    required this.createdAt,
    required this.updatedAt,
    this.relationshipId,
    this.relationshipType,
  });

  factory Memory.fromJson(Map<String, dynamic> json) {
    final coverJson = json['cover'];
    return Memory(
      id: asInt(json['id']),
      title: asText(json['title']),
      memoryType: asText(json['memory_type'], 'note'),
      typeLabel: asText(json['type_label']),
      sourceType: asText(json['source_type'], 'text'),
      sourceLabel: asText(json['source_label']),
      sourceUrl: asNullableText(json['source_url']),
      body: asText(json['body']),
      summary: asNullableText(json['summary']),
      excerpt: asText(json['excerpt']),
      extractedText: asNullableText(json['extracted_text']),
      detectedLanguage: asText(json['detected_language'], 'en'),
      eventDate: asDate(json['event_date']),
      capturedAt: asDate(json['captured_at']),
      displayDate: asText(json['display_date']),
      processingStatus: asText(json['processing_status'], 'ready'),
      statusLabel: asText(json['status_label']),
      processingError: asNullableText(json['processing_error']),
      aiMetadata: asMap(json['ai_metadata']),
      amount: asNullableText(json['amount']),
      isFavorite: asBool(json['is_favorite']),
      isArchived: asBool(json['is_archived']),
      tags: asMaps(json['tags']).map(Tag.fromJson).toList(growable: false),
      collections: asMaps(json['collections']).map(Collection.fromJson).toList(growable: false),
      attachments: asMaps(json['attachments']).map(MemoryAttachment.fromJson).toList(growable: false),
      entities: asMaps(json['entities']).map(MemoryEntity.fromJson).toList(growable: false),
      reminders: asMaps(json['reminders']).map(Reminder.fromJson).toList(growable: false),
      cover: coverJson is Map ? MemoryAttachment.fromJson(asMap(coverJson)) : null,
      createdAt: asDate(json['created_at']),
      updatedAt: asDate(json['updated_at']),
      relationshipId: json['relationship_id'] == null ? null : asInt(json['relationship_id']),
      relationshipType: asNullableText(json['relationship_type']),
    );
  }

  final int id;
  final String title;
  final String memoryType;
  final String typeLabel;
  final String sourceType;
  final String sourceLabel;
  final String? sourceUrl;
  final String body;
  final String? summary;
  final String excerpt;
  final String? extractedText;
  final String detectedLanguage;
  final DateTime? eventDate;
  final DateTime? capturedAt;
  final String displayDate;
  final String processingStatus;
  final String statusLabel;
  final String? processingError;
  final Map<String, dynamic> aiMetadata;
  final String? amount;
  final bool isFavorite;
  final bool isArchived;
  final List<Tag> tags;
  final List<Collection> collections;
  final List<MemoryAttachment> attachments;
  final List<MemoryEntity> entities;
  final List<Reminder> reminders;
  final MemoryAttachment? cover;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final int? relationshipId;
  final String? relationshipType;

  bool get isProcessing {
    return processingStatus == 'queued' || processingStatus == 'processing';
  }

  bool get needsReview {
    return processingStatus == 'needs_review';
  }

  Memory copyWith({bool? isFavorite, bool? isArchived}) {
    return Memory(
      id: id,
      title: title,
      memoryType: memoryType,
      typeLabel: typeLabel,
      sourceType: sourceType,
      sourceLabel: sourceLabel,
      sourceUrl: sourceUrl,
      body: body,
      summary: summary,
      excerpt: excerpt,
      extractedText: extractedText,
      detectedLanguage: detectedLanguage,
      eventDate: eventDate,
      capturedAt: capturedAt,
      displayDate: displayDate,
      processingStatus: processingStatus,
      statusLabel: statusLabel,
      processingError: processingError,
      aiMetadata: aiMetadata,
      amount: amount,
      isFavorite: isFavorite ?? this.isFavorite,
      isArchived: isArchived ?? this.isArchived,
      tags: tags,
      collections: collections,
      attachments: attachments,
      entities: entities,
      reminders: reminders,
      cover: cover,
      createdAt: createdAt,
      updatedAt: updatedAt,
      relationshipId: relationshipId,
      relationshipType: relationshipType,
    );
  }
}

class Reminder {
  const Reminder({
    required this.id,
    required this.title,
    required this.notes,
    required this.type,
    required this.typeLabel,
    required this.dueAt,
    required this.status,
    required this.notificationChannel,
    required this.completedAt,
    required this.isOverdue,
    required this.daysAway,
    required this.memory,
    required this.createdAt,
  });

  factory Reminder.fromJson(Map<String, dynamic> json) {
    final memoryJson = json['memory'];
    return Reminder(
      id: asInt(json['id']),
      title: asText(json['title']),
      notes: asNullableText(json['notes']),
      type: asText(json['type'], 'custom'),
      typeLabel: asText(json['type_label']),
      dueAt: asDate(json['due_at']) ?? DateTime.now(),
      status: asText(json['status'], 'upcoming'),
      notificationChannel: asText(json['notification_channel'], 'in_app'),
      completedAt: asDate(json['completed_at']),
      isOverdue: asBool(json['is_overdue']),
      daysAway: json['days_away'] == null ? null : asInt(json['days_away']),
      memory: memoryJson is Map ? Memory.fromJson(asMap(memoryJson)) : null,
      createdAt: asDate(json['created_at']),
    );
  }

  final int id;
  final String title;
  final String? notes;
  final String type;
  final String typeLabel;
  final DateTime dueAt;
  final String status;
  final String notificationChannel;
  final DateTime? completedAt;
  final bool isOverdue;
  final int? daysAway;
  final Memory? memory;
  final DateTime? createdAt;

  bool get isCompleted => status == 'completed';
}

class TypeCount {
  const TypeCount({required this.type, required this.label, required this.total});

  factory TypeCount.fromJson(Map<String, dynamic> json) {
    return TypeCount(
      type: asText(json['type']),
      label: asText(json['label']),
      total: asInt(json['total']),
    );
  }

  final String type;
  final String label;
  final int total;
}

class MemoryOption {
  const MemoryOption({
    required this.id,
    required this.title,
    required this.memoryType,
    required this.typeLabel,
  });

  factory MemoryOption.fromJson(Map<String, dynamic> json) {
    return MemoryOption(
      id: asInt(json['id']),
      title: asText(json['title']),
      memoryType: asText(json['memory_type']),
      typeLabel: asText(json['type_label']),
    );
  }

  final int id;
  final String title;
  final String memoryType;
  final String typeLabel;
}
