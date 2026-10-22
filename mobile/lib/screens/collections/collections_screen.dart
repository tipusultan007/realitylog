import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api_exception.dart';
import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../l10n/strings.dart';
import '../../models/memory.dart';
import '../../widgets/common.dart';
import '../library/library_screen.dart';

const _collectionColors = [
  '#D95F3B',
  '#517A64',
  '#B8862F',
  '#4A6D8C',
  '#7B5EA7',
  '#8C4A5F',
];

class CollectionsScreen extends StatefulWidget {
  const CollectionsScreen({super.key});

  @override
  State<CollectionsScreen> createState() => _CollectionsScreenState();
}

class _CollectionsScreenState extends State<CollectionsScreen> {
  List<Collection> _collections = const [];
  bool _loading = true;
  String? _error;

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
      final collections = await context.read<AppState>().api.collections();
      if (mounted) {
        setState(() {
          _collections = collections;
          _loading = false;
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

  Future<void> _openForm({Collection? collection}) async {
    final result = await showModalBottomSheet<({String name, String? description, String color})>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => _CollectionFormSheet(collection: collection),
    );

    if (result == null || !mounted) {
      return;
    }

    try {
      final api = context.read<AppState>().api;
      if (collection == null) {
        await api.createCollection(
          name: result.name,
          description: result.description,
          color: result.color,
        );
      } else {
        await api.updateCollection(
          collection.id,
          name: result.name,
          description: result.description ?? '',
          color: result.color,
        );
      }
      if (!mounted) {
        return;
      }
      _showMessage(context.s.tr(collection == null ? 'collectionsCreated' : 'collectionsUpdated'));
      await _load();
    } catch (error) {
      if (mounted) {
        _showMessage(error is ApiException ? error.detail : context.s.tr('errorUnknown'));
      }
    }
  }

  Future<void> _delete(Collection collection) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.s.tr('collectionsDeleteTitle')),
        content: Text(context.s.tr('collectionsDeleteBody')),
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
      await context.read<AppState>().api.deleteCollection(collection.id);
      if (!mounted) {
        return;
      }
      _showMessage(context.s.tr('collectionsDeleted'));
      await _load();
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    }
  }

  Future<void> _openCollection(Collection collection) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => LibraryScreen(
          initialCollection: collection.id,
          title: collection.name,
        ),
      ),
    );
    if (mounted) {
      await _load();
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = context.s;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.tr('collectionsTitle')),
        actions: [
          IconButton(
            tooltip: s.tr('retry'),
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.create_new_folder_outlined),
        label: Text(s.tr('collectionsNew')),
      ),
      body: _loading && _collections.isEmpty
          ? const LoadingView()
          : _error != null
              ? ErrorState(message: _error!, onRetry: _load)
              : _collections.isEmpty
                  ? EmptyState(
                      icon: Icons.folder_copy_outlined,
                      title: s.tr('collectionsEmpty'),
                      body: s.tr('collectionsEmptyBody'),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 96),
                        itemCount: _collections.length + 1,
                        separatorBuilder: (_, _) => const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          if (index == 0) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: Text(
                                s.tr('collectionsSubtitle'),
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                                    ),
                              ),
                            );
                          }
                          return _CollectionCard(
                            collection: _collections[index - 1],
                            onTap: () => _openCollection(_collections[index - 1]),
                            onRename: () => _openForm(collection: _collections[index - 1]),
                            onDelete: () => _delete(_collections[index - 1]),
                          );
                        },
                      ),
                    ),
    );
  }
}

class _CollectionCard extends StatelessWidget {
  const _CollectionCard({
    required this.collection,
    required this.onTap,
    required this.onRename,
    required this.onDelete,
  });

  final Collection collection;
  final VoidCallback onTap;
  final VoidCallback onRename;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final color = _parseColor(collection.color) ?? scheme.primary;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 4, 14),
          child: Row(
            children: [
              Container(
                width: 12,
                height: 44,
                decoration: BoxDecoration(
                  color: color,
                  borderRadius: BorderRadius.circular(6),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      collection.name,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      collection.description?.trim().isNotEmpty == true
                          ? collection.description!
                          : s.tr('collectionsCount', {'count': collection.memoriesCount}),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                    ),
                    if (collection.description?.trim().isNotEmpty == true) ...[
                      const SizedBox(height: 2),
                      Text(
                        s.tr('collectionsCount', {'count': collection.memoriesCount}),
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                      ),
                    ],
                  ],
                ),
              ),
              PopupMenuButton<String>(
                onSelected: (value) {
                  if (value == 'rename') {
                    onRename();
                  } else if (value == 'delete') {
                    onDelete();
                  }
                },
                itemBuilder: (context) => [
                  PopupMenuItem(value: 'rename', child: Text(s.tr('collectionsRename'))),
                  PopupMenuItem(value: 'delete', child: Text(s.tr('collectionsDelete'))),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CollectionFormSheet extends StatefulWidget {
  const _CollectionFormSheet({this.collection});

  final Collection? collection;

  @override
  State<_CollectionFormSheet> createState() => _CollectionFormSheetState();
}

class _CollectionFormSheetState extends State<_CollectionFormSheet> {
  late final TextEditingController _nameController;
  late final TextEditingController _descriptionController;
  late String _color;
  String? _error;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.collection?.name ?? '');
    _descriptionController = TextEditingController(
      text: widget.collection?.description ?? '',
    );
    _color = widget.collection?.color ?? _collectionColors.first;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  void _submit() {
    final name = _nameController.text.trim();
    if (name.isEmpty) {
      setState(() => _error = context.s.tr('collectionsNameRequired'));
      return;
    }
    final description = _descriptionController.text.trim();
    Navigator.of(context).pop((
      name: name,
      description: description.isEmpty ? null : description,
      color: _color,
    ));
  }

  @override
  Widget build(BuildContext context) {
    final s = context.s;

    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        4,
        20,
        MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            widget.collection == null ? s.tr('collectionsNew') : s.tr('collectionsRename'),
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _nameController,
            autofocus: true,
            textCapitalization: TextCapitalization.sentences,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              labelText: s.tr('collectionsName'),
              hintText: s.tr('collectionsNameHint'),
              prefixIcon: const Icon(Icons.folder_outlined),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _descriptionController,
            minLines: 2,
            maxLines: 4,
            textCapitalization: TextCapitalization.sentences,
            decoration: InputDecoration(
              labelText: s.tr('collectionsDescription'),
              alignLabelWithHint: true,
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ],
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: _collectionColors.map((value) {
              final selected = value == _color;
              return InkWell(
                borderRadius: BorderRadius.circular(24),
                onTap: () => setState(() => _color = value),
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: _parseColor(value),
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: selected
                          ? Theme.of(context).colorScheme.onSurface
                          : Colors.transparent,
                      width: 2.5,
                    ),
                  ),
                  child: selected
                      ? const Icon(Icons.check_rounded, size: 18, color: Colors.white)
                      : null,
                ),
              );
            }).toList(growable: false),
          ),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: _submit,
            icon: const Icon(Icons.check_rounded),
            label: Text(
              widget.collection == null ? s.tr('collectionsCreate') : s.tr('save'),
            ),
          ),
        ],
      ),
    );
  }
}

Color? _parseColor(String value) {
  final cleaned = value.replaceFirst('#', '').trim();
  if (cleaned.length != 6) {
    return null;
  }
  final parsed = int.tryParse(cleaned, radix: 16);
  if (parsed == null) {
    return null;
  }
  return Color(0xFF000000 | parsed);
}
