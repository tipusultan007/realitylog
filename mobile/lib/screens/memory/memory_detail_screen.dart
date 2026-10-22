import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api_exception.dart';
import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../l10n/strings.dart';
import '../../models/memory.dart';
import '../../models/responses.dart';
import '../../widgets/authenticated_image.dart';
import '../../widgets/common.dart';
import '../../widgets/memory_card.dart';
import '../reminders/reminder_form_sheet.dart';
import 'attachment_viewer_screen.dart';
import 'memory_edit_screen.dart';

class MemoryDetailScreen extends StatefulWidget {
  const MemoryDetailScreen({super.key, required this.memoryId});

  final int memoryId;

  @override
  State<MemoryDetailScreen> createState() => _MemoryDetailScreenState();
}

class _MemoryDetailScreenState extends State<MemoryDetailScreen> {
  MemoryDetail? _detail;
  bool _loading = true;
  String? _error;
  int? _downloadingAttachmentId;
  bool _reprocessing = false;
  bool _addingAttachments = false;
  int? _removingAttachmentId;

  Memory? get _memory => _detail?.memory;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool showLoading = true}) async {
    if (showLoading) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final detail = await context.read<AppState>().api.memoryDetail(widget.memoryId);
      if (mounted) {
        setState(() {
          _detail = detail;
          _loading = false;
          _error = null;
        });
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

  Future<void> _toggleFavorite() async {
    final memory = _memory;
    if (memory == null) {
      return;
    }
    final app = context.read<AppState>();
    try {
      final favorite = await app.api.toggleFavorite(memory.id);
      if (mounted) {
        setState(() => _detail = MemoryDetail(
              memory: memory.copyWith(isFavorite: favorite),
              related: _detail?.related ?? const [],
              suggested: _detail?.suggested ?? const [],
              linkable: _detail?.linkable ?? const [],
            ));
        app.bumpData();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              context.s.tr(favorite ? 'memoryFavoriteAdded' : 'memoryFavoriteRemoved'),
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    }
  }

  Future<void> _toggleArchive() async {
    final memory = _memory;
    if (memory == null) {
      return;
    }
    final app = context.read<AppState>();
    try {
      if (memory.isArchived) {
        await app.api.restoreMemory(memory.id);
      } else {
        await app.api.archiveMemory(memory.id);
      }
      if (mounted) {
        setState(() => _detail = MemoryDetail(
              memory: memory.copyWith(isArchived: !memory.isArchived),
              related: _detail?.related ?? const [],
              suggested: _detail?.suggested ?? const [],
              linkable: _detail?.linkable ?? const [],
            ));
        app.bumpData();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              context.s.tr(memory.isArchived ? 'memoryRestored' : 'memoryArchived'),
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.s.tr('memoryDeleteConfirm')),
        content: Text(context.s.tr('memoryDeleteBody')),
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

    try {
      final app = context.read<AppState>();
      await app.api.deleteMemory(widget.memoryId);
      app.bumpData();
      if (mounted) {
        Navigator.of(context).pop(true);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.s.tr('memoryDeleted'))),
        );
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    }
  }

  Future<void> _edit() async {
    final memory = _memory;
    if (memory == null) {
      return;
    }
    final updated = await Navigator.of(context).push<Memory>(
      MaterialPageRoute(builder: (_) => MemoryEditScreen(memory: memory)),
    );
    if (updated != null && mounted) {
      await _load(showLoading: false);
    }
  }

  Future<void> _addReminder() async {
    final memory = _memory;
    if (memory == null) {
      return;
    }
    final reminder = await showModalBottomSheet<Reminder>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => ReminderFormSheet(initialMemory: memory),
    );
    if (reminder != null && mounted) {
      context.read<AppState>().bumpData();
      await _load(showLoading: false);
    }
  }

  Future<void> _linkMemory() async {
    final detail = _detail;
    final memory = _memory;
    if (detail == null || memory == null || detail.linkable.isEmpty) {
      return;
    }

    final selection = await showModalBottomSheet<({int memoryId, String relationshipType})>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => _LinkMemorySheet(options: detail.linkable),
    );

    if (selection == null || !mounted) {
      return;
    }

    final app = context.read<AppState>();
    try {
      await app.api.linkMemory(
        id: memory.id,
        toMemoryId: selection.memoryId,
        relationshipType: selection.relationshipType,
      );
      app.bumpData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.s.tr('memoryLinked'))),
        );
        await _load(showLoading: false);
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    }
  }

  Future<void> _linkSuggestion(MemorySuggestion suggestion) async {
    final memory = _memory;
    if (memory == null) {
      return;
    }

    final app = context.read<AppState>();
    try {
      await app.api.linkMemory(
        id: memory.id,
        toMemoryId: suggestion.memory.id,
      );
      app.bumpData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.s.tr('memoryLinked'))),
        );
        await _load(showLoading: false);
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    }
  }

  String _suggestionReason(MemorySuggestion suggestion, S s) {
    final label = switch (suggestion.reason) {
      'similarity' => s.tr('memorySuggestionSimilarity'),
      'entity' => s.tr('memorySuggestionEntity'),
      'tag' => s.tr('memorySuggestionTag'),
      _ => s.tr('memorySuggestionType'),
    };
    final shared = [...suggestion.sharedEntities, ...suggestion.sharedTags];

    if (suggestion.reason == 'similarity' && suggestion.similarity > 0) {
      return '$label · ${(suggestion.similarity * 100).round()}%';
    }

    return shared.isEmpty ? label : '$label · ${shared.take(2).join(', ')}';
  }

  Future<void> _unlinkMemory(Memory related) async {
    final memory = _memory;
    final relationshipId = related.relationshipId;
    if (memory == null || relationshipId == null) {
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.s.tr('memoryUnlinkTitle')),
        content: Text(context.s.tr('memoryUnlinkBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.s.tr('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(context.s.tr('memoryUnlink')),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) {
      return;
    }

    final app = context.read<AppState>();
    try {
      await app.api.unlinkMemory(id: memory.id, relationshipId: relationshipId);
      app.bumpData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.s.tr('memoryUnlinked'))),
        );
        await _load(showLoading: false);
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    }
  }

  Future<void> _reprocess() async {
    final memory = _memory;
    if (memory == null || _reprocessing) {
      return;
    }

    setState(() => _reprocessing = true);
    final app = context.read<AppState>();

    try {
      await app.api.reprocessMemory(memory.id);
      app.bumpData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.s.tr('memoryReprocessed'))),
        );
        await _load(showLoading: false);
      }
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    } finally {
      if (mounted) {
        setState(() => _reprocessing = false);
      }
    }
  }

  Future<void> _openAttachment(MemoryAttachment attachment) async {
    final app = context.read<AppState>();
    final url = app.api.resolveMediaUrl(attachment.url);

    if (attachment.isImage) {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => AttachmentViewerScreen(
            attachment: attachment,
            url: url,
            headers: app.api.authHeaders,
          ),
        ),
      );
      return;
    }

    setState(() => _downloadingAttachmentId = attachment.id);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(context.s.tr('memoryDownloading'))),
    );

    try {
      final bytes = await app.api.downloadBytes(
        attachment.downloadUrl.isNotEmpty ? attachment.downloadUrl : attachment.url,
      );
      final directory = await getTemporaryDirectory();
      final safeName = attachment.originalName.replaceAll(RegExp(r'[^\w.\-]'), '_');
      final file = File('${directory.path}/$safeName');
      await file.writeAsBytes(bytes, flush: true);
      await OpenFilex.open(file.path);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is ApiException ? error.detail : context.s.tr('memoryAttachmentError'),
            ),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _downloadingAttachmentId = null);
      }
    }
  }

  Future<void> _addAttachments() async {
    if (_addingAttachments) {
      return;
    }

    List<PlatformFile> picked;
    try {
      picked = await FilePicker.pickFiles();
    } catch (_) {
      if (mounted) {
        _showError(context.s.tr('memoryAttachmentPickFailed'));
      }
      return;
    }

    final files = picked
        .where((file) => (file.path ?? '').isNotEmpty)
        .map((file) => (path: file.path!, name: file.name))
        .toList(growable: false);

    if (files.isEmpty || !mounted) {
      return;
    }

    setState(() => _addingAttachments = true);
    final app = context.read<AppState>();

    try {
      final memory = await app.api.addAttachments(widget.memoryId, files);
      if (!mounted) {
        return;
      }
      app.bumpData();
      _replaceMemory(memory);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.s.tr('memoryAttachmentsAdded'))),
      );
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    } finally {
      if (mounted) {
        setState(() => _addingAttachments = false);
      }
    }
  }

  Future<void> _removeAttachment(MemoryAttachment attachment) async {
    if (_removingAttachmentId != null) {
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.s.tr('memoryAttachmentDelete')),
        content: Text(context.s.tr('memoryAttachmentDeleteConfirm')),
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

    setState(() => _removingAttachmentId = attachment.id);
    final app = context.read<AppState>();

    try {
      final memory = await app.api.deleteAttachment(widget.memoryId, attachment.id);
      if (!mounted) {
        return;
      }
      app.bumpData();
      _replaceMemory(memory);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.s.tr('memoryAttachmentRemoved'))),
      );
    } catch (error) {
      if (mounted) {
        _showError(error);
      }
    } finally {
      if (mounted) {
        setState(() => _removingAttachmentId = null);
      }
    }
  }

  void _replaceMemory(Memory memory) {
    setState(() {
      _detail = MemoryDetail(
        memory: memory,
        related: _detail?.related ?? const [],
        suggested: _detail?.suggested ?? const [],
        linkable: _detail?.linkable ?? const [],
      );
    });
  }

  Future<void> _openSource() async {
    final url = _memory?.sourceUrl;
    if (url == null || url.isEmpty) {
      return;
    }
    final uri = Uri.tryParse(url);
    if (uri == null) {
      return;
    }
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      if (mounted) {
        _showError(context.s.tr('errorUnknown'));
      }
    }
  }

  void _showError(Object error) {
    final message = error is String ? error : describeError(error, context.s);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final memory = _memory;
    final detail = _detail;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.tr('memoryTitle')),
        actions: [
          if (memory != null) ...[
            IconButton(
              tooltip: memory.isFavorite ? s.tr('memoryUnfavorite') : s.tr('memoryFavorite'),
              onPressed: _toggleFavorite,
              icon: Icon(
                memory.isFavorite ? Icons.star_rounded : Icons.star_outline_rounded,
                color: memory.isFavorite ? scheme.tertiary : null,
              ),
            ),
            PopupMenuButton<String>(
              onSelected: (value) {
                switch (value) {
                  case 'edit':
                    _edit();
                  case 'archive':
                    _toggleArchive();
                  case 'delete':
                    _delete();
                }
              },
              itemBuilder: (context) => [
                PopupMenuItem(value: 'edit', child: Text(s.tr('edit'))),
                PopupMenuItem(
                  value: 'archive',
                  child: Text(memory.isArchived ? s.tr('memoryRestore') : s.tr('memoryArchive')),
                ),
                PopupMenuItem(value: 'delete', child: Text(s.tr('memoryDelete'))),
              ],
            ),
          ],
        ],
      ),
      body: _loading && memory == null
          ? const LoadingView()
          : _error != null && memory == null
              ? ErrorState(message: _error!, onRetry: _load)
              : memory == null
                  ? EmptyState(
                      icon: Icons.search_off_rounded,
                      title: s.tr('memoryNotFound'),
                    )
                  : RefreshIndicator(
                      onRefresh: () => _load(showLoading: false),
                      child: ListView(
                        padding: const EdgeInsets.only(bottom: 40),
                        children: [
                          if (memory.cover != null && memory.cover!.isImage)
                            AuthenticatedImage(
                              url: app.api.resolveMediaUrl(memory.cover!.url),
                              headers: app.api.authHeaders,
                              height: 240,
                              borderRadius: const BorderRadius.vertical(
                                bottom: Radius.circular(28),
                              ),
                            ),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Wrap(
                                  spacing: 6,
                                  runSpacing: 6,
                                  children: [
                                    InfoPill(
                                      label: s.tr('type.${memory.memoryType}'),
                                      icon: Icons.sell_outlined,
                                    ),
                                    if (memory.isArchived)
                                      InfoPill(
                                        label: s.tr('memoryArchive'),
                                        icon: Icons.inventory_2_outlined,
                                        tone: scheme.tertiary,
                                      ),
                                    if (memory.isProcessing)
                                      InfoPill(
                                        label: s.tr('memoryProcessing'),
                                        icon: Icons.autorenew_rounded,
                                        tone: scheme.tertiary,
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  memory.title,
                                  style: Theme.of(context).textTheme.headlineMedium,
                                ),
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    Icon(Icons.event_outlined, size: 15, color: scheme.onSurfaceVariant),
                                    const SizedBox(width: 5),
                                    Text(
                                      formatDate(
                                        memory.eventDate ?? memory.capturedAt,
                                        app.settings?.dateFormat ?? 'd M Y',
                                        app.locale,
                                      ),
                                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                            color: scheme.onSurfaceVariant,
                                          ),
                                    ),
                                    const SizedBox(width: 12),
                                    Icon(Icons.translate_rounded, size: 15, color: scheme.onSurfaceVariant),
                                    const SizedBox(width: 5),
                                    Text(
                                      memory.detectedLanguage == 'bn' ? 'বাংলা' : 'English',
                                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                            color: scheme.onSurfaceVariant,
                                          ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 16),
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton.icon(
                                        onPressed: _toggleFavorite,
                                        icon: Icon(
                                          memory.isFavorite
                                              ? Icons.star_rounded
                                              : Icons.star_outline_rounded,
                                          size: 18,
                                        ),
                                        label: Text(
                                          memory.isFavorite
                                              ? s.tr('memoryUnfavorite')
                                              : s.tr('memoryFavorite'),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: OutlinedButton.icon(
                                        onPressed: _addReminder,
                                        icon: const Icon(Icons.notification_add_outlined, size: 18),
                                        label: Text(s.tr('memoryAddReminder')),
                                      ),
                                    ),
                                  ],
                                ),
                                if (memory.isProcessing) ...[
                                  const SizedBox(height: 12),
                                  _Notice(
                                    icon: Icons.autorenew_rounded,
                                    title: s.tr('captureProcessing'),
                                    color: scheme.tertiary,
                                  ),
                                ],
                                if (memory.needsReview) ...[
                                  const SizedBox(height: 12),
                                  _Notice(
                                    icon: Icons.rate_review_outlined,
                                    title: s.tr('memoryReview'),
                                    body: s.tr('memoryReviewBody'),
                                    color: scheme.tertiary,
                                  ),
                                ],
                                if (memory.needsReview ||
                                    memory.processingStatus == 'failed') ...[
                                  const SizedBox(height: 10),
                                  OutlinedButton.icon(
                                    onPressed: _reprocessing ? null : _reprocess,
                                    icon: _reprocessing
                                        ? const SizedBox(
                                            width: 16,
                                            height: 16,
                                            child: CircularProgressIndicator(strokeWidth: 2),
                                          )
                                        : const Icon(Icons.refresh_rounded, size: 18),
                                    label: Text(
                                      _reprocessing
                                          ? s.tr('memoryReprocessing')
                                          : s.tr('memoryReprocess'),
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    s.tr('memoryReprocessHint'),
                                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                          color: scheme.onSurfaceVariant,
                                        ),
                                  ),
                                ],
                                if (memory.summary != null) ...[
                                  const SizedBox(height: 22),
                                  SectionHeader(title: s.tr('memorySummary')),
                                  Text(
                                    memory.summary!,
                                    style: Theme.of(context).textTheme.bodyLarge,
                                  ),
                                ],
                                if (memory.body.trim().isNotEmpty) ...[
                                  const SizedBox(height: 22),
                                  SectionHeader(title: s.tr('memoryDetails')),
                                  SelectableText(
                                    memory.body,
                                    style: Theme.of(context).textTheme.bodyLarge,
                                  ),
                                ],
                                const SizedBox(height: 22),
                                Row(
                                  children: [
                                    Expanded(
                                      child: SectionHeader(title: s.tr('memoryExtracted')),
                                    ),
                                    TextButton.icon(
                                      onPressed: _edit,
                                      icon: const Icon(Icons.edit_outlined, size: 16),
                                      label: Text(s.tr('edit')),
                                    ),
                                  ],
                                ),
                                if (memory.extractedText != null)
                                  SelectableText(
                                    memory.extractedText!,
                                    style: Theme.of(context).textTheme.bodyMedium,
                                  )
                                else
                                  Text(
                                    s.tr('memoryNoExtracted'),
                                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                          color: scheme.onSurfaceVariant,
                                        ),
                                  ),
                                if (memory.aiMetadata.isNotEmpty) ...[
                                  const SizedBox(height: 18),
                                  _MetadataWrap(metadata: memory.aiMetadata),
                                ],
                                if (memory.tags.isNotEmpty) ...[
                                  const SizedBox(height: 22),
                                  SectionHeader(title: s.tr('memoryTags')),
                                  Wrap(
                                    spacing: 8,
                                    runSpacing: 8,
                                    children: memory.tags
                                        .map(
                                          (tag) => Chip(
                                            avatar: const Icon(Icons.tag_rounded, size: 15),
                                            label: Text(tag.name),
                                          ),
                                        )
                                        .toList(growable: false),
                                  ),
                                ],
                                if (memory.entities.isNotEmpty) ...[
                                  const SizedBox(height: 22),
                                  SectionHeader(title: s.tr('memoryEntities')),
                                  ...memory.entities.map(
                                    (entity) => ListTile(
                                      contentPadding: EdgeInsets.zero,
                                      leading: Icon(
                                        entity.type == 'person'
                                            ? Icons.person_outline
                                            : Icons.place_outlined,
                                      ),
                                      title: Text(entity.name),
                                      subtitle: Text(entity.label),
                                    ),
                                  ),
                                ],
                                if (memory.collections.isNotEmpty) ...[
                                  const SizedBox(height: 14),
                                  SectionHeader(title: s.tr('memoryCollections')),
                                  Wrap(
                                    spacing: 8,
                                    runSpacing: 8,
                                    children: memory.collections
                                        .map(
                                          (collection) => Chip(
                                            avatar: const Icon(Icons.folder_outlined, size: 15),
                                            label: Text(collection.name),
                                          ),
                                        )
                                        .toList(growable: false),
                                  ),
                                ],
                                const SizedBox(height: 22),
                                SectionHeader(title: s.tr('memoryAttachments')),
                                if (memory.attachments.isEmpty)
                                  Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: Text(
                                      s.tr('memoryNoAttachments'),
                                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                            color: scheme.onSurfaceVariant,
                                          ),
                                    ),
                                  ),
                                ...memory.attachments.map(
                                  (attachment) => _AttachmentTile(
                                    attachment: attachment,
                                    downloading:
                                        _downloadingAttachmentId == attachment.id,
                                    removing: _removingAttachmentId == attachment.id,
                                    onTap: () => _openAttachment(attachment),
                                    onDelete: () => _removeAttachment(attachment),
                                  ),
                                ),
                                const SizedBox(height: 6),
                                OutlinedButton.icon(
                                  onPressed: _addingAttachments ? null : _addAttachments,
                                  icon: _addingAttachments
                                      ? const SizedBox(
                                          width: 16,
                                          height: 16,
                                          child: CircularProgressIndicator(strokeWidth: 2),
                                        )
                                      : const Icon(Icons.attach_file_rounded, size: 18),
                                  label: Text(s.tr('memoryAddAttachment')),
                                ),
                                if (memory.reminders.isNotEmpty) ...[
                                  const SizedBox(height: 22),
                                  SectionHeader(title: s.tr('memoryReminders')),
                                  ...memory.reminders.map(
                                    (reminder) => ListTile(
                                      contentPadding: EdgeInsets.zero,
                                      leading: Icon(
                                        reminder.isCompleted
                                            ? Icons.check_circle_outline
                                            : Icons.notifications_none_rounded,
                                      ),
                                      title: Text(reminder.title),
                                      subtitle: Text(
                                        formatDateTime(reminder.dueAt, app.locale),
                                      ),
                                    ),
                                  ),
                                ],
                                if (memory.sourceUrl != null) ...[
                                  const SizedBox(height: 10),
                                  TextButton.icon(
                                    onPressed: _openSource,
                                    icon: const Icon(Icons.open_in_new_rounded, size: 16),
                                    label: Text(
                                      '${s.tr('memorySourceUrl')}: ${memory.sourceUrl}',
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 18),
                                Text(
                                  '${s.tr('memoryCaptured')}: '
                                  '${formatDateTime(memory.capturedAt, app.locale)}',
                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                        color: scheme.onSurfaceVariant,
                                      ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 24),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(20, 0, 20, 0),
                            child: Row(
                              children: [
                                Expanded(
                                  child: SectionHeader(title: s.tr('memoryRelated')),
                                ),
                                if (detail != null && detail.linkable.isNotEmpty)
                                  TextButton.icon(
                                    onPressed: _linkMemory,
                                    icon: const Icon(Icons.add_link_rounded, size: 18),
                                    label: Text(s.tr('memoryLinkTitle')),
                                  ),
                              ],
                            ),
                          ),
                          if (detail == null || detail.related.isEmpty)
                            Padding(
                              padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
                              child: Text(
                                s.tr('memoryNoRelated'),
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: scheme.onSurfaceVariant,
                                    ),
                              ),
                            )
                          else
                            ...detail.related.map(
                              (related) => Padding(
                                padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: [
                                    if (related.relationshipType != null)
                                      Align(
                                        alignment: AlignmentDirectional.centerStart,
                                        child: Padding(
                                          padding: const EdgeInsets.only(bottom: 4, left: 4),
                                          child: Text(
                                            s.tr('relationship.${related.relationshipType}'),
                                            style: Theme.of(context)
                                                .textTheme
                                                .labelSmall
                                                ?.copyWith(
                                                  color: scheme.onSurfaceVariant,
                                                  fontWeight: FontWeight.w700,
                                                ),
                                          ),
                                        ),
                                      ),
                                    MemoryCard(
                                      memory: related,
                                      onTap: () {
                                        Navigator.of(context).pushReplacement(
                                          MaterialPageRoute(
                                            builder: (_) => MemoryDetailScreen(memoryId: related.id),
                                          ),
                                        );
                                      },
                                    ),
                                    if (related.relationshipId != null)
                                      Align(
                                        alignment: AlignmentDirectional.centerEnd,
                                        child: TextButton.icon(
                                          onPressed: () => _unlinkMemory(related),
                                          icon: const Icon(Icons.link_off_rounded, size: 16),
                                          label: Text(s.tr('memoryUnlink')),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          if (detail != null && detail.suggested.isNotEmpty) ...[
                            const SizedBox(height: 22),
                            Padding(
                              padding: const EdgeInsets.fromLTRB(20, 0, 20, 0),
                              child: SectionHeader(title: s.tr('memorySuggested')),
                            ),
                            Padding(
                              padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
                              child: Text(
                                s.tr('memorySuggestedBody'),
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: scheme.onSurfaceVariant,
                                    ),
                              ),
                            ),
                            ...detail.suggested.map(
                              (suggestion) => Padding(
                                padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.stretch,
                                  children: [
                                    MemoryCard(
                                      memory: suggestion.memory,
                                      onTap: () {
                                        Navigator.of(context).pushReplacement(
                                          MaterialPageRoute(
                                            builder: (_) => MemoryDetailScreen(
                                              memoryId: suggestion.memory.id,
                                            ),
                                          ),
                                        );
                                      },
                                    ),
                                    Padding(
                                      padding: const EdgeInsets.only(top: 4),
                                      child: Row(
                                        children: [
                                          Expanded(
                                            child: Text(
                                              _suggestionReason(suggestion, s),
                                              style: Theme.of(context)
                                                  .textTheme
                                                  .labelSmall
                                                  ?.copyWith(
                                                    color: scheme.onSurfaceVariant,
                                                    fontWeight: FontWeight.w600,
                                                  ),
                                            ),
                                          ),
                                          TextButton.icon(
                                            onPressed: () => _linkSuggestion(suggestion),
                                            icon: const Icon(Icons.link_rounded, size: 16),
                                            label: Text(s.tr('memoryLinkSuggestion')),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({
    required this.icon,
    required this.title,
    required this.color,
    this.body,
  });

  final IconData icon;
  final String title;
  final String? body;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.28)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
                if (body != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    body!,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LinkMemorySheet extends StatefulWidget {
  const _LinkMemorySheet({required this.options});

  final List<MemoryOption> options;

  @override
  State<_LinkMemorySheet> createState() => _LinkMemorySheetState();
}

class _LinkMemorySheetState extends State<_LinkMemorySheet> {
  static const _relationshipTypes = ['related', 'receipt', 'repair', 'warranty', 'follow_up'];

  final _searchController = TextEditingController();
  int? _selectedId;
  String _relationshipType = 'related';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final query = _searchController.text.trim().toLowerCase();
    final options = query.isEmpty
        ? widget.options
        : widget.options
            .where((option) => option.title.toLowerCase().contains(query))
            .toList(growable: false);

    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        4,
        20,
        MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.72,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              s.tr('memoryLinkTitle'),
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 4),
            Text(
              s.tr('memoryLinkSubtitle'),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              key: ValueKey('link-type-$_relationshipType'),
              initialValue: _relationshipType,
              decoration: InputDecoration(
                labelText: s.tr('memoryLinkRelationship'),
                prefixIcon: const Icon(Icons.link_rounded),
              ),
              items: _relationshipTypes
                  .map(
                    (type) => DropdownMenuItem(
                      value: type,
                      child: Text(s.tr('relationship.$type')),
                    ),
                  )
                  .toList(growable: false),
              onChanged: (value) {
                if (value != null) {
                  setState(() => _relationshipType = value);
                }
              },
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _searchController,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: s.tr('memoryLinkSearch'),
                prefixIcon: const Icon(Icons.search_rounded),
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: options.isEmpty
                  ? Center(
                      child: Text(
                        s.tr('memoryLinkEmpty'),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                      ),
                    )
                  : ListView.builder(
                      itemCount: options.length,
                      itemBuilder: (context, index) {
                        final option = options[index];
                        final selected = option.id == _selectedId;
                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          onTap: () => setState(() => _selectedId = option.id),
                          leading: Icon(
                            selected
                                ? Icons.radio_button_checked_rounded
                                : Icons.radio_button_unchecked_rounded,
                            color: selected ? scheme.primary : scheme.onSurfaceVariant,
                          ),
                          title: Text(
                            option.title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          subtitle: Text(s.tr('type.${option.memoryType}')),
                        );
                      },
                    ),
            ),
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: _selectedId == null
                  ? null
                  : () => Navigator.of(context).pop((
                        memoryId: _selectedId!,
                        relationshipType: _relationshipType,
                      )),
              icon: const Icon(Icons.link_rounded),
              label: Text(s.tr('memoryLinkAction')),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetadataWrap extends StatelessWidget {
  const _MetadataWrap({required this.metadata});

  final Map<String, dynamic> metadata;

  @override
  Widget build(BuildContext context) {
    final entries = metadata.entries
        .where((entry) => entry.value is String || entry.value is num)
        .take(8)
        .toList(growable: false);

    if (entries.isEmpty) {
      return const SizedBox.shrink();
    }

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: entries
          .map(
            (entry) => Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.surfaceContainerLow,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.5),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    entry.key.replaceAll('_', ' '),
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${entry.value}',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                ],
              ),
            ),
          )
          .toList(growable: false),
    );
  }
}

class _AttachmentTile extends StatelessWidget {
  const _AttachmentTile({
    required this.attachment,
    required this.downloading,
    required this.removing,
    required this.onTap,
    required this.onDelete,
  });

  final MemoryAttachment attachment;
  final bool downloading;
  final bool removing;
  final VoidCallback onTap;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final scheme = Theme.of(context).colorScheme;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        onTap: downloading ? null : onTap,
        leading: attachment.isImage
            ? AuthenticatedImage(
                url: app.api.resolveMediaUrl(attachment.url),
                headers: app.api.authHeaders,
                width: 52,
                height: 52,
                borderRadius: BorderRadius.circular(12),
              )
            : CircleAvatar(
                backgroundColor: scheme.secondaryContainer,
                child: Icon(
                  attachment.isPdf
                      ? Icons.picture_as_pdf_outlined
                      : Icons.insert_drive_file_outlined,
                  color: scheme.onSecondaryContainer,
                ),
              ),
        title: Text(
          attachment.originalName,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Text(
          attachment.isDuplicate
              ? '${attachment.humanSize} • ${attachment.mimeType} • ${context.s.tr('memoryDuplicateAttachment')}'
              : '${attachment.humanSize} • ${attachment.mimeType}',
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        trailing: downloading || removing
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : PopupMenuButton<String>(
                tooltip: context.s.tr('memoryAttachmentDelete'),
                onSelected: (value) {
                  if (value == 'delete') {
                    onDelete();
                  }
                },
                itemBuilder: (context) => [
                  PopupMenuItem(
                    value: 'delete',
                    child: Text(context.s.tr('memoryAttachmentDelete')),
                  ),
                ],
              ),
      ),
    );
  }
}
