import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../l10n/strings.dart';
import '../../models/responses.dart';
import '../../widgets/common.dart';
import '../memory/memory_detail_screen.dart';

const _types = [
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

const _dateFilters = ['all', 'last_week', 'last_month', 'last_year', 'this_year'];

class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key, this.initialQuery});

  final String? initialQuery;

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final _controller = TextEditingController();
  Timer? _debounce;

  SearchResultData? _data;
  List<String> _recent = const [];
  bool _loading = false;
  String? _error;

  String _type = 'all';
  String _date = 'all';
  String _language = 'all';
  bool _favorite = false;
  bool _archived = false;

  @override
  void initState() {
    super.initState();
    final initial = widget.initialQuery?.trim() ?? '';
    if (initial.isNotEmpty) {
      _controller.text = initial;
      _search();
    } else {
      _loadRecent();
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  Future<void> _loadRecent() async {
    try {
      final data = await context.read<AppState>().api.search(query: '');
      if (mounted) {
        setState(() {
          _recent = data.recentSearches;
          _data = null;
          _error = null;
        });
      }
    } catch (_) {
      // Recent searches are a convenience, not a blocker.
    }
  }

  Future<void> _search() async {
    final query = _controller.text.trim();
    if (query.isEmpty) {
      setState(() {
        _data = null;
        _error = null;
        _loading = false;
      });
      await _loadRecent();
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<AppState>().api.search(
            query: query,
            type: _type,
            date: _date,
            language: _language,
            favorite: _favorite,
            archived: _archived,
          );
      if (mounted) {
        setState(() {
          _data = data;
          _recent = data.recentSearches;
          _loading = false;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = describeError(error, context.s);
          _loading = false;
        });
      }
    }
  }

  void _onChanged(String value) {
    _debounce?.cancel();

    if (value.trim().isEmpty) {
      setState(() {
        _data = null;
        _error = null;
      });
      _debounce = Timer(const Duration(milliseconds: 350), _loadRecent);
      return;
    }

    if (value.trim().length < 2) {
      return;
    }

    _debounce = Timer(const Duration(milliseconds: 500), _search);
  }

  void _useQuery(String query) {
    _controller.text = query;
    _controller.selection = TextSelection.collapsed(offset: query.length);
    _search();
  }

  Future<void> _openFilters() async {
    final result = await showModalBottomSheet<_SearchFilters>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _FilterSheet(
        initial: _SearchFilters(
          type: _type,
          date: _date,
          language: _language,
          favorite: _favorite,
          archived: _archived,
        ),
      ),
    );

    if (result != null && mounted) {
      setState(() {
        _type = result.type;
        _date = result.date;
        _language = result.language;
        _favorite = result.favorite;
        _archived = result.archived;
      });
      if (_controller.text.trim().isNotEmpty) {
        _search();
      }
    }
  }

  bool get _hasFilters {
    return _type != 'all' ||
        _date != 'all' ||
        _language != 'all' ||
        _favorite ||
        _archived;
  }

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final data = _data;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.tr('searchTitle')),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 36),
        children: [
          Text(
            s.tr('searchSubtitle'),
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _controller,
            textInputAction: TextInputAction.search,
            onChanged: _onChanged,
            onSubmitted: (_) => _search(),
            decoration: InputDecoration(
              hintText: s.tr('searchHint'),
              prefixIcon: const Icon(Icons.manage_search_rounded),
              suffixIcon: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (_controller.text.isNotEmpty)
                    IconButton(
                      tooltip: s.tr('clear'),
                      onPressed: () {
                        _controller.clear();
                        setState(() {
                          _data = null;
                          _error = null;
                        });
                        _loadRecent();
                      },
                      icon: const Icon(Icons.close_rounded),
                    ),
                  IconButton(
                    tooltip: s.tr('searchFilters'),
                    onPressed: _openFilters,
                    icon: Badge(
                      isLabelVisible: _hasFilters,
                      child: const Icon(Icons.tune_rounded),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (_hasFilters) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                if (_type != 'all')
                  InfoPill(
                    label: s.tr('type.$_type'),
                    icon: Icons.sell_outlined,
                  ),
                if (_date != 'all')
                  InfoPill(
                    label: _dateLabel(_date, s),
                    icon: Icons.event_outlined,
                  ),
                if (_language != 'all')
                  InfoPill(
                    label: _language == 'bn' ? 'বাংলা' : 'English',
                    icon: Icons.translate_rounded,
                  ),
                if (_favorite)
                  InfoPill(label: s.tr('searchFavorites'), icon: Icons.star_outline_rounded),
                if (_archived)
                  InfoPill(label: s.tr('searchArchived'), icon: Icons.inventory_2_outlined),
              ],
            ),
          ],
          const SizedBox(height: 18),
          if (_loading)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 44),
              child: LoadingView(message: s.tr('searchSearching')),
            )
          else if (_error != null)
            ErrorState(message: _error!, onRetry: _search)
          else if (data == null)
            _StartPrompt(
              recent: _recent,
              onUseQuery: _useQuery,
            )
          else ...[
            _AnswerCard(
              data: data,
              onOpenMemory: _openMemory,
            ),
            if (data.sources.isNotEmpty) ...[
              const SizedBox(height: 22),
              SectionHeader(title: s.tr('searchSources')),
              ...data.sources.asMap().entries.map(
                    (entry) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _SourceCard(
                        index: entry.key + 1,
                        source: entry.value,
                        onTap: () => _openMemory(entry.value.memory.id),
                      ),
                    ),
                  ),
            ],
            if (data.total > data.sources.length && data.results.isNotEmpty) ...[
              const SizedBox(height: 18),
              SectionHeader(title: s.tr('searchResults')),
              ...data.results
                  .where(
                    (hit) => !data.sources.any((source) => source.memory.id == hit.memory.id),
                  )
                  .take(12)
                  .map(
                    (hit) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _ResultRow(
                        hit: hit,
                        onTap: () => _openMemory(hit.memory.id),
                      ),
                    ),
                  ),
            ],
            if (_recent.isNotEmpty) ...[
              const SizedBox(height: 20),
              SectionHeader(title: s.tr('searchRecent')),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _recent
                    .map(
                      (query) => ActionChip(
                        label: Text(query),
                        onPressed: () => _useQuery(query),
                      ),
                    )
                    .toList(growable: false),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Future<void> _openMemory(int id) async {
    final app = context.read<AppState>();
    final version = app.dataVersion;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => MemoryDetailScreen(memoryId: id)),
    );
    if (mounted && app.dataVersion != version && _controller.text.trim().isNotEmpty) {
      _search();
    }
  }

  String _dateLabel(String value, S s) {
    return switch (value) {
      'last_week' => s.tr('searchDateLastWeek'),
      'last_month' => s.tr('searchDateLastMonth'),
      'last_year' => s.tr('searchDateLastYear'),
      'this_year' => s.tr('searchDateThisYear'),
      _ => s.tr('searchDateAll'),
    };
  }
}

class _StartPrompt extends StatelessWidget {
  const _StartPrompt({required this.recent, required this.onUseQuery});

  final List<String> recent;
  final ValueChanged<String> onUseQuery;

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: scheme.surfaceContainerLow,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: scheme.outlineVariant.withValues(alpha: 0.55)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.auto_awesome_outlined, color: scheme.primary, size: 28),
              const SizedBox(height: 10),
              Text(
                s.tr('searchStartTitle'),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const SizedBox(height: 6),
              Text(
                s.tr('searchExamples'),
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
              ),
            ],
          ),
        ),
        if (recent.isNotEmpty) ...[
          const SizedBox(height: 22),
          SectionHeader(title: s.tr('searchRecent')),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: recent
                .map(
                  (query) => ActionChip(
                    label: Text(query),
                    onPressed: () => onUseQuery(query),
                  ),
                )
                .toList(growable: false),
          ),
        ],
      ],
    );
  }
}

class _AnswerCard extends StatelessWidget {
  const _AnswerCard({required this.data, required this.onOpenMemory});

  final SearchResultData data;
  final ValueChanged<int> onOpenMemory;

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final hasAnswer = data.hasAnswer && (data.answer?.trim().isNotEmpty ?? false);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: hasAnswer
            ? LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [scheme.primaryContainer, scheme.surface],
              )
            : null,
        color: hasAnswer ? null : scheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: hasAnswer
              ? scheme.primary.withValues(alpha: 0.2)
              : scheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                hasAnswer ? Icons.auto_awesome_rounded : Icons.search_off_rounded,
                color: hasAnswer ? scheme.primary : scheme.onSurfaceVariant,
                size: 22,
              ),
              const SizedBox(width: 8),
              Text(
                s.tr('searchAnswer'),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              const Spacer(),
              if (hasAnswer) _ConfidencePill(confidence: data.confidence),
            ],
          ),
          const SizedBox(height: 12),
          if (hasAnswer)
            SelectableText(
              data.answer!,
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    height: 1.6,
                    fontSize: 16,
                  ),
            )
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  s.tr('searchNoAnswer'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 6),
                Text(
                  s.tr('searchNoAnswerBody'),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                ),
              ],
            ),
          if (data.tooShort) ...[
            const SizedBox(height: 10),
            InfoPill(label: s.tr('searchTooShort'), icon: Icons.info_outline_rounded),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              InfoPill(
                label:
                    '${s.tr('searchAnswerLanguage')}: ${data.locale == 'bn' ? 'বাংলা' : 'English'}',
                icon: Icons.translate_rounded,
              ),
              const SizedBox(width: 8),
              if (data.dateRange != null)
                InfoPill(
                  label: _rangeLabel(data.dateRange!.label, s),
                  icon: Icons.date_range_outlined,
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            s.tr('searchDisclaimer'),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                  height: 1.4,
                ),
          ),
        ],
      ),
    );
  }

  String _rangeLabel(String label, S s) {
    final key = 'range.$label';
    final translated = s.tr(key);
    return translated == key ? label : translated;
  }
}

class _ConfidencePill extends StatelessWidget {
  const _ConfidencePill({required this.confidence});

  final String confidence;

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final label = switch (confidence) {
      'high' => s.tr('searchConfidenceHigh'),
      'medium' => s.tr('searchConfidenceMedium'),
      _ => s.tr('searchConfidenceLow'),
    };
    final tone = switch (confidence) {
      'high' => scheme.secondary,
      'medium' => scheme.tertiary,
      _ => scheme.outline,
    };

    return InfoPill(label: label, icon: Icons.verified_outlined, tone: tone);
  }
}

class _SourceCard extends StatelessWidget {
  const _SourceCard({required this.index, required this.source, required this.onTap});

  final int index;
  final SearchSource source;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  InfoPill(
                    label: s.tr('searchSourceN', {'n': index}),
                    icon: Icons.link_rounded,
                  ),
                  const SizedBox(width: 8),
                  _MatchBadge(
                    matchType: source.matchType,
                    similarity: source.similarity,
                    vectorMatch: source.vectorMatch,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      source.date,
                      textAlign: TextAlign.right,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                source.memory.title,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
              if (source.excerpt.isNotEmpty) ...[
                const SizedBox(height: 5),
                Text(
                  source.excerpt,
                  maxLines: 4,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: scheme.onSurfaceVariant,
                        height: 1.45,
                      ),
                ),
              ],
              const SizedBox(height: 8),
              Row(
                children: [
                  Text(
                    s.tr('type.${source.memory.memoryType}'),
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: scheme.secondary,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const Spacer(),
                  Icon(Icons.arrow_forward_rounded, size: 16, color: scheme.primary),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ResultRow extends StatelessWidget {
  const _ResultRow({required this.hit, required this.onTap});

  final SearchHit hit;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        onTap: onTap,
        leading: CircleAvatar(
          backgroundColor: scheme.secondaryContainer,
          child: Icon(Icons.sticky_note_2_outlined, color: scheme.onSecondaryContainer, size: 20),
        ),
        title: Text(
          hit.memory.title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
        ),
        subtitle: Wrap(
          spacing: 8,
          runSpacing: 4,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(
              context.s.tr('type.${hit.memory.memoryType}'),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            _MatchBadge(
              matchType: hit.matchType,
              similarity: hit.similarity,
              vectorMatch: hit.vectorMatch,
            ),
          ],
        ),
        trailing: const Icon(Icons.chevron_right_rounded),
      ),
    );
  }
}

class _MatchBadge extends StatelessWidget {
  const _MatchBadge({
    required this.matchType,
    this.similarity = 0,
    this.vectorMatch = false,
  });

  final String matchType;
  final double similarity;
  final bool vectorMatch;

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final label = switch (matchType) {
      'exact' => s.tr('searchMatchExact'),
      'semantic' => s.tr('searchMatchSemantic'),
      _ => s.tr('searchMatchRelated'),
    };
    final percent =
        vectorMatch && similarity > 0 ? ' ${(similarity * 100).round()}%' : '';
    final icon = switch (matchType) {
      'exact' => Icons.check_circle_outline,
      'semantic' => Icons.auto_awesome_outlined,
      _ => Icons.link_rounded,
    };

    return InfoPill(label: '$label$percent', icon: icon);
  }
}

class _SearchFilters {
  const _SearchFilters({
    required this.type,
    required this.date,
    required this.language,
    required this.favorite,
    required this.archived,
  });

  final String type;
  final String date;
  final String language;
  final bool favorite;
  final bool archived;
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.initial});

  final _SearchFilters initial;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late String _type = widget.initial.type;
  late String _date = widget.initial.date;
  late String _language = widget.initial.language;
  late bool _favorite = widget.initial.favorite;
  late bool _archived = widget.initial.archived;

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.of(context).viewInsets.bottom),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              s.tr('searchFilters'),
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              key: ValueKey('filter-type-$_type'),
              initialValue: _type,
              decoration: InputDecoration(labelText: s.tr('searchType')),
              items: [
                DropdownMenuItem(value: 'all', child: Text(s.tr('all'))),
                ..._types.map(
                  (type) => DropdownMenuItem(
                    value: type,
                    child: Text(s.tr('type.$type')),
                  ),
                ),
              ],
              onChanged: (value) => setState(() => _type = value ?? 'all'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              key: ValueKey('filter-date-$_date'),
              initialValue: _date,
              decoration: InputDecoration(labelText: s.tr('searchDate')),
              items: _dateFilters
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(_dateLabel(value, s)),
                    ),
                  )
                  .toList(growable: false),
              onChanged: (value) => setState(() => _date = value ?? 'all'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              key: ValueKey('filter-language-$_language'),
              initialValue: _language,
              decoration: InputDecoration(labelText: s.tr('searchLanguage')),
              items: [
                DropdownMenuItem(value: 'all', child: Text(s.tr('all'))),
                const DropdownMenuItem(value: 'en', child: Text('English')),
                const DropdownMenuItem(value: 'bn', child: Text('বাংলা')),
              ],
              onChanged: (value) => setState(() => _language = value ?? 'all'),
            ),
            const SizedBox(height: 8),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _favorite,
              onChanged: (value) => setState(() => _favorite = value),
              title: Text(s.tr('searchFavorites')),
              secondary: Icon(Icons.star_outline_rounded, color: scheme.tertiary),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _archived,
              onChanged: (value) => setState(() => _archived = value),
              title: Text(s.tr('searchArchived')),
              secondary: Icon(Icons.inventory_2_outlined, color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => setState(() {
                      _type = 'all';
                      _date = 'all';
                      _language = 'all';
                      _favorite = false;
                      _archived = false;
                    }),
                    child: Text(s.tr('searchReset')),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: () => Navigator.of(context).pop(
                      _SearchFilters(
                        type: _type,
                        date: _date,
                        language: _language,
                        favorite: _favorite,
                        archived: _archived,
                      ),
                    ),
                    child: Text(s.tr('searchApply')),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _dateLabel(String value, S s) {
    return switch (value) {
      'last_week' => s.tr('searchDateLastWeek'),
      'last_month' => s.tr('searchDateLastMonth'),
      'last_year' => s.tr('searchDateLastYear'),
      'this_year' => s.tr('searchDateThisYear'),
      _ => s.tr('searchDateAll'),
    };
  }
}
