import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api_exception.dart';
import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../l10n/strings.dart';
import '../../models/memory.dart';

const _reminderTypes = ['custom', 'warranty', 'renewal', 'follow_up', 'maintenance', 'event'];

class ReminderFormSheet extends StatefulWidget {
  const ReminderFormSheet({
    super.key,
    this.initialMemory,
    this.memories = const [],
  });

  final Memory? initialMemory;
  final List<MemoryOption> memories;

  @override
  State<ReminderFormSheet> createState() => _ReminderFormSheetState();
}

class _ReminderFormSheetState extends State<ReminderFormSheet> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _notesController = TextEditingController();

  late DateTime _dueDate;
  late TimeOfDay _dueTime;
  late String _type;
  late String _channel;
  int? _memoryId;
  List<MemoryOption> _memories = const [];
  bool _loadingMemories = false;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final initial = DateTime.now().add(const Duration(days: 1));
    _dueDate = DateTime(initial.year, initial.month, initial.day);
    _dueTime = const TimeOfDay(hour: 9, minute: 0);
    _type = widget.initialMemory?.memoryType ?? 'custom';
    if (!_reminderTypes.contains(_type)) {
      _type = 'custom';
    }
    _channel = 'in_app';
    _memories = widget.memories;
    _memoryId = widget.initialMemory?.id;
    if (_memories.isEmpty) {
      _loadMemories();
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _loadMemories() async {
    setState(() => _loadingMemories = true);
    try {
      final data = await context.read<AppState>().api.reminders();
      if (mounted) {
        setState(() {
          _memories = [
            if (widget.initialMemory != null &&
                !data.memories.any((item) => item.id == widget.initialMemory!.id))
              MemoryOption(
                id: widget.initialMemory!.id,
                title: widget.initialMemory!.title,
                memoryType: widget.initialMemory!.memoryType,
                typeLabel: widget.initialMemory!.typeLabel,
              ),
            ...data.memories,
          ];
        });
      }
    } catch (_) {
      // The linked memory is optional.
    } finally {
      if (mounted) {
        setState(() => _loadingMemories = false);
      }
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: _dueDate,
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 20),
    );
    if (selected != null && mounted) {
      setState(() => _dueDate = selected);
    }
  }

  Future<void> _pickTime() async {
    final selected = await showTimePicker(
      context: context,
      initialTime: _dueTime,
    );
    if (selected != null && mounted) {
      setState(() => _dueTime = selected);
    }
  }

  DateTime get _dueAt {
    return DateTime(
      _dueDate.year,
      _dueDate.month,
      _dueDate.day,
      _dueTime.hour,
      _dueTime.minute,
    );
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final reminder = await context.read<AppState>().api.createReminder(
            title: _titleController.text.trim(),
            dueAt: _dueAt,
            type: _type,
            memoryId: _memoryId,
            notes: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
            channel: _channel,
          );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.s.tr('remindersCreated'))),
        );
        Navigator.of(context).pop(reminder);
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

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                s.tr('remindersNew'),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _titleController,
                autofocus: true,
                textCapitalization: TextCapitalization.sentences,
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(
                  labelText: s.tr('remindersTitleLabel'),
                  prefixIcon: const Icon(Icons.notifications_none_rounded),
                ),
                validator: (value) {
                  if ((value ?? '').trim().isEmpty) {
                    return s.tr('editTitleRequired');
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _pickDate,
                      icon: const Icon(Icons.event_outlined, size: 18),
                      label: Text(
                        formatDate(
                          _dueDate,
                          app.settings?.dateFormat ?? 'd M Y',
                          app.locale,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _pickTime,
                      icon: const Icon(Icons.schedule_rounded, size: 18),
                      label: Text(_dueTime.format(context)),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                key: ValueKey('reminder-type-$_type'),
                initialValue: _type,
                decoration: InputDecoration(
                  labelText: s.tr('remindersType'),
                  prefixIcon: const Icon(Icons.category_outlined),
                ),
                items: _reminderTypes
                    .map(
                      (type) => DropdownMenuItem(
                        value: type,
                        child: Text(s.tr('reminderType.$type')),
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
              DropdownButtonFormField<int?>(
                key: ValueKey('reminder-memory-$_memoryId'),
                initialValue: _memoryId,
                decoration: InputDecoration(
                  labelText: s.tr('remindersMemory'),
                  prefixIcon: const Icon(Icons.link_rounded),
                  suffixIcon: _loadingMemories
                      ? const Padding(
                          padding: EdgeInsets.all(14),
                          child: SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        )
                      : null,
                ),
                items: [
                  DropdownMenuItem<int?>(
                    value: null,
                    child: Text(s.tr('remindersNoMemory')),
                  ),
                  ..._memories.map(
                    (memory) => DropdownMenuItem<int?>(
                      value: memory.id,
                      child: Text(
                        memory.title,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ),
                ],
                onChanged: (value) => setState(() => _memoryId = value),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _notesController,
                minLines: 2,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: s.tr('remindersNotes'),
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 12),
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(
                    value: 'in_app',
                    label: Text(s.tr('remindersInApp')),
                    icon: const Icon(Icons.phone_android_rounded, size: 16),
                  ),
                  ButtonSegment(
                    value: 'email',
                    label: Text(s.tr('remindersEmail')),
                    icon: const Icon(Icons.mail_outline_rounded, size: 16),
                  ),
                ],
                selected: {_channel},
                onSelectionChanged: (values) {
                  setState(() => _channel = values.first);
                },
              ),
              if (_error != null) ...[
                const SizedBox(height: 14),
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
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: _saving ? null : _save,
                icon: _saving
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2.2),
                      )
                    : const Icon(Icons.check_rounded),
                label: Text(_saving ? s.tr('loading') : s.tr('remindersCreate')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
