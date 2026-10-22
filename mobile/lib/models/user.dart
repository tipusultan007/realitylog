import '../core/json_utils.dart';

class UserSettings {
  const UserSettings({
    required this.interfaceLocale,
    required this.aiLocale,
    required this.timezone,
    required this.country,
    required this.currency,
    required this.dateFormat,
    required this.theme,
    required this.notificationsEnabled,
    required this.emailReminders,
    required this.quietHoursStart,
    required this.quietHoursEnd,
    required this.externalAiConsent,
  });

  factory UserSettings.fromJson(Map<String, dynamic> json) {
    return UserSettings(
      interfaceLocale: asText(json['interface_locale'], 'en'),
      aiLocale: asText(json['ai_locale'], 'en'),
      timezone: asText(json['timezone'], 'Asia/Dhaka'),
      country: asText(json['country'], 'BD'),
      currency: asText(json['currency'], 'BDT'),
      dateFormat: asText(json['date_format'], 'd M Y'),
      theme: asText(json['theme'], 'light'),
      notificationsEnabled: asBool(json['notifications_enabled'], true),
      emailReminders: asBool(json['email_reminders'], true),
      quietHoursStart: asNullableText(json['quiet_hours_start']),
      quietHoursEnd: asNullableText(json['quiet_hours_end']),
      externalAiConsent: asBool(json['external_ai_consent']),
    );
  }

  final String interfaceLocale;
  final String aiLocale;
  final String timezone;
  final String country;
  final String currency;
  final String dateFormat;
  final String theme;
  final bool notificationsEnabled;
  final bool emailReminders;
  final String? quietHoursStart;
  final String? quietHoursEnd;
  final bool externalAiConsent;

  UserSettings copyWith({
    String? interfaceLocale,
    String? aiLocale,
    String? timezone,
    String? country,
    String? currency,
    String? dateFormat,
    String? theme,
    bool? notificationsEnabled,
    bool? emailReminders,
    String? quietHoursStart,
    bool? clearQuietHours,
    String? quietHoursEnd,
    bool? externalAiConsent,
  }) {
    return UserSettings(
      interfaceLocale: interfaceLocale ?? this.interfaceLocale,
      aiLocale: aiLocale ?? this.aiLocale,
      timezone: timezone ?? this.timezone,
      country: country ?? this.country,
      currency: currency ?? this.currency,
      dateFormat: dateFormat ?? this.dateFormat,
      theme: theme ?? this.theme,
      notificationsEnabled: notificationsEnabled ?? this.notificationsEnabled,
      emailReminders: emailReminders ?? this.emailReminders,
      quietHoursStart: clearQuietHours == true ? null : (quietHoursStart ?? this.quietHoursStart),
      quietHoursEnd: clearQuietHours == true ? null : (quietHoursEnd ?? this.quietHoursEnd),
      externalAiConsent: externalAiConsent ?? this.externalAiConsent,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'interface_locale': interfaceLocale,
      'ai_locale': aiLocale,
      'timezone': timezone,
      'country': country,
      'currency': currency,
      'date_format': dateFormat,
      'theme': theme,
      'notifications_enabled': notificationsEnabled,
      'email_reminders': emailReminders,
      'quiet_hours_start': quietHoursStart,
      'quiet_hours_end': quietHoursEnd,
      'external_ai_consent': externalAiConsent,
    };
  }
}

class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.initials,
    required this.settings,
    required this.createdAt,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    final settingsJson = json['settings'];
    return User(
      id: asInt(json['id']),
      name: asText(json['name']),
      email: asText(json['email']),
      initials: asText(json['initials']),
      settings: settingsJson is Map ? UserSettings.fromJson(asMap(settingsJson)) : null,
      createdAt: asDate(json['created_at']),
    );
  }

  final int id;
  final String name;
  final String email;
  final String initials;
  final UserSettings? settings;
  final DateTime? createdAt;

  User copyWith({String? name, UserSettings? settings}) {
    return User(
      id: id,
      name: name ?? this.name,
      email: email,
      initials: initials,
      settings: settings ?? this.settings,
      createdAt: createdAt,
    );
  }
}

class AuthSession {
  const AuthSession({
    required this.token,
    required this.expiresAt,
    required this.user,
  });

  factory AuthSession.fromJson(Map<String, dynamic> json) {
    return AuthSession(
      token: asText(json['token']),
      expiresAt: asDate(json['expires_at']),
      user: User.fromJson(asMap(json['user'])),
    );
  }

  final String token;
  final DateTime? expiresAt;
  final User user;
}

class DeviceSession {
  const DeviceSession({
    required this.id,
    required this.name,
    required this.isCurrent,
    required this.createdAt,
    required this.lastUsedAt,
    required this.expiresAt,
  });

  factory DeviceSession.fromJson(Map<String, dynamic> json) {
    return DeviceSession(
      id: asInt(json['id']),
      name: asText(json['name'], 'Android'),
      isCurrent: asBool(json['is_current']),
      createdAt: asDate(json['created_at']),
      lastUsedAt: asDate(json['last_used_at']),
      expiresAt: asDate(json['expires_at']),
    );
  }

  final int id;
  final String name;
  final bool isCurrent;
  final DateTime? createdAt;
  final DateTime? lastUsedAt;
  final DateTime? expiresAt;
}

class TwoFactorStatus {
  const TwoFactorStatus({
    required this.enabled,
    required this.pending,
    required this.recoveryCodesRemaining,
  });

  factory TwoFactorStatus.fromJson(Map<String, dynamic> json) {
    return TwoFactorStatus(
      enabled: asBool(json['enabled']),
      pending: asBool(json['pending']),
      recoveryCodesRemaining: asInt(json['recovery_codes_remaining']),
    );
  }

  final bool enabled;
  final bool pending;
  final int recoveryCodesRemaining;
}

class TwoFactorSetup {
  const TwoFactorSetup({
    required this.secret,
    required this.otpauthUri,
    required this.account,
  });

  factory TwoFactorSetup.fromJson(Map<String, dynamic> json) {
    return TwoFactorSetup(
      secret: asText(json['secret']),
      otpauthUri: asText(json['otpauth_uri']),
      account: asText(json['account']),
    );
  }

  final String secret;
  final String otpauthUri;
  final String account;
}
