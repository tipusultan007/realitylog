import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../l10n/strings.dart';
import '../../models/user.dart';
import '../../widgets/common.dart';

class SessionsScreen extends StatefulWidget {
  const SessionsScreen({super.key});

  @override
  State<SessionsScreen> createState() => _SessionsScreenState();
}

class _SessionsScreenState extends State<SessionsScreen> {
  List<DeviceSession> _sessions = const [];
  bool _loading = true;
  bool _revoking = false;
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
      final sessions = await context.read<AppState>().api.sessions();
      if (mounted) {
        setState(() {
          _sessions = sessions;
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

  Future<bool> _confirm(String title, String body) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.s.tr('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(context.s.tr('sessionsRevoke')),
          ),
        ],
      ),
    );

    return confirmed == true;
  }

  Future<void> _revoke(DeviceSession session) async {
    if (_revoking) {
      return;
    }

    if (!await _confirm(
      context.s.tr('sessionsRevokeConfirmTitle'),
      context.s.tr('sessionsRevokeConfirmBody'),
    )) {
      return;
    }

    if (!mounted) {
      return;
    }

    setState(() => _revoking = true);
    try {
      final wasCurrent = await context.read<AppState>().api.revokeSession(session.id);
      if (!mounted) {
        return;
      }
      if (wasCurrent) {
        await context.read<AppState>().logout();
        if (mounted) {
          Navigator.of(context).popUntil((route) => route.isFirst);
        }
        return;
      }
      _showMessage(context.s.tr('sessionsRevoked'));
      await _load();
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _revoking = false);
      }
    }
  }

  Future<void> _revokeOthers() async {
    if (_revoking) {
      return;
    }

    if (!await _confirm(
      context.s.tr('sessionsRevokeOthersConfirm'),
      context.s.tr('sessionsRevokeOthersBody'),
    )) {
      return;
    }

    if (!mounted) {
      return;
    }

    setState(() => _revoking = true);
    try {
      await context.read<AppState>().api.revokeOtherSessions();
      if (!mounted) {
        return;
      }
      _showMessage(context.s.tr('sessionsRevokedOthers'));
      await _load();
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _revoking = false);
      }
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
    final locale = context.watch<AppState>().locale;
    final others = _sessions.where((session) => !session.isCurrent).length;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.tr('sessionsTitle')),
        actions: [
          IconButton(
            tooltip: s.tr('retry'),
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: _loading && _sessions.isEmpty
          ? const LoadingView()
          : _error != null
              ? ErrorState(message: _error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
                    children: [
                      Text(
                        s.tr('sessionsSubtitle'),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: Theme.of(context).colorScheme.onSurfaceVariant,
                            ),
                      ),
                      const SizedBox(height: 14),
                      for (final session in _sessions) ...[
                        _SessionCard(
                          session: session,
                          locale: locale,
                          busy: _revoking,
                          onRevoke: () => _revoke(session),
                        ),
                        const SizedBox(height: 12),
                      ],
                      const SizedBox(height: 6),
                      Card(
                        child: ListTile(
                          enabled: others > 0 && !_revoking,
                          leading: const Icon(Icons.phonelink_off_rounded),
                          title: Text(s.tr('sessionsRevokeOthers')),
                          subtitle: Text(
                            others > 0
                                ? s.tr('sessionsRevokeOthersBody')
                                : s.tr('sessionsNoOthers'),
                          ),
                          trailing: const Icon(Icons.chevron_right_rounded),
                          onTap: others > 0 && !_revoking ? _revokeOthers : null,
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
}

class _SessionCard extends StatelessWidget {
  const _SessionCard({
    required this.session,
    required this.locale,
    required this.busy,
    required this.onRevoke,
  });

  final DeviceSession session;
  final String locale;
  final bool busy;
  final VoidCallback onRevoke;

  @override
  Widget build(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final lastUsed = session.lastUsedAt != null
        ? formatDateTime(session.lastUsedAt, locale)
        : s.tr('sessionsNeverUsed');
    final expires = session.expiresAt != null
        ? formatDateTime(session.expiresAt, locale)
        : s.tr('sessionsNeverExpires');

    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  session.isCurrent
                      ? Icons.phone_android_rounded
                      : Icons.devices_other_rounded,
                  color: session.isCurrent ? scheme.primary : scheme.onSurfaceVariant,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    session.name,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ),
                if (session.isCurrent)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: scheme.primaryContainer,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      s.tr('sessionsCurrent'),
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                            color: scheme.onPrimaryContainer,
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              '${s.tr('sessionsLastUsed')}: $lastUsed',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            Text(
              '${s.tr('sessionsExpires')}: $expires',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
            ),
            if (!session.isCurrent) ...[
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: busy ? null : onRevoke,
                  icon: const Icon(Icons.logout_rounded, size: 18),
                  label: Text(s.tr('sessionsRevoke')),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
