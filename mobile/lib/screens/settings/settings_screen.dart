import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';

import '../../core/api_exception.dart';
import '../../core/app_state.dart';
import '../../core/errors.dart';
import '../../core/formatters.dart';
import '../../core/native_bridge.dart';
import '../../core/reminder_notifications.dart';
import '../../l10n/strings.dart';
import '../../models/user.dart';
import '../../widgets/language_toggle.dart';
import '../collections/collections_screen.dart';
import 'help_screen.dart';
import 'privacy_screen.dart';
import 'sessions_screen.dart';
import 'two_factor_screen.dart';

const _dateFormats = ['d M Y', 'j F Y', 'd/m/Y', 'Y-m-d'];

const _timezones = [
  'Asia/Dhaka',
  'Asia/Kolkata',
  'Asia/Karachi',
  'Asia/Dubai',
  'Asia/Singapore',
  'Europe/London',
  'America/New_York',
  'America/Los_Angeles',
  'UTC',
];

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  late final TextEditingController _nameController;
  late final TextEditingController _timezoneController;
  late final TextEditingController _countryController;
  late final TextEditingController _currencyController;

  bool _savingProfile = false;
  bool _savingNotifications = false;
  bool _savingPrivacy = false;
  bool _exporting = false;
  bool _notificationsEnabled = true;
  bool _emailReminders = true;
  TimeOfDay? _quietStart;
  TimeOfDay? _quietEnd;

  @override
  void initState() {
    super.initState();
    final app = context.read<AppState>();
    final user = app.user;
    final settings = app.settings;
    _nameController = TextEditingController(text: user?.name ?? '');
    _timezoneController = TextEditingController(text: settings?.timezone ?? 'Asia/Dhaka');
    _countryController = TextEditingController(text: settings?.country ?? 'BD');
    _currencyController = TextEditingController(text: settings?.currency ?? 'BDT');
    _notificationsEnabled = settings?.notificationsEnabled ?? true;
    _emailReminders = settings?.emailReminders ?? true;
    _quietStart = _parseTime(settings?.quietHoursStart);
    _quietEnd = _parseTime(settings?.quietHoursEnd);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _timezoneController.dispose();
    _countryController.dispose();
    _currencyController.dispose();
    super.dispose();
  }

  TimeOfDay? _parseTime(String? value) {
    if (value == null || value.isEmpty) {
      return null;
    }
    final parts = value.split(':');
    if (parts.length < 2) {
      return null;
    }
    final hour = int.tryParse(parts[0]);
    final minute = int.tryParse(parts[1]);
    if (hour == null || minute == null) {
      return null;
    }
    return TimeOfDay(hour: hour.clamp(0, 23), minute: minute.clamp(0, 59));
  }

  String? _timeValue(TimeOfDay? value) {
    if (value == null) {
      return null;
    }
    return '${value.hour.toString().padLeft(2, '0')}:'
        '${value.minute.toString().padLeft(2, '0')}';
  }

  Future<void> _saveProfile() async {
    FocusScope.of(context).unfocus();
    if (_nameController.text.trim().isEmpty) {
      _showMessage(context.s.tr('authNameRequired'));
      return;
    }

    setState(() => _savingProfile = true);
    try {
      await context.read<AppState>().updateProfile(
            name: _nameController.text.trim(),
            timezone: _timezoneController.text.trim(),
            country: _countryController.text.trim().toUpperCase(),
            currency: _currencyController.text.trim().toUpperCase(),
          );
      if (mounted) {
        _showMessage(context.s.tr('settingsProfileSaved'));
      }
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _savingProfile = false);
      }
    }
  }

  Future<void> _changeInterfaceLocale(String code) async {
    try {
      await context.read<AppState>().setInterfaceLocale(code);
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    }
  }

  Future<void> _changeAnswerLocale(String code) async {
    try {
      await context.read<AppState>().setAnswerLocale(code);
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    }
  }

  Future<void> _changeDateFormat(String format) async {
    try {
      await context.read<AppState>().setDateFormat(format);
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    }
  }

  Future<void> _persistNotifications() async {
    if (_savingNotifications) {
      return;
    }
    final app = context.read<AppState>();
    final wasEnabled = app.settings?.notificationsEnabled ?? true;
    final enabled = _notificationsEnabled;
    setState(() => _savingNotifications = true);
    try {
      await app.updateNotifications(
            notificationsEnabled: enabled,
            emailReminders: _emailReminders,
            quietHoursStart: _timeValue(_quietStart),
            quietHoursEnd: _timeValue(_quietEnd),
          );
      if (!mounted || enabled == wasEnabled) {
        return;
      }
      if (!enabled) {
        await const NativeBridge().cancelAllReminders();
        return;
      }
      final granted = await const NativeBridge().requestNotificationPermission();
      final reminders = await app.api.reminders();
      if (!mounted) {
        return;
      }
      await syncReminderNotifications([...reminders.overdue, ...reminders.upcoming]);
      if (!granted && mounted) {
        _showMessage(context.s.tr('settingsNotificationPermission'));
      }
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _savingNotifications = false);
      }
    }
  }

  Future<void> _toggleQuietHours(bool enabled) async {
    setState(() {
      if (enabled) {
        _quietStart = const TimeOfDay(hour: 22, minute: 0);
        _quietEnd = const TimeOfDay(hour: 7, minute: 0);
      } else {
        _quietStart = null;
        _quietEnd = null;
      }
    });
    await _persistNotifications();
  }

  Future<void> _pickQuietTime({required bool start}) async {
    final selected = await showTimePicker(
      context: context,
      initialTime: start
          ? (_quietStart ?? const TimeOfDay(hour: 22, minute: 0))
          : (_quietEnd ?? const TimeOfDay(hour: 7, minute: 0)),
    );
    if (selected != null && mounted) {
      setState(() {
        if (start) {
          _quietStart = selected;
        } else {
          _quietEnd = selected;
        }
      });
      await _persistNotifications();
    }
  }

  Future<void> _setPrivacy(bool value) async {
    setState(() => _savingPrivacy = true);
    try {
      await context.read<AppState>().updatePrivacy(externalAiConsent: value);
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    } finally {
      if (mounted) {
        setState(() => _savingPrivacy = false);
      }
    }
  }

  Future<void> _exportArchive() async {
    final s = context.s;
    setState(() => _exporting = true);
    try {
      final app = context.read<AppState>();
      final archive = await app.api.exportArchive();
      final directory = await getTemporaryDirectory();
      final now = DateTime.now();
      final name = 'realitylog-export-'
          '${now.year}${now.month.toString().padLeft(2, '0')}${now.day.toString().padLeft(2, '0')}.json';
      final file = File('${directory.path}/$name');
      await file.writeAsString(
        const JsonEncoder.withIndent('  ').convert(archive),
        flush: true,
      );
      final result = await OpenFilex.open(file.path);
      if (mounted) {
        _showMessage(
          result.type == ResultType.done
              ? s.tr('settingsExportSaved')
              : '${s.tr('settingsExportSaved')}\n${file.path}',
        );
      }
    } catch (error) {
      if (mounted) {
        _showMessage(
          error is ApiException ? error.detail : s.tr('settingsExportError'),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _exporting = false);
      }
    }
  }

  Future<void> _showDeleteAccountDialog() async {
    final passwordController = TextEditingController();
    final confirmationController = TextEditingController();
    String? localError;

    final credentials = await showDialog<(String, String)?>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: Text(context.s.tr('settingsDeleteAccount')),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(context.s.tr('settingsDeleteAccountBody')),
                    const SizedBox(height: 14),
                    TextField(
                      controller: passwordController,
                      obscureText: true,
                      decoration: InputDecoration(
                        labelText: context.s.tr('settingsDeletePassword'),
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: confirmationController,
                      autocorrect: false,
                      decoration: InputDecoration(
                        labelText: context.s.tr('settingsTypeDelete'),
                      ),
                    ),
                    if (localError != null) ...[
                      const SizedBox(height: 10),
                      Text(
                        localError!,
                        style: TextStyle(color: Theme.of(context).colorScheme.error),
                      ),
                    ],
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(dialogContext).pop(),
                  child: Text(context.s.tr('cancel')),
                ),
                FilledButton(
                  onPressed: () {
                    if (passwordController.text.isEmpty) {
                      setDialogState(() {
                        localError = context.s.tr('authPasswordRequired');
                      });
                      return;
                    }
                    if (confirmationController.text.trim() != 'DELETE') {
                      setDialogState(() {
                        localError = context.s.tr('settingsTypeDelete');
                      });
                      return;
                    }
                    Navigator.of(dialogContext).pop(
                      (passwordController.text, confirmationController.text),
                    );
                  },
                  child: Text(context.s.tr('settingsDeleteConfirmButton')),
                ),
              ],
            );
          },
        );
      },
    );

    passwordController.dispose();
    confirmationController.dispose();

    if (credentials == null || !mounted) {
      return;
    }

    try {
      await context.read<AppState>().deleteAccount(credentials.$1);
      if (mounted) {
        _showMessage(context.s.tr('settingsAccountDeleted'));
      }
    } catch (error) {
      if (mounted) {
        _showMessage(describeError(error, context.s));
      }
    }
  }

  Future<void> _logout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.s.tr('settingsLogoutConfirm')),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.s.tr('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(context.s.tr('settingsLogout')),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      await context.read<AppState>().logout();
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final settings = app.settings ?? _fallbackSettings;
    final user = app.user;

    return Scaffold(
      appBar: AppBar(title: Text(s.tr('settingsTitle'))),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 36),
        children: [
          _ProfileHeader(name: user?.name ?? '', email: user?.email ?? '', initials: user?.initials ?? 'R'),
          const SizedBox(height: 22),
          _SectionTitle(title: s.tr('settingsProfile')),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  TextField(
                    controller: _nameController,
                    textCapitalization: TextCapitalization.words,
                    decoration: InputDecoration(
                      labelText: s.tr('settingsName'),
                      prefixIcon: const Icon(Icons.person_outline),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    enabled: false,
                    initialValue: user?.email ?? '',
                    decoration: InputDecoration(
                      labelText: s.tr('settingsEmail'),
                      prefixIcon: const Icon(Icons.alternate_email_rounded),
                    ),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    key: ValueKey('timezone-${_timezoneController.text}'),
                    initialValue: _timezones.contains(_timezoneController.text)
                        ? _timezoneController.text
                        : _timezones.first,
                    decoration: InputDecoration(
                      labelText: s.tr('settingsTimezone'),
                      prefixIcon: const Icon(Icons.public_rounded),
                    ),
                    items: _timezones
                        .map(
                          (timezone) => DropdownMenuItem(
                            value: timezone,
                            child: Text(timezone),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: (value) {
                      if (value != null) {
                        _timezoneController.text = value;
                      }
                    },
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _countryController,
                          textCapitalization: TextCapitalization.characters,
                          decoration: InputDecoration(
                            labelText: s.tr('settingsCountry'),
                            prefixIcon: const Icon(Icons.flag_outlined),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: _currencyController,
                          textCapitalization: TextCapitalization.characters,
                          decoration: InputDecoration(
                            labelText: s.tr('settingsCurrency'),
                            prefixIcon: const Icon(Icons.payments_outlined),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: _savingProfile ? null : _saveProfile,
                      child: _savingProfile
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2.2),
                            )
                          : Text(s.tr('save')),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(title: s.tr('authLanguage')),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          s.tr('settingsInterfaceLanguage'),
                          style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ),
                      LanguageToggle(
                        locale: app.locale,
                        onChanged: _changeInterfaceLocale,
                        compact: true,
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    s.tr('settingsLanguageHint'),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    s.tr('settingsAnswerLanguage'),
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
                    selected: {settings.aiLocale},
                    onSelectionChanged: (values) => _changeAnswerLocale(values.first),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    s.tr('settingsAnswerHint'),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    key: ValueKey('date-format-${settings.dateFormat}'),
                    initialValue: _dateFormats.contains(settings.dateFormat)
                        ? settings.dateFormat
                        : _dateFormats.first,
                    decoration: InputDecoration(
                      labelText: s.tr('settingsDateFormat'),
                      prefixIcon: const Icon(Icons.date_range_outlined),
                    ),
                    items: _dateFormats
                        .map(
                          (format) => DropdownMenuItem(
                            value: format,
                            child: Text(
                              formatDate(DateTime.now(), format, app.locale),
                            ),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: (value) {
                      if (value != null) {
                        _changeDateFormat(value);
                      }
                    },
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(title: s.tr('settingsTheme')),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: SegmentedButton<ThemeMode>(
                showSelectedIcon: false,
                segments: [
                  ButtonSegment(
                    value: ThemeMode.light,
                    label: Text(s.tr('settingsThemeLight')),
                    icon: const Icon(Icons.light_mode_outlined, size: 17),
                  ),
                  ButtonSegment(
                    value: ThemeMode.dark,
                    label: Text(s.tr('settingsThemeDark')),
                    icon: const Icon(Icons.dark_mode_outlined, size: 17),
                  ),
                  ButtonSegment(
                    value: ThemeMode.system,
                    label: Text(s.tr('settingsThemeSystem')),
                    icon: const Icon(Icons.phone_android_rounded, size: 17),
                  ),
                ],
                selected: {app.themeMode},
                onSelectionChanged: (values) => app.setThemeMode(values.first),
              ),
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(title: s.tr('settingsNotifications')),
          Card(
            child: Column(
              children: [
                SwitchListTile(
                  value: _notificationsEnabled,
                  onChanged: _savingNotifications
                      ? null
                      : (value) {
                          setState(() => _notificationsEnabled = value);
                          _persistNotifications();
                        },
                  title: Text(s.tr('settingsNotificationsEnabled')),
                  secondary: const Icon(Icons.notifications_active_outlined),
                ),
                const Divider(height: 1),
                SwitchListTile(
                  value: _emailReminders,
                  onChanged: _savingNotifications
                      ? null
                      : (value) {
                          setState(() => _emailReminders = value);
                          _persistNotifications();
                        },
                  title: Text(s.tr('settingsEmailReminders')),
                  secondary: const Icon(Icons.mail_outline_rounded),
                ),
                const Divider(height: 1),
                SwitchListTile(
                  value: _quietStart != null && _quietEnd != null,
                  onChanged: _savingNotifications ? null : _toggleQuietHours,
                  title: Text(s.tr('settingsQuietHours')),
                  secondary: const Icon(Icons.bedtime_outlined),
                ),
                if (_quietStart != null && _quietEnd != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                    child: Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _savingNotifications
                                ? null
                                : () => _pickQuietTime(start: true),
                            icon: const Icon(Icons.schedule_rounded, size: 17),
                            label: Text(
                              '${s.tr('settingsQuietStart')}: ${_quietStart!.format(context)}',
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _savingNotifications
                                ? null
                                : () => _pickQuietTime(start: false),
                            icon: const Icon(Icons.schedule_rounded, size: 17),
                            label: Text(
                              '${s.tr('settingsQuietEnd')}: ${_quietEnd!.format(context)}',
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                if (_savingNotifications)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 8),
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(title: s.tr('settingsPrivacy')),
          Card(
            child: Column(
              children: [
                SwitchListTile(
                  value: settings.externalAiConsent,
                  onChanged: _savingPrivacy ? null : _setPrivacy,
                  title: Text(s.tr('settingsExternalAi')),
                  subtitle: Text(s.tr('settingsExternalAiBody')),
                  secondary: const Icon(Icons.psychology_outlined),
                ),
                ListTile(
                  leading: const Icon(Icons.privacy_tip_outlined),
                  title: Text(s.tr('settingsPrivacyPolicy')),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const PrivacyScreen()),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(title: s.tr('settingsSecurity')),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.devices_rounded),
                  title: Text(s.tr('settingsSessions')),
                  subtitle: Text(s.tr('settingsSessionsBody')),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const SessionsScreen()),
                  ),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.shield_outlined),
                  title: Text(s.tr('settingsTwoFactor')),
                  subtitle: Text(s.tr('settingsTwoFactorBody')),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const TwoFactorScreen()),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(title: s.tr('settingsData')),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.folder_copy_outlined),
                  title: Text(s.tr('settingsCollections')),
                  subtitle: Text(s.tr('settingsCollectionsBody')),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const CollectionsScreen()),
                  ),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.download_outlined),
                  title: Text(s.tr('settingsExport')),
                  trailing: _exporting
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.chevron_right_rounded),
                  onTap: _exporting ? null : _exportArchive,
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.logout_rounded),
                  title: Text(s.tr('settingsLogout')),
                  onTap: _logout,
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(title: s.tr('settingsDangerZone')),
          Card(
            child: ListTile(
              leading: Icon(Icons.delete_forever_outlined, color: scheme.error),
              title: Text(
                s.tr('settingsDeleteAccount'),
                style: TextStyle(color: scheme.error, fontWeight: FontWeight.w700),
              ),
              subtitle: Text(s.tr('settingsDeleteAccountBody')),
              onTap: _showDeleteAccountDialog,
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(title: s.tr('settingsAbout')),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.help_outline_rounded),
                  title: Text(s.tr('settingsHelp')),
                  subtitle: Text(s.tr('settingsHelpBody')),
                  trailing: const Icon(Icons.chevron_right_rounded),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const HelpScreen()),
                  ),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.info_outline_rounded),
                  title: Text('RealityLog'),
                  subtitle: Text(s.tr('settingsVersion')),
                ),
                ListTile(
                  leading: const Icon(Icons.mail_outline_rounded),
                  title: Text(s.tr('privacyContact')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Text(
        title,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
            ),
      ),
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({
    required this.name,
    required this.email,
    required this.initials,
  });

  final String name;
  final String email;
  final String initials;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      children: [
        CircleAvatar(
          radius: 30,
          backgroundColor: scheme.primaryContainer,
          child: Text(
            initials.isNotEmpty ? initials : 'R',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: scheme.onPrimaryContainer,
                  fontWeight: FontWeight.w800,
                ),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 2),
              Text(
                email,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

const _fallbackSettings = UserSettings(
  interfaceLocale: 'en',
  aiLocale: 'en',
  timezone: 'Asia/Dhaka',
  country: 'BD',
  currency: 'BDT',
  dateFormat: 'd M Y',
  theme: 'system',
  notificationsEnabled: true,
  emailReminders: true,
  quietHoursStart: null,
  quietHoursEnd: null,
  externalAiConsent: false,
);
