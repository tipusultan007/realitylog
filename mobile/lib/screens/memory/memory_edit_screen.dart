import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api_exception.dart';
import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../l10n/strings.dart';
import '../../models/memory.dart';

const _editTypes = [
  'purchase',
  'receipt',
  'document',
  'person',
  'place',
  'event',
  'repair',
  'item',
  'task',
  'idea',
  'travel',
  'note',
  'meeting',
  'warranty',
  'medical',
  'link',
];

class MemoryEditScreen extends StatefulWidget {
  const MemoryEditScreen({super.key, required this.memory});

  final Memory memory;

  @override
  State<MemoryEditScreen> createState() => _MemoryEditScreenState();
}

class _MemoryEditScreenState extends State<MemoryEditScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _titleController;
  late final TextEditingController _bodyController;
  late final TextEditingController _extractedController;
  late final TextEditingController _summaryController;
  late final TextEditingController _tagsController;

  late String _type;
  late String _language;
  late DateTime? _eventDate;
  late Set<int> _collectionIds;
  List<Collection> _collections = const [];
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final memory = widget.memory;
    _titleController = TextEditingController(text: memory.title);
    _bodyController = TextEditingController(text: memory.body);
    _extractedController = TextEditingController(text: memory.extractedText ?? '');
    _summaryController = TextEditingController(text: memory.summary ?? '');
    _tagsController = TextEditingController(
      text: memory.tags.map((tag) => tag.name).join(', '),
    );
    _type = memory.memoryType;
    _language = memory.detectedLanguage;
    _eventDate = memory.eventDate;
    _collectionIds = memory.collections.map((collection) => collection.id).toSet();
    _loadCollections();
  }

  Future<void> _loadCollections() async {
    try {
      final collections = await context.read<AppState>().api.collections();
      if (mounted) {
        setState(() => _collections = collections);
      }
    } catch (_) {
      // Editing still works when collections cannot be loaded.
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _bodyController.dispose();
    _extractedController.dispose();
    _summaryController.dispose();
    _tagsController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final app = context.read<AppState>();
    final selected = await showDatePicker(
      context: context,
      initialDate: _eventDate ?? DateTime.now(),
      firstDate: DateTime(1900),
      lastDate: DateTime(2100),
      locale: Locale(app.locale),
    );
    if (selected != null && mounted) {
      setState(() => _eventDate = selected);
    }
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();

    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final app = context.read<AppState>();
      String? nullIfEmpty(TextEditingController controller) {
        final value = controller.text.trim();
        return value.isEmpty ? null : value;
      }

      final updated = await app.api.updateMemory(
        widget.memory.id,
        {
          'title': _titleController.text.trim(),
          'memory_type': _type,
          'body': nullIfEmpty(_bodyController),
          'extracted_text': nullIfEmpty(_extractedController),
          'summary': nullIfEmpty(_summaryController),
          'event_date': _eventDate == null
              ? null
              : '${_eventDate!.year.toString().padLeft(4, '0')}-'
                  '${_eventDate!.month.toString().padLeft(2, '0')}-'
                  '${_eventDate!.day.toString().padLeft(2, '0')}',
          'detected_language': _language,
          'tags': nullIfEmpty(_tagsController),
          'collection_ids': _collectionIds.toList(growable: false),
        },
      );

      app.bumpData();

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.s.tr('editSaved'))),
        );
        Navigator.of(context).pop(updated);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.detail);
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.tr('memoryEditTitle')),
        actions: [
          TextButton(
            onPressed: _saving ? null : _save,
            child: Text(s.tr('save')),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 36),
          children: [
            TextFormField(
              controller: _titleController,
              textCapitalization: TextCapitalization.sentences,
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                labelText: s.tr('captureTitleLabel'),
                prefixIcon: const Icon(Icons.title_rounded),
              ),
              validator: (value) {
                if ((value ?? '').trim().isEmpty) {
                  return s.tr('editTitleRequired');
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              key: ValueKey('edit-type-$_type'),
              initialValue: _type,
              decoration: InputDecoration(
                labelText: s.tr('editType'),
                prefixIcon: const Icon(Icons.sell_outlined),
              ),
              items: _editTypes
                  .map(
                    (type) => DropdownMenuItem(
                      value: type,
                      child: Text(s.tr('type.$type')),
                    ),
                  )
                  .toList(growable: false),
              onChanged: (value) {
                if (value != null) {
                  setState(() => _type = value);
                }
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _bodyController,
              minLines: 4,
              maxLines: 12,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                labelText: s.tr('editBody'),
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _extractedController,
              minLines: 5,
              maxLines: 16,
              decoration: InputDecoration(
                labelText: s.tr('editExtracted'),
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _summaryController,
              minLines: 2,
              maxLines: 5,
              decoration: InputDecoration(
                labelText: s.tr('editSummary'),
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              s.tr('editEventDate'),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pickDate,
                    icon: const Icon(Icons.event_outlined, size: 18),
                    label: Text(
                      _eventDate == null
                          ? s.tr('editChooseDate')
                          : formatDate(
                              _eventDate,
                              app.settings?.dateFormat ?? 'd M Y',
                              app.locale,
                            ),
                    ),
                  ),
                ),
                if (_eventDate != null) ...[
                  const SizedBox(width: 8),
                  IconButton(
                    tooltip: s.tr('clear'),
                    onPressed: () => setState(() => _eventDate = null),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 18),
            Text(
              s.tr('editLanguage'),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 8),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'en', label: Text('English')),
                ButtonSegment(value: 'bn', label: Text('বাংলা')),
              ],
              selected: {_language},
              onSelectionChanged: (values) {
                setState(() => _language = values.first);
              },
            ),
            const SizedBox(height: 18),
            TextFormField(
              controller: _tagsController,
              decoration: InputDecoration(
                labelText: s.tr('editTags'),
                hintText: s.tr('captureTagsHint'),
                prefixIcon: const Icon(Icons.tag_rounded),
              ),
            ),
            const SizedBox(height: 18),
            Text(
              s.tr('editCollections'),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 4),
            Text(
              s.tr('editCollectionsHint'),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            const SizedBox(height: 8),
            if (_collections.isEmpty)
              Text(
                s.tr('collectionsEmpty'),
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
              )
            else
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _collections
                    .map(
                      (collection) => FilterChip(
                        label: Text(collection.name),
                        selected: _collectionIds.contains(collection.id),
                        onSelected: (selected) {
                          setState(() {
                            if (selected) {
                              _collectionIds.add(collection.id);
                            } else {
                              _collectionIds.remove(collection.id);
                            }
                          });
                        },
                      ),
                    )
                    .toList(growable: false),
              ),
            if (_error != null) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: scheme.errorContainer,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Text(
                  _error!,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: scheme.onErrorContainer,
                      ),
                ),
              ),
            ],
            const SizedBox(height: 24),
            FilledButton.icon(
              onPressed: _saving ? null : _save,
              icon: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2.2),
                    )
                  : const Icon(Icons.check_rounded),
              label: Text(_saving ? s.tr('loading') : s.tr('editSave')),
            ),
          ],
        ),
      ),
    );
  }
}
