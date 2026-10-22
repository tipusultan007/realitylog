import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/app_state.dart';
import '../../core/native_bridge.dart';
import '../../core/reminder_notifications.dart';
import '../../l10n/strings.dart';
import '../capture/capture_screen.dart';
import '../dashboard/dashboard_screen.dart';
import '../library/library_screen.dart';
import '../search/search_screen.dart';
import '../timeline/timeline_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> with WidgetsBindingObserver {
  static const _bridge = NativeBridge();

  int _index = 0;
  String? _searchSeed;
  SharedContent? _sharedContent;
  int _captureSeed = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _checkSharedContent();
      _syncReminderNotifications();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkSharedContent();
      _syncReminderNotifications();
    }
  }

  Future<void> _syncReminderNotifications() async {
    try {
      final data = await context.read<AppState>().api.reminders();
      if (!mounted) {
        return;
      }
      await syncReminderNotifications(
        [...data.overdue, ...data.upcoming],
        enabled: context.read<AppState>().settings?.notificationsEnabled ?? true,
      );
    } catch (_) {
      // Local alarms keep their last known schedule when the server is offline.
    }
  }

  Future<void> _checkSharedContent() async {
    final shared = await _bridge.takeSharedContent();
    if (shared == null || !mounted) {
      return;
    }
    setState(() {
      _sharedContent = shared;
      _captureSeed++;
      _index = 1;
    });
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;

    return Scaffold(
      body: switch (_index) {
        0 => DashboardScreen(
            onOpenCapture: () => setState(() => _index = 1),
            onOpenSearch: _openSearch,
          ),
        1 => CaptureScreen(
            key: ValueKey('capture-$_captureSeed'),
            sharedContent: _sharedContent,
          ),
        2 => SearchScreen(
            key: ValueKey('search-${_searchSeed ?? 'blank'}'),
            initialQuery: _searchSeed,
          ),
        3 => const TimelineScreen(),
        _ => const LibraryScreen(),
      },
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (index) {
          setState(() {
            _index = index;
            if (index != 1) {
              _sharedContent = null;
            }
            if (index == 2 && _searchSeed != null) {
              _searchSeed = null;
            }
          });
        },
        destinations: [
          NavigationDestination(
            icon: const Icon(Icons.home_outlined),
            selectedIcon: const Icon(Icons.home_rounded),
            label: s.tr('navHome'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.add_circle_outline_rounded),
            selectedIcon: const Icon(Icons.add_circle_rounded),
            label: s.tr('navCapture'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.manage_search_outlined),
            selectedIcon: const Icon(Icons.manage_search_rounded),
            label: s.tr('navRecall'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.view_timeline_outlined),
            selectedIcon: const Icon(Icons.view_timeline_rounded),
            label: s.tr('navTimeline'),
          ),
          NavigationDestination(
            icon: const Icon(Icons.collections_bookmark_outlined),
            selectedIcon: const Icon(Icons.collections_bookmark_rounded),
            label: s.tr('navLibrary'),
          ),
        ],
      ),
      floatingActionButton: _index == 0
          ? FloatingActionButton.extended(
              onPressed: () => setState(() => _index = 1),
              icon: const Icon(Icons.add_rounded),
              label: Text(app.user == null ? s.tr('navCapture') : s.tr('captureTitle')),
            )
          : null,
    );
  }

  void _openSearch(String query) {
    setState(() {
      _searchSeed = query;
      _index = 2;
    });
  }
}
