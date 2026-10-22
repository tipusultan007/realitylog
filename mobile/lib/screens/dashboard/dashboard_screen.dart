import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../l10n/strings.dart';
import '../../models/memory.dart';
import '../../models/responses.dart';
import '../../widgets/authenticated_image.dart';
import '../../widgets/brand.dart';
import '../../widgets/common.dart';
import '../../widgets/memory_card.dart';
import '../memory/memory_detail_screen.dart';
import '../reminders/reminders_screen.dart';
import '../settings/settings_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.onOpenCapture,
    required this.onOpenSearch,
  });

  final VoidCallback onOpenCapture;
  final ValueChanged<String> onOpenSearch;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Future<DashboardData>? _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    setState(() {
      _future = context.read<AppState>().api.dashboard();
    });
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 20,
        title: Row(
          children: [
            const AppLogo(size: 32),
            const SizedBox(width: 10),
            Text(
              'RealityLog',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontFamily: 'serif',
                    fontWeight: FontWeight.w700,
                  ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: s.tr('remindersTitle'),
            onPressed: _openReminders,
            icon: const Icon(Icons.notifications_none_rounded),
          ),
          Padding(
            padding: const EdgeInsets.only(right: 12, left: 2),
            child: InkWell(
              borderRadius: BorderRadius.circular(999),
              onTap: _openSettings,
              child: CircleAvatar(
                radius: 18,
                backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                child: Text(
                  app.user?.initials.isNotEmpty == true ? app.user!.initials : 'R',
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: Theme.of(context).colorScheme.onPrimaryContainer,
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
            ),
          ),
        ],
      ),
      body: FutureBuilder<DashboardData>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting && !snapshot.hasData) {
            return const LoadingView();
          }

          if (snapshot.hasError) {
            return ErrorState(
              message: describeError(snapshot.error!, s),
              onRetry: _load,
            );
          }

          final data = snapshot.data;
          if (data == null) {
            return ErrorState(message: s.tr('errorUnknown'), onRetry: _load);
          }

          return RefreshIndicator(
            onRefresh: () async => _load(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 100),
              children: [
                Text(
                  data.greeting.isNotEmpty ? data.greeting : s.tr('appName'),
                  style: Theme.of(context).textTheme.headlineLarge,
                ),
                const SizedBox(height: 4),
                Text(
                  s.tr('dashboardSubtitle'),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                ),
                const SizedBox(height: 20),
                GridView.count(
                  crossAxisCount: 2,
                  mainAxisSpacing: 10,
                  crossAxisSpacing: 10,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  childAspectRatio: 1.45,
                  children: [
                    StatCard(
                      label: s.tr('dashboardMemories'),
                      value: '${data.stat('memories')}',
                      icon: Icons.auto_stories_outlined,
                      tone: Theme.of(context).colorScheme.primary,
                    ),
                    StatCard(
                      label: s.tr('dashboardDocuments'),
                      value: '${data.stat('documents')}',
                      icon: Icons.description_outlined,
                      tone: Theme.of(context).colorScheme.secondary,
                    ),
                    StatCard(
                      label: s.tr('dashboardFavorites'),
                      value: '${data.stat('favorites')}',
                      icon: Icons.star_outline_rounded,
                      tone: Theme.of(context).colorScheme.tertiary,
                    ),
                    StatCard(
                      label: s.tr('dashboardReminders'),
                      value: '${data.stat('reminders')}',
                      icon: Icons.notifications_active_outlined,
                      tone: Theme.of(context).colorScheme.primary,
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    InfoPill(
                      label:
                          '${s.tr('dashboardStorage')}: ${humanBytes(data.stat('storage_bytes'))}',
                      icon: Icons.cloud_outlined,
                    ),
                    const SizedBox(width: 8),
                    if (data.stat('processing') > 0)
                      InfoPill(
                        label:
                            '${s.tr('dashboardProcessing')}: ${data.stat('processing')}',
                        icon: Icons.autorenew_rounded,
                        tone: Theme.of(context).colorScheme.tertiary,
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                _QuickCaptureCard(
                  title: s.tr('dashboardQuickCapture'),
                  subtitle: s.tr('captureSubtitle'),
                  onTap: widget.onOpenCapture,
                ),
                if (data.typeBreakdown.isNotEmpty) ...[
                  const SizedBox(height: 24),
                  SectionHeader(title: s.tr('dashboardTypeBreakdown')),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: data.typeBreakdown
                        .map(
                          (type) => Chip(
                            avatar: CircleAvatar(
                              backgroundColor:
                                  Theme.of(context).colorScheme.primaryContainer,
                              child: Text(
                                '${type.total}',
                                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                      fontWeight: FontWeight.w700,
                                    ),
                              ),
                            ),
                            label: Text(
                              context.s.tr('type.${type.type}'),
                            ),
                          ),
                        )
                        .toList(growable: false),
                  ),
                ],
                const SizedBox(height: 24),
                SectionHeader(
                  title: s.tr('dashboardUpcoming'),
                  actionLabel: s.tr('seeAll'),
                  onAction: _openReminders,
                ),
                if (data.upcomingReminders.isEmpty)
                  _SoftEmpty(text: s.tr('dashboardEmptyReminders'))
                else
                  ...data.upcomingReminders.map(
                    (reminder) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _ReminderRow(
                        reminder: reminder,
                        onTap: _openReminders,
                      ),
                    ),
                  ),
                const SizedBox(height: 24),
                SectionHeader(
                  title: s.tr('dashboardRecent'),
                  actionLabel: s.tr('seeAll'),
                  onAction: () => widget.onOpenSearch(''),
                ),
                if (data.recentMemories.isEmpty)
                  _SoftEmpty(
                    text: s.tr('dashboardEmptyRecent'),
                    actionLabel: s.tr('dashboardQuickCapture'),
                    onAction: widget.onOpenCapture,
                  )
                else
                  ...data.recentMemories.map(
                    (memory) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: MemoryCard(
                        memory: memory,
                        onTap: () => _openMemory(memory.id),
                      ),
                    ),
                  ),
                if (data.onThisDay.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  SectionHeader(title: s.tr('dashboardOnThisDay')),
                  SizedBox(
                    height: 236,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: data.onThisDay.length,
                      separatorBuilder: (_, _) => const SizedBox(width: 12),
                      itemBuilder: (context, index) {
                        return SizedBox(
                          width: 280,
                          child: _CompactMemoryCard(
                            memory: data.onThisDay[index],
                            onTap: () => _openMemory(data.onThisDay[index].id),
                          ),
                        );
                      },
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                SectionHeader(title: s.tr('dashboardSearches')),
                if (data.recentSearches.isEmpty)
                  _SoftEmpty(text: s.tr('dashboardNoSearches'))
                else
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: data.recentSearches
                        .map(
                          (query) => ActionChip(
                            avatar: const Icon(Icons.manage_search_rounded, size: 16),
                            label: Text(query),
                            onPressed: () => widget.onOpenSearch(query),
                          ),
                        )
                        .toList(growable: false),
                  ),
              ],
            ),
          );
        },
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

  Future<void> _openReminders() async {
    final app = context.read<AppState>();
    final version = app.dataVersion;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const RemindersScreen()),
    );
    if (mounted && app.dataVersion != version) {
      _load();
    }
  }

  Future<void> _openSettings() async {
    final app = context.read<AppState>();
    final version = app.dataVersion;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const SettingsScreen()),
    );
    if (mounted && app.dataVersion != version) {
      _load();
    }
  }
}

class _QuickCaptureCard extends StatelessWidget {
  const _QuickCaptureCard({
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      color: scheme.primary,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: scheme.onPrimary.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(Icons.add_rounded, color: scheme.onPrimary, size: 26),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: scheme.onPrimary,
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: scheme.onPrimary.withValues(alpha: 0.82),
                          ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.arrow_forward_rounded, color: scheme.onPrimary),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReminderRow extends StatelessWidget {
  const _ReminderRow({required this.reminder, required this.onTap});

  final Reminder reminder;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final app = context.watch<AppState>();
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(22),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: scheme.tertiary.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(Icons.notifications_active_outlined, color: scheme.tertiary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      reminder.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      formatDateTime(reminder.dueAt, app.locale),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                    ),
                  ],
                ),
              ),
              if (reminder.isOverdue)
                InfoPill(label: context.s.tr('remindersOverdueBadge'), tone: scheme.error),
            ],
          ),
        ),
      ),
    );
  }
}

class _CompactMemoryCard extends StatelessWidget {
  const _CompactMemoryCard({required this.memory, required this.onTap});

  final Memory memory;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final scheme = Theme.of(context).colorScheme;
    final cover = memory.cover;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (cover != null && cover.isImage)
              AuthenticatedImage(
                url: app.api.resolveMediaUrl(cover.url),
                headers: app.api.authHeaders,
                height: 108,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
              )
            else
              Container(
                height: 108,
                color: scheme.secondaryContainer,
                alignment: Alignment.center,
                child: Icon(Icons.auto_stories_outlined, color: scheme.onSecondaryContainer),
              ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    memory.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  const SizedBox(height: 6),
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
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SoftEmpty extends StatelessWidget {
  const _SoftEmpty({required this.text, this.actionLabel, this.onAction});

  final String text;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            text,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
          ),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 10),
            TextButton(onPressed: onAction, child: Text(actionLabel!)),
          ],
        ],
      ),
    );
  }
}
