import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../l10n/strings.dart';
import '../../models/responses.dart';
import '../../widgets/common.dart';
import '../../widgets/memory_card.dart';
import '../memory/memory_detail_screen.dart';

const _timelineTypes = [
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

class TimelineScreen extends StatefulWidget {
  const TimelineScreen({super.key});

  @override
  State<TimelineScreen> createState() => _TimelineScreenState();
}

class _TimelineScreenState extends State<TimelineScreen> {
  late String _month;
  String _type = 'all';
  TimelineData? _data;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _month = '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}';
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<AppState>().api.timeline(month: _month, type: _type);
      if (mounted) {
        setState(() {
          _data = data;
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

  void _shiftMonth(int delta) {
    final parsed = DateTime.tryParse('$_month-01') ?? DateTime.now();
    final shifted = DateTime(parsed.year, parsed.month + delta, 1);
    setState(() {
      _month = '${shifted.year.toString().padLeft(4, '0')}-'
          '${shifted.month.toString().padLeft(2, '0')}';
    });
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final data = _data;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.tr('timelineTitle')),
        actions: [
          IconButton(
            tooltip: s.tr('retry'),
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 36),
          children: [
            Text(
              s.tr('timelineSubtitle'),
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              decoration: BoxDecoration(
                color: scheme.surface,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: scheme.outlineVariant.withValues(alpha: 0.6)),
              ),
              child: Row(
                children: [
                  IconButton(
                    tooltip: s.tr('previous'),
                    onPressed: () => _shiftMonth(-1),
                    icon: const Icon(Icons.chevron_left_rounded),
                  ),
                  Expanded(
                    child: Column(
                      children: [
                        Text(
                          formatMonthLabel(_month, app.locale),
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                        Text(
                          s.tr('timelineCount', {'count': data?.monthTotal ?? 0}),
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: scheme.onSurfaceVariant,
                              ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: s.tr('next'),
                    onPressed: () => _shiftMonth(1),
                    icon: const Icon(Icons.chevron_right_rounded),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              key: ValueKey('timeline-type-$_type'),
              initialValue: _type,
              decoration: InputDecoration(
                labelText: s.tr('timelineFilterType'),
                prefixIcon: const Icon(Icons.tune_rounded),
              ),
              items: [
                DropdownMenuItem(value: 'all', child: Text(s.tr('all'))),
                ..._timelineTypes.map(
                  (type) => DropdownMenuItem(
                    value: type,
                    child: Text(s.tr('type.$type')),
                  ),
                ),
              ],
              onChanged: (value) {
                setState(() => _type = value ?? 'all');
                _load();
              },
            ),
            const SizedBox(height: 18),
            if (_loading && data == null)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 50),
                child: LoadingView(),
              )
            else if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 30),
                child: ErrorState(message: _error!, onRetry: _load),
              )
            else if (data == null || (data.groups.isEmpty && data.undated.isEmpty))
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 40),
                child: EmptyState(
                  icon: Icons.view_timeline_outlined,
                  title: s.tr('timelineEmpty'),
                  body: s.tr('timelineEmptyBody'),
                ),
              )
            else ...[
              ...data.groups.expand(
                (group) => [
                  Padding(
                    padding: const EdgeInsets.only(top: 10, bottom: 10),
                    child: Row(
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: scheme.primary,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          group.label,
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ],
                    ),
                  ),
                  ...group.memories.map(
                    (memory) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: MemoryCard(
                        memory: memory,
                        onTap: () => _openMemory(memory.id),
                      ),
                    ),
                  ),
                ],
              ),
              if (data.undated.isNotEmpty) ...[
                const SizedBox(height: 14),
                SectionHeader(title: s.tr('timelineUndated')),
                ...data.undated.map(
                  (memory) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: MemoryCard(
                      memory: memory,
                      onTap: () => _openMemory(memory.id),
                    ),
                  ),
                ),
              ],
            ],
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
      _load();
    }
  }
}
