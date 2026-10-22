import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/user.dart';
import 'api_client.dart';
import 'native_bridge.dart';
import 'navigation.dart';

class AppState extends ChangeNotifier {
  AppState({ApiClient? apiClient}) : api = apiClient ?? ApiClient() {
    api.onUnauthorized = _handleUnauthorized;
  }

  static const _tokenKey = 'realitylog_api_token';
  static const _localeKey = 'realitylog_interface_locale';
  static const _themeKey = 'realitylog_theme_mode';

  final ApiClient api;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  SharedPreferences? _prefs;
  User? _user;
  UserSettings? _settings;
  String _locale = 'en';
  ThemeMode _themeMode = ThemeMode.system;
  bool _bootstrapped = false;
  bool _busy = false;
  int _dataVersion = 0;

  User? get user => _user;
  UserSettings? get settings => _settings;
  String get locale => _locale;
  ThemeMode get themeMode => _themeMode;
  bool get bootstrapped => _bootstrapped;
  bool get busy => _busy;
  bool get isSignedIn => _user != null;
  int get dataVersion => _dataVersion;

  Future<void> bootstrap() async {
    _prefs = await SharedPreferences.getInstance();
    _locale = _prefs?.getString(_localeKey) ?? 'en';
    _themeMode = _themeModeFromString(_prefs?.getString(_themeKey));
    api.locale = _locale;
    Intl.defaultLocale = _locale;

    try {
      final storedToken = await _storage.read(key: _tokenKey);
      if (storedToken != null && storedToken.isNotEmpty) {
        api.token = storedToken;
        final currentUser = await api.me();
        _applyUser(currentUser, persistLocale: true);
      }
    } catch (_) {
      api.token = null;
      try {
        await _storage.delete(key: _tokenKey);
      } catch (_) {
        // Secure storage can be unavailable on some platform configurations.
      }
    }

    _bootstrapped = true;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    _setBusy(true);
    try {
      final session = await api.login(
        email: email,
        password: password,
        deviceName: 'RealityLog Android',
      );
      await _acceptSession(session);
    } finally {
      _setBusy(false);
    }
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    _setBusy(true);
    try {
      final session = await api.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
        locale: _locale,
        deviceName: 'RealityLog Android',
      );
      await _acceptSession(session);
    } finally {
      _setBusy(false);
    }
  }

  Future<void> logout() async {
    try {
      await api.logout();
    } catch (_) {
      // A token may already be expired or revoked; local sign-out still applies.
    }
    await _clearSession();
    await const NativeBridge().cancelAllReminders();
  }

  Future<void> completeTwoFactorLogin(String challengeToken, String code) async {
    _setBusy(true);
    try {
      final session = await api.twoFactorLogin(
        challengeToken: challengeToken,
        code: code,
      );
      await _acceptSession(session);
    } finally {
      _setBusy(false);
    }
  }

  Future<void> deleteAccount(String password) async {
    await api.deleteAccount(password: password);
    await _clearSession();
    await const NativeBridge().cancelAllReminders();
  }

  Future<void> setInterfaceLocale(String code, {bool sync = true}) async {
    if (!_supportedLocales.contains(code) || code == _locale) {
      return;
    }

    _locale = code;
    api.locale = code;
    Intl.defaultLocale = code;
    await _prefs?.setString(_localeKey, code);
    notifyListeners();

    if (sync && _user != null && _settings != null && _settings!.interfaceLocale != code) {
      try {
        final updated = await api.updateLanguage(
          interfaceLocale: code,
          aiLocale: _settings!.aiLocale,
          dateFormat: _settings!.dateFormat,
        );
        _settings = updated;
        _user = _user!.copyWith(settings: updated);
        notifyListeners();
      } catch (_) {
        // The interface language is already applied locally.
      }
    }
  }

  Future<void> setAnswerLocale(String code) async {
    if (!_supportedLocales.contains(code) || _settings == null) {
      return;
    }

    final updated = await api.updateLanguage(
      interfaceLocale: _settings!.interfaceLocale,
      aiLocale: code,
      dateFormat: _settings!.dateFormat,
    );
    _settings = updated;
    _user = _user?.copyWith(settings: updated);
    notifyListeners();
  }

  Future<void> setDateFormat(String format) async {
    if (_settings == null) {
      return;
    }

    final updated = await api.updateLanguage(
      interfaceLocale: _settings!.interfaceLocale,
      aiLocale: _settings!.aiLocale,
      dateFormat: format,
    );
    _settings = updated;
    _user = _user?.copyWith(settings: updated);
    notifyListeners();
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    _themeMode = mode;
    await _prefs?.setString(_themeKey, mode.name);
    notifyListeners();
  }

  Future<void> updateProfile({
    required String name,
    required String timezone,
    required String country,
    required String currency,
  }) async {
    final updated = await api.updateProfile(
      name: name,
      timezone: timezone,
      country: country,
      currency: currency,
    );
    _user = updated;
    _settings = updated.settings ?? _settings;
    notifyListeners();
  }

  Future<void> updateNotifications({
    required bool notificationsEnabled,
    required bool emailReminders,
    String? quietHoursStart,
    String? quietHoursEnd,
  }) async {
    final updated = await api.updateNotifications(
      notificationsEnabled: notificationsEnabled,
      emailReminders: emailReminders,
      quietHoursStart: quietHoursStart,
      quietHoursEnd: quietHoursEnd,
    );
    _applySettings(updated);
  }

  Future<void> updatePrivacy({required bool externalAiConsent}) async {
    final updated = await api.updatePrivacy(externalAiConsent: externalAiConsent);
    _applySettings(updated);
  }

  void bumpData() {
    _dataVersion++;
    notifyListeners();
  }

  Future<void> refreshUser() async {
    final updated = await api.me();
    _applyUser(updated);
  }

  Future<void> _acceptSession(AuthSession session) async {
    api.token = session.token;
    try {
      await _storage.write(key: _tokenKey, value: session.token);
    } catch (_) {
      // Keep the in-memory session if secure storage is unavailable.
    }
    _applyUser(session.user, persistLocale: true);
  }

  void _applyUser(User currentUser, {bool persistLocale = false}) {
    _user = currentUser;
    _settings = currentUser.settings;
    final serverLocale = currentUser.settings?.interfaceLocale;
    if (serverLocale != null && _supportedLocales.contains(serverLocale)) {
      _locale = serverLocale;
      api.locale = serverLocale;
      Intl.defaultLocale = serverLocale;
      if (persistLocale) {
        unawaited(_prefs?.setString(_localeKey, serverLocale) ?? Future<void>.value());
      }
    }
    notifyListeners();
  }

  void _applySettings(UserSettings updated) {
    _settings = updated;
    if (_user != null) {
      _user = _user!.copyWith(settings: updated);
    }
    notifyListeners();
  }

  void _handleUnauthorized() {
    if (_user == null && api.token == null) {
      return;
    }
    unawaited(_clearSession());
  }

  Future<void> _clearSession() async {
    api.token = null;
    _user = null;
    _settings = null;
    try {
      await _storage.delete(key: _tokenKey);
    } catch (_) {
      // Nothing else to clear.
    }
    appNavigatorKey.currentState?.popUntil((route) => route.isFirst);
    notifyListeners();
  }

  void _setBusy(bool value) {
    _busy = value;
    notifyListeners();
  }

  ThemeMode _themeModeFromString(String? value) {
    return switch (value) {
      'light' => ThemeMode.light,
      'dark' => ThemeMode.dark,
      _ => ThemeMode.system,
    };
  }

  static const _supportedLocales = {'en', 'bn'};
}
