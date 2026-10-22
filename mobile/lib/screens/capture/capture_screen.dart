import 'dart:async';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/api_exception.dart';
import '../../core/app_state.dart';
import '../../core/draft_store.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../core/native_bridge.dart';
import '../../l10n/strings.dart';
import '../../models/memory.dart';
import '../memory/memory_detail_screen.dart';

class CaptureScreen extends StatefulWidget {
  const CaptureScreen({super.key, this.sharedContent});

  final SharedContent? sharedContent;

  @override
  State<CaptureScreen> createState() => _CaptureScreenState();
}

class _CaptureScreenState extends State<CaptureScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _bodyController = TextEditingController();
  final _urlController = TextEditingController();
  final _tagsController = TextEditingController();
  final _imagePicker = ImagePicker();
  final _draftStore = DraftStore();
  final _native = const NativeBridge();

  Timer? _draftTimer;
  Timer? _recordingTimer;
  bool _recording = false;
  int _recordingSeconds = 0;

  String _sourceType = 'text';
  String _language = 'en';
  DateTime? _eventDate;
  int? _collectionId;
  String? _filePath;
  String? _fileName;
  bool _saving = false;
  String? _error;
  List<Collection> _collections = const [];

  @override
  void initState() {
    super.initState();
    final app = context.read<AppState>();
    _language = app.settings?.aiLocale ?? app.locale;
    _applySharedContent();
    _restoreDraft();
    _loadCollections();
    _titleController.addListener(_scheduleDraftSave);
    _bodyController.addListener(_scheduleDraftSave);
    _urlController.addListener(_scheduleDraftSave);
    _tagsController.addListener(_scheduleDraftSave);
  }

  @override
  void dispose() {
    _draftTimer?.cancel();
    _recordingTimer?.cancel();
    if (_recording) {
      unawaited(_native.cancelRecording());
    }
    _titleController.dispose();
    _bodyController.dispose();
    _urlController.dispose();
    _tagsController.dispose();
    super.dispose();
  }

  void _applySharedContent() {
    final shared = widget.sharedContent;
    if (shared == null) {
      return;
    }

    final filePath = shared.filePath;
    final name = (shared.fileName ?? '').toLowerCase();

    if (filePath != null && filePath.isNotEmpty) {
      if (name.endsWith('.pdf') ||
          name.endsWith('.doc') ||
          name.endsWith('.docx') ||
          name.endsWith('.txt') ||
          name.endsWith('.csv') ||
          name.endsWith('.md')) {
        _sourceType = 'document';
      } else if (name.endsWith('.mp3') ||
          name.endsWith('.m4a') ||
          name.endsWith('.wav') ||
          name.endsWith('.ogg') ||
          name.endsWith('.webm')) {
        _sourceType = 'voice';
      } else {
        _sourceType = 'image';
      }
      _filePath = filePath;
      _fileName = shared.fileName;
    } else if (shared.looksLikeLink) {
      _sourceType = 'link';
      _urlController.text = shared.text!.trim();
    } else {
      _sourceType = 'text';
      _bodyController.text = shared.text ?? '';
    }

    final subject = shared.subject?.trim() ?? '';
    if (subject.isNotEmpty) {
      _titleController.text = subject;
    } else if (!shared.looksLikeLink) {
      final suggestion = shared.suggestedTitle.trim();
      if (suggestion.isNotEmpty) {
        _titleController.text = suggestion;
      }
    }
  }

  void _scheduleDraftSave() {
    if (_saving) {
      return;
    }
    _draftTimer?.cancel();
    _draftTimer = Timer(const Duration(milliseconds: 900), _saveDraft);
  }

  Future<void> _saveDraft() async {
    _draftTimer?.cancel();

    final draft = CaptureDraft(
      sourceType: _sourceType,
      title: _titleController.text,
      body: _bodyController.text,
      sourceUrl: _urlController.text,
      tags: _tagsController.text,
      language: _language,
      eventDate: _dateValue(),
      collectionId: _collectionId,
      filePath: _filePath,
      fileName: _fileName,
      savedAt: DateTime.now(),
    );

    if (draft.isEmpty) {
      await _draftStore.clear();
      return;
    }

    final saved = await _draftStore.save(draft);
    if (mounted && saved.filePath != _filePath) {
      setState(() {
        _filePath = saved.filePath;
        _fileName = saved.fileName ?? _fileName;
      });
    }
  }

  Future<void> _restoreDraft() async {
    if (widget.sharedContent != null) {
      return;
    }

    final draft = await _draftStore.load();
    if (draft == null || !mounted) {
      return;
    }

    setState(() {
      _sourceType = draft.sourceType;
      _language = draft.language;
      _titleController.text = draft.title;
      _bodyController.text = draft.body;
      _urlController.text = draft.sourceUrl;
      _tagsController.text = draft.tags;
      _eventDate = draft.eventDate == null ? null : DateTime.tryParse(draft.eventDate!);
      _collectionId = draft.collectionId;
      _filePath = draft.filePath;
      _fileName = draft.fileName;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(context.s.tr('captureDraftRestored'))),
    );
  }

  String? _dateValue() {
    if (_eventDate == null) {
      return null;
    }
    return '${_eventDate!.year.toString().padLeft(4, '0')}-'
        '${_eventDate!.month.toString().padLeft(2, '0')}-'
        '${_eventDate!.day.toString().padLeft(2, '0')}';
  }

  Future<void> _toggleRecording() async {
    if (_recording) {
      await _stopRecording();
    } else {
      await _startRecording();
    }
  }

  Future<void> _startRecording() async {
    final s = context.s;
    final started = await _native.startRecording();
    if (!mounted) {
      return;
    }

    if (!started) {
      setState(() {
        _error = s.tr(_native.isSupported ? 'captureVoicePermission' : 'captureVoiceUnavailable');
      });
      return;
    }

    setState(() {
      _sourceType = 'voice';
      _filePath = null;
      _fileName = null;
      _recording = true;
      _recordingSeconds = 0;
      _error = null;
    });

    _recordingTimer?.cancel();
    _recordingTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        setState(() => _recordingSeconds++);
      }
    });
  }

  Future<void> _stopRecording() async {
    _recordingTimer?.cancel();
    final result = await _native.stopRecording();
    if (!mounted) {
      return;
    }

    setState(() {
      _recording = false;
      if (result != null) {
        _filePath = result.path;
        _fileName = result.name;
        _error = null;
      } else {
        _error = context.s.tr('captureVoiceUnavailable');
      }
    });

    if (result != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.s.tr('captureVoiceRecorded'))),
      );
      await _saveDraft();
    }
  }

  Future<void> _cancelRecording() async {
    _recordingTimer?.cancel();
    await _native.cancelRecording();
    if (mounted) {
      setState(() {
        _recording = false;
        _recordingSeconds = 0;
      });
    }
  }

  Future<void> _loadCollections() async {
    try {
      final collections = await context.read<AppState>().api.collections();
      if (mounted) {
        setState(() => _collections = collections);
      }
    } catch (_) {
      // Collections are optional; capture still works without them.
    }
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final file = await _imagePicker.pickImage(
        source: source,
        imageQuality: 86,
        maxWidth: 2200,
      );
      if (file == null || !mounted) {
        return;
      }
      setState(() {
        _filePath = file.path;
        _fileName = file.name;
        _sourceType = 'image';
        _error = null;
      });
      unawaited(_saveDraft());
    } catch (error) {
      if (mounted) {
        setState(() => _error = error.toString());
      }
    }
  }

  Future<void> _pickFile() async {
    final extensions = _sourceType == 'voice'
        ? ['mp3', 'm4a', 'wav', 'ogg', 'webm']
        : ['pdf', 'txt', 'doc', 'docx', 'md', 'csv'];

    try {
      final file = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: extensions,
      );
      if (file == null || file.path == null || !mounted) {
        return;
      }
      setState(() {
        _filePath = file.path;
        _fileName = file.name;
        _error = null;
      });
      unawaited(_saveDraft());
    } catch (error) {
      if (mounted) {
        setState(() => _error = error.toString());
      }
    }
  }

  Future<void> _pickDate() async {
    final app = context.read<AppState>();
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: _eventDate ?? now,
      firstDate: DateTime(1900),
      lastDate: DateTime(2100),
      locale: Locale(app.locale),
    );
    if (selected != null && mounted) {
      setState(() => _eventDate = selected);
      _scheduleDraftSave();
    }
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    final s = context.s;

    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    if ((_sourceType == 'text' || _sourceType == 'link') &&
        _titleController.text.trim().isEmpty &&
        _bodyController.text.trim().isEmpty) {
      setState(() => _error = s.tr('captureRequiredBody'));
      return;
    }

    if (_sourceType == 'link' && _urlController.text.trim().isEmpty) {
      setState(() => _error = s.tr('captureRequiredUrl'));
      return;
    }

    if ((_sourceType == 'image' || _sourceType == 'document' || _sourceType == 'voice') &&
        _filePath == null) {
      setState(() => _error = s.tr('captureRequiredFile'));
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final app = context.read<AppState>();
      final memory = await app.api.capture(
        sourceType: _sourceType,
        title: _titleController.text,
        body: _bodyController.text,
        sourceUrl: _sourceType == 'link' ? _urlController.text : null,
        eventDate: _dateValue(),
        tags: _tagsController.text,
        collectionId: _collectionId?.toString(),
        language: _language,
        filePath: _filePath,
        fileName: _fileName,
      );

      app.bumpData();
      await _draftStore.clear();

      if (!mounted) {
        return;
      }

      _reset();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(s.tr('captureSuccess'))),
      );
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => MemoryDetailScreen(memoryId: memory.id)),
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      if (error.isNetwork) {
        await _saveDraft();
        if (mounted) {
          setState(() => _error = s.tr('captureDraftQueued'));
        }
      } else {
        setState(() => _error = error.detail);
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = describeError(error, s));
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  void _discardDraft() {
    final hadContent = _titleController.text.trim().isNotEmpty ||
        _bodyController.text.trim().isNotEmpty ||
        _urlController.text.trim().isNotEmpty ||
        _tagsController.text.trim().isNotEmpty ||
        _filePath != null;

    _reset();

    if (hadContent && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.s.tr('captureDraftDiscarded'))),
      );
    }
  }

  void _reset() {
    _draftTimer?.cancel();
    unawaited(_draftStore.clear());
    if (_recording) {
      unawaited(_cancelRecording());
    }
    _titleController.clear();
    _bodyController.clear();
    _urlController.clear();
    _tagsController.clear();
    setState(() {
      _sourceType = 'text';
      _eventDate = null;
      _collectionId = null;
      _filePath = null;
      _fileName = null;
      _error = null;
    });
  }

  void _changeSource(String type) {
    if (_recording) {
      unawaited(_cancelRecording());
    }
    setState(() {
      _sourceType = type;
      _filePath = null;
      _fileName = null;
      _error = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.tr('captureTitle')),
        actions: [
          IconButton(
            tooltip: s.tr('captureDraftDiscard'),
            onPressed: _saving ? null : _discardDraft,
            icon: const Icon(Icons.restart_alt_rounded),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 36),
          children: [
            Text(
              s.tr('captureSubtitle'),
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            if (widget.sharedContent != null) ...[
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: scheme.secondaryContainer,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.share_outlined, size: 20, color: scheme.onSecondaryContainer),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            s.tr('captureSharedTitle'),
                            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: scheme.onSecondaryContainer,
                                ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            s.tr('captureSharedBody'),
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: scheme.onSecondaryContainer,
                                ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 18),
            Text(
              s.tr('captureType'),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _sourceChip('text', s.tr('captureText'), Icons.notes_rounded),
                _sourceChip('link', s.tr('captureLink'), Icons.link_rounded),
                _sourceChip('image', s.tr('captureImage'), Icons.photo_camera_outlined),
                _sourceChip('document', s.tr('captureDocument'), Icons.description_outlined),
                _sourceChip('voice', s.tr('captureVoice'), Icons.mic_none_rounded),
              ],
            ),
            const SizedBox(height: 18),
            TextFormField(
              controller: _titleController,
              textCapitalization: TextCapitalization.sentences,
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                labelText: s.tr('captureTitleLabel'),
                prefixIcon: const Icon(Icons.title_rounded),
              ),
            ),
            const SizedBox(height: 12),
            if (_sourceType == 'link') ...[
              TextFormField(
                controller: _urlController,
                keyboardType: TextInputType.url,
                textInputAction: TextInputAction.next,
                autocorrect: false,
                decoration: InputDecoration(
                  labelText: s.tr('captureLinkLabel'),
                  prefixIcon: const Icon(Icons.link_rounded),
                  hintText: 'https://',
                ),
                validator: (value) {
                  if (_sourceType != 'link') {
                    return null;
                  }
                  final text = (value ?? '').trim();
                  if (text.isEmpty) {
                    return s.tr('captureRequiredUrl');
                  }
                  final uri = Uri.tryParse(text);
                  if (uri == null || !uri.hasScheme || !uri.hasAuthority) {
                    return s.tr('captureRequiredUrl');
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
            ],
            TextFormField(
              controller: _bodyController,
              minLines: 4,
              maxLines: 10,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                labelText: s.tr('captureBody'),
                hintText: s.tr('captureBodyHint'),
                alignLabelWithHint: true,
              ),
              validator: (value) {
                if ((_sourceType == 'text') &&
                    (value ?? '').trim().isEmpty &&
                    _titleController.text.trim().isEmpty) {
                  return s.tr('captureRequiredBody');
                }
                return null;
              },
            ),
            if (_sourceType == 'image' ||
                _sourceType == 'document' ||
                _sourceType == 'voice') ...[
              const SizedBox(height: 18),
              _fileSection(s),
            ],
            const SizedBox(height: 18),
            Text(
              s.tr('captureEventDate'),
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
                          ? s.tr('capturePickDate')
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
              s.tr('captureLanguage'),
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
                _scheduleDraftSave();
              },
            ),
            const SizedBox(height: 18),
            TextFormField(
              controller: _tagsController,
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                labelText: s.tr('captureTags'),
                hintText: s.tr('captureTagsHint'),
                prefixIcon: const Icon(Icons.sell_outlined),
              ),
            ),
            if (_collections.isNotEmpty) ...[
              const SizedBox(height: 18),
              DropdownButtonFormField<int?>(
                key: ValueKey('capture-collection-$_collectionId'),
                initialValue: _collectionId,
                decoration: InputDecoration(
                  labelText: s.tr('captureCollection'),
                  prefixIcon: const Icon(Icons.folder_outlined),
                ),
                items: [
                  DropdownMenuItem<int?>(
                    value: null,
                    child: Text(s.tr('captureNone')),
                  ),
                  ..._collections.map(
                    (collection) => DropdownMenuItem<int?>(
                      value: collection.id,
                      child: Text(collection.name),
                    ),
                  ),
                ],
                onChanged: (value) {
                  setState(() => _collectionId = value);
                  _scheduleDraftSave();
                },
              ),
            ],
            if (_error != null) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: scheme.errorContainer,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.error_outline, size: 18, color: scheme.onErrorContainer),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _error!,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: scheme.onErrorContainer,
                            ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 22),
            FilledButton.icon(
              onPressed: _saving ? null : _save,
              icon: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2.2),
                    )
                  : const Icon(Icons.check_rounded),
              label: Text(_saving ? s.tr('captureSaving') : s.tr('captureSave')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sourceChip(String value, String label, IconData icon) {
    final selected = _sourceType == value;
    final scheme = Theme.of(context).colorScheme;
    return ChoiceChip(
      selected: selected,
      avatar: Icon(
        icon,
        size: 17,
        color: selected ? scheme.onPrimaryContainer : scheme.onSurfaceVariant,
      ),
      label: Text(label),
      onSelected: _saving
          ? null
          : (_) {
              _changeSource(value);
            },
    );
  }

  Widget _fileSection(S s) {
    final scheme = Theme.of(context).colorScheme;

    if (_sourceType == 'voice' && _recording) {
      final minutes = (_recordingSeconds ~/ 60).toString().padLeft(2, '0');
      final seconds = (_recordingSeconds % 60).toString().padLeft(2, '0');

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: scheme.errorContainer,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(Icons.fiber_manual_record_rounded, size: 18, color: scheme.error),
                const SizedBox(width: 8),
                Text(
                  s.tr('captureRecording'),
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: scheme.onErrorContainer,
                      ),
                ),
                const Spacer(),
                Text(
                  '$minutes:$seconds',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: scheme.onErrorContainer,
                        fontFeatures: const [FontFeature.tabularFigures()],
                      ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: _toggleRecording,
              icon: const Icon(Icons.stop_rounded),
              label: Text(s.tr('captureVoiceStop')),
            ),
          ],
        ),
      );
    }

    if (_sourceType == 'voice' && _filePath == null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          FilledButton.icon(
            onPressed: _saving ? null : _toggleRecording,
            icon: const Icon(Icons.mic_rounded),
            label: Text(s.tr('captureVoiceRecord')),
          ),
          const SizedBox(height: 8),
          Text(
            s.tr('captureVoiceHint'),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: _pickFile,
            icon: const Icon(Icons.attach_file_rounded, size: 18),
            label: Text(s.tr('captureAudioFile')),
          ),
        ],
      );
    }

    if (_sourceType == 'image' && _filePath == null) {
      return Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => _pickImage(ImageSource.camera),
              icon: const Icon(Icons.photo_camera_outlined, size: 18),
              label: Text(s.tr('captureCamera')),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => _pickImage(ImageSource.gallery),
              icon: const Icon(Icons.photo_library_outlined, size: 18),
              label: Text(s.tr('captureGallery')),
            ),
          ),
        ],
      );
    }

    if (_filePath == null) {
      return OutlinedButton.icon(
        onPressed: _pickFile,
        icon: const Icon(Icons.attach_file_rounded, size: 18),
        label: Text(
          _sourceType == 'voice' ? s.tr('captureAudioFile') : s.tr('captureDocumentFile'),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: 0.6)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_sourceType == 'image')
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.file(
                File(_filePath!),
                height: 180,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => Container(
                  height: 120,
                  color: scheme.surfaceContainerHigh,
                  alignment: Alignment.center,
                  child: const Icon(Icons.broken_image_outlined),
                ),
              ),
            ),
          if (_sourceType == 'image') const SizedBox(height: 10),
          Row(
            children: [
              Icon(
                _sourceType == 'voice' ? Icons.audio_file_outlined : Icons.insert_drive_file_outlined,
                color: scheme.primary,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  _fileName ?? s.tr('captureNoFile'),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
              IconButton(
                tooltip: s.tr('remove'),
                onPressed: () => setState(() {
                  _filePath = null;
                  _fileName = null;
                }),
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
          if (_sourceType == 'image')
            TextButton.icon(
              onPressed: () => _pickImage(ImageSource.gallery),
              icon: const Icon(Icons.swap_horiz_rounded, size: 18),
              label: Text(s.tr('captureChangeFile')),
            ),
          if (_sourceType == 'voice')
            TextButton.icon(
              onPressed: _saving ? null : _startRecording,
              icon: const Icon(Icons.mic_rounded, size: 18),
              label: Text(s.tr('captureVoiceRecord')),
            ),
        ],
      ),
    );
  }
}
