import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../l10n/strings.dart';
import '../../models/memory.dart';
import '../../widgets/common.dart';
import '../../widgets/memory_card.dart';
import '../memory/memory_detail_screen.dart';

const _libraryTypes = [
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

class LibraryScreen extends StatefulWidget {
  const LibraryScreen({super.key, this.initialCollection, this.title});

  final int? initialCollection;
  final String? title;

  @override
  State<LibraryScreen> createState() => _LibraryScreenState();
}

class _LibraryScreenState extends State<LibraryScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();
  Timer? _debounce;

  final List<Memory> _memories = [];
  List<Collection> _collections = const [];
  String _mode = 'library';
  String _type = 'all';
  String _sort = 'newest';
  int? _collection;
  int _page = 1;
  int _total = 0;
  int _totalForMode = 0;
  bool _hasMore = false;
  bool _loading = false;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _collection = widget.initialCollection;
    _scrollController.addListener(_onScroll);
    _load(reset: true);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_scrollController.hasClients) {
      return;
    }
    final position = _scrollController.position;
    if (position.pixels >= position.maxScrollExtent - 320 &&
        _hasMore &&
        !_loading &&
        !_loadingMore) {
      _load();
    }
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
        _memories.clear();
      });
    } else {
      setState(() => _loadingMore = true);
    }

    try {
      final result = await context.read<AppState>().api.memories(
            mode: _mode,
            query: _searchController.text,
            type: _type,
            sort: _sort,
            collection: _collection,
            page: reset ? 1 : _page + 1,
            perPage: 12,
          );

      if (mounted) {
        setState(() {
          if (reset) {
            _memories
              ..clear()
              ..addAll(result.memories);
          } else {
            _memories.addAll(result.memories);
          }
          _page = result.page;
          _hasMore = result.hasMore;
          _total = result.total;
          _totalForMode = result.totalForMode;
          _collections = result.collections;
          _loading = false;
          _loadingMore = false;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _loadingMore = false;
          if (reset) {
            _error = describeError(error, context.s);
          }
        });
        if (!reset) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(describeError(error, context.s))),
          );
        }
      }
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () => _load(reset: true));
  }

  void _setMode(String mode) {
    if (_mode == mode) {
      return;
    }
    setState(() => _mode = mode);
    _load(reset: true);
  }

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title ?? s.tr('libraryTitle')),
        actions: [
          IconButton(
            tooltip: s.tr('retry'),
            onPressed: _loading ? null : () => _load(reset: true),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _load(reset: true),
        child: CustomScrollView(
          controller: _scrollController,
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SegmentedButton<String>(
                      showSelectedIcon: false,
                      segments: [
                        ButtonSegment(value: 'library', label: Text(s.tr('libraryAll'))),
                        ButtonSegment(value: 'favorites', label: Text(s.tr('libraryFavorites'))),
                        ButtonSegment(value: 'archive', label: Text(s.tr('libraryArchive'))),
                      ],
                      selected: {_mode},
                      onSelectionChanged: (values) => _setMode(values.first),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _searchController,
                      textInputAction: TextInputAction.search,
                      onChanged: _onSearchChanged,
                      onSubmitted: (_) => _load(reset: true),
                      decoration: InputDecoration(
                        hintText: s.tr('librarySearchHint'),
                        prefixIcon: const Icon(Icons.search_rounded),
                        suffixIcon: _searchController.text.isEmpty
                            ? null
                            : IconButton(
                                tooltip: s.tr('clear'),
                                onPressed: () {
                                  _searchController.clear();
                                  _load(reset: true);
                                },
                                icon: const Icon(Icons.close_rounded),
                              ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            key: ValueKey('library-type-$_type'),
                            initialValue: _type,
                            isDense: true,
                            decoration: InputDecoration(
                              labelText: s.tr('libraryType'),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            ),
                            items: [
                              DropdownMenuItem(
                                value: 'all',
                                child: Text(s.tr('libraryAllTypes')),
                              ),
                              ..._libraryTypes.map(
                                (type) => DropdownMenuItem(
                                  value: type,
                                  child: Text(s.tr('type.$type')),
                                ),
                              ),
                            ],
                            onChanged: (value) {
                              setState(() => _type = value ?? 'all');
                              _load(reset: true);
                            },
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            key: ValueKey('library-sort-$_sort'),
                            initialValue: _sort,
                            isDense: true,
                            decoration: InputDecoration(
                              labelText: s.tr('librarySort'),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                            ),
                            items: [
                              DropdownMenuItem(value: 'newest', child: Text(s.tr('librarySortNewest'))),
                              DropdownMenuItem(value: 'oldest', child: Text(s.tr('librarySortOldest'))),
                              DropdownMenuItem(value: 'event', child: Text(s.tr('librarySortEvent'))),
                              DropdownMenuItem(value: 'az', child: Text(s.tr('librarySortAz'))),
                            ],
                            onChanged: (value) {
                              setState(() => _sort = value ?? 'newest');
                              _load(reset: true);
                            },
                          ),
                        ),
                      ],
                    ),
                    if (_collections.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      DropdownButtonFormField<int?>(
                        key: ValueKey('library-collection-$_collection'),
                        initialValue: _collection,
                        isDense: true,
                        decoration: InputDecoration(
                          labelText: s.tr('libraryCollection'),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        ),
                        items: [
                          DropdownMenuItem<int?>(
                            value: null,
                            child: Text(s.tr('libraryAllCollections')),
                          ),
                          ..._collections.map(
                            (collection) => DropdownMenuItem<int?>(
                              value: collection.id,
                              child: Text(collection.name),
                            ),
                          ),
                        ],
                        onChanged: (value) {
                          setState(() => _collection = value);
                          _load(reset: true);
                        },
                      ),
                    ],
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Text(
                          s.tr('libraryTotal', {'count': _totalForMode}),
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: scheme.onSurfaceVariant,
                                fontWeight: FontWeight.w600,
                              ),
                        ),
                        if (_total != _totalForMode && _searchController.text.trim().isNotEmpty) ...[
                          const SizedBox(width: 6),
                          Text(
                            '• ${s.tr('libraryTotal', {'count': _total})}',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: scheme.onSurfaceVariant,
                                ),
                          ),
                        ],
                      ],
                    ),
                    if (_mode == 'archive') ...[
                      const SizedBox(height: 8),
                      Text(
                        s.tr('libraryArchiveHint'),
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                      ),
                    ],
                    const SizedBox(height: 8),
                  ],
                ),
              ),
            ),
            if (_loading)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Padding(
                  padding: EdgeInsets.only(bottom: 60),
                  child: LoadingView(),
                ),
              )
            else if (_error != null)
              SliverFillRemaining(
                hasScrollBody: false,
                child: ErrorState(
                  message: _error!,
                  onRetry: () => _load(reset: true),
                ),
              )
            else if (_memories.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: EmptyState(
                  icon: _mode == 'archive'
                      ? Icons.inventory_2_outlined
                      : _mode == 'favorites'
                          ? Icons.star_outline_rounded
                          : Icons.auto_stories_outlined,
                  title: _mode == 'archive'
                      ? s.tr('libraryEmptyArchive')
                      : _mode == 'favorites'
                          ? s.tr('libraryEmptyFavorites')
                          : (_searchController.text.trim().isEmpty
                              ? s.tr('libraryEmpty')
                              : s.tr('libraryNoMatches')),
                  body: s.tr('libraryEmptyBody'),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 18),
                sliver: SliverList.separated(
                  itemCount: _memories.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final memory = _memories[index];
                    return MemoryCard(
                      memory: memory,
                      onTap: () => _openMemory(memory.id),
                    );
                  },
                ),
              ),
            if (_loadingMore)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 90),
                  child: Center(
                    child: Text(
                      s.tr('libraryLoadMore'),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _openMemory(int id) async {
    final app = context.read<AppState>();
    final version = app.dataVersion;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => MemoryDetailScreen(memoryId: id)),
    );
    if (mounted && app.dataVersion != version) {
      _load(reset: true);
    }
  }
}
