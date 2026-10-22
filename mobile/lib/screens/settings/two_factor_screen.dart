import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../l10n/strings.dart';
import '../../models/user.dart';
import '../../widgets/common.dart';

class TwoFactorScreen extends StatefulWidget {
  const TwoFactorScreen({super.key});

  @override
  State<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends State<TwoFactorScreen> {
  final _codeController = TextEditingController();

  TwoFactorStatus? _status;
  TwoFactorSetup? _setup;
  List<String> _recoveryCodes = const [];
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final api = context.read<AppState>().api;
      final status = await api.twoFactorStatus();
      TwoFactorSetup? setup;
      if (status.pending && !status.enabled) {
        setup = await api.startTwoFactorSetup();
      }
      if (mounted) {
        setState(() {
          _status = status;
          _setup = setup;
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

  Future<void> _startSetup() async {
    if (_busy) {
      return;
    }

    setState(() => _busy = true);
    try {
      final setup = await context.read<AppState>().api.startTwoFactorSetup();
      if (!mounted) {
        return;
      }
      setState(() {
        _setup = setup;
        _recoveryCodes = const [];
      });
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _confirm() async {
    if (_busy) {
      return;
    }

    final code = _codeController.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(code)) {
      _showMessage(context.s.tr('mfaCodeLabel'));
      return;
    }

    setState(() => _busy = true);
    try {
      final codes = await context.read<AppState>().api.confirmTwoFactor(code);
      if (!mounted) {
        return;
      }
      setState(() {
        _recoveryCodes = codes;
        _setup = null;
        _codeController.clear();
        _status = const TwoFactorStatus(
          enabled: true,
          pending: false,
          recoveryCodesRemaining: 8,
        );
      });
      _showMessage(context.s.tr('mfaEnabledSnack'));
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _disable() async {
    if (_busy) {
      return;
    }

    final controller = TextEditingController();
    final password = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.s.tr('mfaDisable')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(context.s.tr('mfaDisableHint')),
            const SizedBox(height: 14),
            TextField(
              controller: controller,
              autofocus: true,
              obscureText: true,
              decoration: InputDecoration(
                labelText: context.s.tr('mfaPassword'),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: Text(context.s.tr('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(controller.text),
            child: Text(context.s.tr('mfaDisable')),
          ),
        ],
      ),
    );
    controller.dispose();

    if (password == null || password.isEmpty || !mounted) {
      return;
    }

    setState(() => _busy = true);
    try {
      await context.read<AppState>().api.disableTwoFactor(password);
      if (!mounted) {
        return;
      }
      setState(() {
        _status = const TwoFactorStatus(
          enabled: false,
          pending: false,
          recoveryCodesRemaining: 0,
        );
        _setup = null;
        _recoveryCodes = const [];
        _codeController.clear();
      });
      _showMessage(context.s.tr('mfaDisabledSnack'));
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _copy(String text, String message) async {
    await Clipboard.setData(ClipboardData(text: text));
    if (mounted) {
      _showMessage(message);
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
      appBar: AppBar(title: Text(s.tr('mfaTitle'))),
      body: _loading && _status == null
          ? const LoadingView()
          : _error != null && _status == null
              ? ErrorState(message: _error!, onRetry: _load)
              : ListView(
                  padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
                  children: [
                    Text(
                      s.tr('mfaSubtitle'),
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Theme.of(context).colorScheme.onSurfaceVariant,
                          ),
                    ),
                    const SizedBox(height: 16),
                    _buildStatusCard(context),
                    if (_recoveryCodes.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      _buildRecoveryCodes(context),
                    ] else if (_setup != null) ...[
                      const SizedBox(height: 16),
                      _buildSetup(context, _setup!),
                    ] else if (_status?.enabled == true) ...[
                      const SizedBox(height: 16),
                      _buildDisableCard(context),
                    ] else ...[
                      const SizedBox(height: 16),
                      FilledButton.icon(
                        onPressed: _busy ? null : _startSetup,
                        icon: const Icon(Icons.shield_outlined),
                        label: Text(s.tr('mfaEnable')),
                      ),
                    ],
                  ],
                ),
    );
  }

  Widget _buildStatusCard(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final enabled = _status?.enabled ?? false;
    final remaining = _status?.recoveryCodesRemaining ?? 0;

    return Card(
      child: ListTile(
        leading: Icon(
          enabled ? Icons.verified_user_rounded : Icons.shield_outlined,
          color: enabled ? scheme.primary : scheme.onSurfaceVariant,
        ),
        title: Text(s.tr('mfaTitle')),
        subtitle: enabled
            ? Text(s.tr('mfaRecoveryRemaining', {'count': remaining}))
            : Text(s.tr('mfaNotEnabled')),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: enabled ? scheme.primaryContainer : scheme.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            enabled ? s.tr('mfaEnabled') : s.tr('mfaNotEnabled'),
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: enabled ? scheme.onPrimaryContainer : scheme.onSurfaceVariant,
                  fontWeight: FontWeight.w700,
                ),
          ),
        ),
      ),
    );
  }

  Widget _buildSetup(BuildContext context, TwoFactorSetup setup) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              s.tr('mfaSecretHint'),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSurfaceVariant,
                    height: 1.4,
                  ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: scheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: SelectableText(
                      setup.secret,
                      style: const TextStyle(
                        fontFamily: 'monospace',
                        fontSize: 16,
                        letterSpacing: 1.4,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: s.tr('mfaSecret'),
                    onPressed: () => _copy(setup.secret, s.tr('mfaSecretCopied')),
                    icon: const Icon(Icons.copy_rounded, size: 18),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 4),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(s.tr('mfaAdvanced')),
              children: [
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        s.tr('mfaUriHint'),
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                            ),
                      ),
                      const SizedBox(height: 8),
                      SelectableText(
                        setup.otpauthUri,
                        style: const TextStyle(fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _codeController,
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.done,
              maxLength: 6,
              decoration: InputDecoration(
                labelText: s.tr('mfaCodeLabel'),
                prefixIcon: const Icon(Icons.pin_outlined),
                counterText: '',
              ),
              onSubmitted: (_) => _confirm(),
            ),
            const SizedBox(height: 10),
            FilledButton(
              onPressed: _busy ? null : _confirm,
              child: _busy
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2.2),
                    )
                  : Text(s.tr('mfaConfirm')),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRecoveryCodes(BuildContext context) {
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;

    return Card(
      color: scheme.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(Icons.key_rounded, color: scheme.onSecondaryContainer),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    s.tr('mfaRecoveryTitle'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: scheme.onSecondaryContainer,
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              s.tr('mfaRecoveryHint'),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: scheme.onSecondaryContainer,
                    height: 1.4,
                  ),
            ),
            const SizedBox(height: 12),
            for (final code in _recoveryCodes)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: SelectableText(
                  code,
                  style: TextStyle(
                    fontFamily: 'monospace',
                    fontSize: 15,
                    letterSpacing: 1.2,
                    color: scheme.onSecondaryContainer,
                  ),
                ),
              ),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: () => _copy(
                  _recoveryCodes.join('\n'),
                  s.tr('mfaCodesCopied'),
                ),
                icon: const Icon(Icons.copy_rounded, size: 18),
                label: Text(s.tr('mfaCopyCodes')),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDisableCard(BuildContext context) {
    final s = context.s;

    return Card(
      child: ListTile(
        leading: const Icon(Icons.gpp_bad_outlined),
        title: Text(s.tr('mfaDisable')),
        subtitle: Text(s.tr('mfaDisableHint')),
        trailing: const Icon(Icons.chevron_right_rounded),
        onTap: _busy ? null : _disable,
      ),
    );
  }
}
