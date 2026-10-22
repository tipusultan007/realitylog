import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../models/memory.dart';
import '../models/responses.dart';
import '../models/user.dart';
import 'api_exception.dart';
import 'formatters.dart';
import 'json_utils.dart';

class ApiClient {
  ApiClient({String? baseUrl})
      : baseUrl = (baseUrl ??
                const String.fromEnvironment(
                  'API_BASE_URL',
                  defaultValue: 'http://10.0.2.2:8000/api/v1',
                ))
            .replaceAll(RegExp(r'/+$'), '');

  final String baseUrl;
  String? token;
  String locale = 'en';
  void Function()? onUnauthorized;

  Uri get origin {
    final parsed = Uri.parse(baseUrl);
    return parsed.replace(path: '', query: null, fragment: null);
  }

  Uri apiUri(String path, [Map<String, String>? query]) {
    final base = Uri.parse(baseUrl);
    final normalized = path.startsWith('/') ? path : '/$path';
    return base.replace(
      path: '${base.path}$normalized',
      queryParameters: query == null || query.isEmpty ? null : query,
    );
  }

  Uri mediaUri(String path) {
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return Uri.parse(path);
    }
    if (path.startsWith('/')) {
      return origin.replace(path: path);
    }
    return apiUri(path);
  }

  String resolveMediaUrl(String path) => mediaUri(path).toString();

  Map<String, String> get authHeaders {
    return {
      'Accept': 'application/json',
      'X-Locale': locale,
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Future<AuthSession> login({
    required String email,
    required String password,
    String? deviceName,
  }) async {
    final body = <String, dynamic>{
      'email': email,
      'password': password,
    };
    if (deviceName != null) {
      body['device_name'] = deviceName;
    }
    final data = await _sendJson(
      'POST',
      '/login',
      body: body,
      authenticated: false,
    );
    final json = asMap(data);
    if (asBool(json['two_factor_required'])) {
      throw TwoFactorChallengeRequired(
        challengeToken: asText(json['challenge_token']),
        expiresAt: asDate(json['challenge_expires_at']),
        message: asText(json['message']),
      );
    }
    return AuthSession.fromJson(json);
  }

  Future<AuthSession> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    required String locale,
    String? deviceName,
  }) async {
    final body = <String, dynamic>{
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'locale': locale,
    };
    if (deviceName != null) {
      body['device_name'] = deviceName;
    }
    final data = await _sendJson(
      'POST',
      '/register',
      body: body,
      authenticated: false,
    );
    return AuthSession.fromJson(asMap(data));
  }

  Future<User> me() async {
    final data = await _sendJson('GET', '/me');
    return User.fromJson(asMap(asMap(data)['data']));
  }

  Future<void> logout() async {
    await _sendJson('POST', '/logout');
  }

  Future<List<DeviceSession>> sessions() async {
    final data = await _sendJson('GET', '/sessions');
    return asMaps(asMap(data)['data']).map(DeviceSession.fromJson).toList(growable: false);
  }

  Future<bool> revokeSession(int id) async {
    final data = await _sendJson('DELETE', '/sessions/$id');
    return asBool(asMap(data)['revoked_current']);
  }

  Future<int> revokeOtherSessions() async {
    final data = await _sendJson('DELETE', '/sessions');
    return asInt(asMap(data)['revoked']);
  }

  Future<TwoFactorStatus> twoFactorStatus() async {
    final data = await _sendJson('GET', '/two-factor');
    return TwoFactorStatus.fromJson(asMap(asMap(data)['data']));
  }

  Future<TwoFactorSetup> startTwoFactorSetup() async {
    final data = await _sendJson('POST', '/two-factor/setup');
    return TwoFactorSetup.fromJson(asMap(asMap(data)['data']));
  }

  Future<List<String>> confirmTwoFactor(String code) async {
    final data = await _sendJson('POST', '/two-factor/confirm', body: {
      'code': code,
    });
    return asTextList(asMap(data)['recovery_codes']);
  }

  Future<void> disableTwoFactor(String password) async {
    await _sendJson('POST', '/two-factor/disable', body: {
      'password': password,
    });
  }

  Future<AuthSession> twoFactorLogin({
    required String challengeToken,
    required String code,
  }) async {
    final data = await _sendJson(
      'POST',
      '/two-factor/challenge',
      body: {
        'challenge_token': challengeToken,
        'code': code,
      },
      authenticated: false,
    );
    return AuthSession.fromJson(asMap(data));
  }

  Future<Map<String, dynamic>> meta({String? locale}) async {
    final data = await _sendJson(
      'GET',
      '/meta',
      query: locale == null ? null : {'locale': locale},
      authenticated: false,
    );
    return asMap(data);
  }

  Future<DashboardData> dashboard() async {
    final data = await _sendJson('GET', '/dashboard');
    return DashboardData.fromJson(asMap(data));
  }

  Future<MemoryPage> memories({
    String mode = 'library',
    String? query,
    String type = 'all',
    String language = 'all',
    String sort = 'newest',
    int? collection,
    int page = 1,
    int perPage = 12,
  }) async {
    final params = <String, String>{
      'mode': mode,
      'type': type,
      'language': language,
      'sort': sort,
      'page': '$page',
      'per_page': '$perPage',
    };
    if (query != null && query.trim().isNotEmpty) {
      params['q'] = query.trim();
    }
    if (collection != null) {
      params['collection'] = '$collection';
    }
    final data = await _sendJson('GET', '/memories', query: params);
    return MemoryPage.fromJson(asMap(data));
  }

  Future<MemoryDetail> memoryDetail(int id) async {
    final data = await _sendJson('GET', '/memories/$id');
    return MemoryDetail.fromJson(asMap(data));
  }

  Future<Memory> updateMemory(int id, Map<String, dynamic> payload) async {
    final data = await _sendJson('PATCH', '/memories/$id', body: payload);
    return Memory.fromJson(asMap(asMap(data)['data']));
  }

  Future<void> deleteMemory(int id) async {
    await _sendJson('DELETE', '/memories/$id');
  }

  Future<bool> toggleFavorite(int id) async {
    final data = await _sendJson('PATCH', '/memories/$id/favorite');
    return asBool(asMap(data)['is_favorite']);
  }

  Future<void> archiveMemory(int id) async {
    await _sendJson('PATCH', '/memories/$id/archive');
  }

  Future<void> restoreMemory(int id) async {
    await _sendJson('PATCH', '/memories/$id/restore');
  }

  Future<int> linkMemory({
    required int id,
    required int toMemoryId,
    String relationshipType = 'related',
  }) async {
    final data = await _sendJson('POST', '/memories/$id/link', body: {
      'to_memory_id': toMemoryId,
      'relationship_type': relationshipType,
    });
    return asInt(asMap(data)['relationship_id']);
  }

  Future<void> unlinkMemory({required int id, required int relationshipId}) async {
    await _sendJson('DELETE', '/memories/$id/links/$relationshipId');
  }

  Future<Memory> reprocessMemory(int id) async {
    final data = await _sendJson('POST', '/memories/$id/reprocess');
    return Memory.fromJson(asMap(asMap(data)['data']));
  }

  Future<Memory> addAttachments(
    int memoryId,
    List<({String path, String? name})> files,
  ) async {
    final request = http.MultipartRequest(
      'POST',
      apiUri('/memories/$memoryId/attachments'),
    );
    request.headers.addAll(_headers());

    for (final file in files) {
      try {
        request.files.add(
          await http.MultipartFile.fromPath(
            'files[]',
            file.path,
            filename: file.name,
          ),
        );
      } catch (_) {
        throw ApiException.network('Could not read one of the selected files.');
      }
    }

    final data = await _execute(request, const Duration(seconds: 120));
    return Memory.fromJson(asMap(asMap(data)['data']));
  }

  Future<Memory> deleteAttachment(int memoryId, int attachmentId) async {
    final data = await _sendJson(
      'DELETE',
      '/memories/$memoryId/attachments/$attachmentId',
    );
    return Memory.fromJson(asMap(asMap(data)['data']));
  }

  Future<Memory> capture({
    required String sourceType,
    String? title,
    String? body,
    String? sourceUrl,
    String? eventDate,
    String? tags,
    String? collectionId,
    String language = 'en',
    String? filePath,
    String? fileName,
  }) async {
    final fields = <String, String>{
      'source_type': sourceType,
      'language': language,
    };
    if (title != null && title.trim().isNotEmpty) {
      fields['title'] = title.trim();
    }
    if (body != null && body.trim().isNotEmpty) {
      fields['body'] = body.trim();
    }
    if (sourceUrl != null && sourceUrl.trim().isNotEmpty) {
      fields['source_url'] = sourceUrl.trim();
    }
    if (eventDate != null && eventDate.isNotEmpty) {
      fields['event_date'] = eventDate;
    }
    if (tags != null && tags.trim().isNotEmpty) {
      fields['tags'] = tags.trim();
    }
    if (collectionId != null) {
      fields['collection_id'] = collectionId;
    }

    final data = await _sendMultipart(
      '/capture',
      fields: fields,
      filePath: filePath,
      fileName: fileName,
    );
    return Memory.fromJson(asMap(asMap(data)['data']));
  }

  Future<SearchResultData> search({
    required String query,
    String type = 'all',
    String date = 'all',
    String language = 'all',
    bool favorite = false,
    bool archived = false,
  }) async {
    final params = <String, String>{
      'q': query,
      'type': type,
      'date': date,
      'language': language,
      if (favorite) 'favorite': '1',
      if (archived) 'archived': '1',
    };
    final data = await _sendJson('GET', '/search', query: params);
    return SearchResultData.fromJson(asMap(data));
  }

  Future<TimelineData> timeline({required String month, String type = 'all'}) async {
    final data = await _sendJson(
      'GET',
      '/timeline',
      query: {'month': month, 'type': type},
    );
    return TimelineData.fromJson(asMap(data));
  }

  Future<RemindersData> reminders() async {
    final data = await _sendJson('GET', '/reminders');
    return RemindersData.fromJson(asMap(data));
  }

  Future<Reminder> createReminder({
    required String title,
    required DateTime dueAt,
    required String type,
    int? memoryId,
    String? notes,
    String channel = 'in_app',
  }) async {
    final data = await _sendJson('POST', '/reminders', body: {
      'title': title,
      'due_at': localIsoTimestamp(dueAt),
      'type': type,
      'memory_id': memoryId,
      'notes': notes,
      'notification_channel': channel,
    });
    return Reminder.fromJson(asMap(asMap(data)['data']));
  }

  Future<Reminder> completeReminder(int id) async {
    final data = await _sendJson('PATCH', '/reminders/$id/complete');
    return Reminder.fromJson(asMap(asMap(data)['data']));
  }

  Future<Reminder> snoozeReminder(int id) async {
    final data = await _sendJson('PATCH', '/reminders/$id/snooze');
    return Reminder.fromJson(asMap(asMap(data)['data']));
  }

  Future<void> deleteReminder(int id) async {
    await _sendJson('DELETE', '/reminders/$id');
  }

  Future<SettingsData> settings() async {
    final data = await _sendJson('GET', '/settings');
    return SettingsData.fromJson(asMap(data));
  }

  Future<User> updateProfile({
    required String name,
    required String timezone,
    required String country,
    required String currency,
  }) async {
    final data = await _sendJson('PATCH', '/settings/profile', body: {
      'name': name,
      'timezone': timezone,
      'country': country,
      'currency': currency,
    });
    return User.fromJson(asMap(asMap(data)['user']));
  }

  Future<UserSettings> updateLanguage({
    required String interfaceLocale,
    required String aiLocale,
    required String dateFormat,
  }) async {
    final data = await _sendJson('PATCH', '/settings/language', body: {
      'interface_locale': interfaceLocale,
      'ai_locale': aiLocale,
      'date_format': dateFormat,
    });
    return UserSettings.fromJson(asMap(asMap(data)['settings']));
  }

  Future<UserSettings> updateNotifications({
    required bool notificationsEnabled,
    required bool emailReminders,
    String? quietHoursStart,
    String? quietHoursEnd,
  }) async {
    final data = await _sendJson('PATCH', '/settings/notifications', body: {
      'notifications_enabled': notificationsEnabled,
      'email_reminders': emailReminders,
      'quiet_hours_start': quietHoursStart,
      'quiet_hours_end': quietHoursEnd,
    });
    return UserSettings.fromJson(asMap(asMap(data)['settings']));
  }

  Future<UserSettings> updatePrivacy({required bool externalAiConsent}) async {
    final data = await _sendJson('PATCH', '/settings/privacy', body: {
      'external_ai_consent': externalAiConsent,
    });
    return UserSettings.fromJson(asMap(asMap(data)['settings']));
  }

  Future<void> deleteAccount({required String password}) async {
    await _sendJson('DELETE', '/account', body: {
      'password': password,
      'confirmation': 'DELETE',
    });
  }

  Future<List<Collection>> collections() async {
    final data = await _sendJson('GET', '/collections');
    return asMaps(asMap(data)['data']).map(Collection.fromJson).toList(growable: false);
  }

  Future<Collection> createCollection({
    required String name,
    String? description,
    String? color,
  }) async {
    final data = await _sendJson('POST', '/collections', body: {
      'name': name,
      'description': description,
      'color': color,
    });
    return Collection.fromJson(asMap(asMap(data)['data']));
  }

  Future<Collection> updateCollection(
    int id, {
    String? name,
    String? description,
    String? color,
  }) async {
    final body = <String, dynamic>{};
    if (name != null) {
      body['name'] = name;
    }
    if (description != null) {
      body['description'] = description;
    }
    if (color != null) {
      body['color'] = color;
    }
    final data = await _sendJson('PATCH', '/collections/$id', body: body);
    return Collection.fromJson(asMap(asMap(data)['data']));
  }

  Future<void> deleteCollection(int id) async {
    await _sendJson('DELETE', '/collections/$id');
  }

  Future<Map<String, dynamic>> exportArchive() async {
    final data = await _sendJson('GET', '/export');
    return asMap(data);
  }

  Future<List<int>> downloadBytes(String path) async {
    final response = await http
        .get(mediaUri(path), headers: authHeaders)
        .timeout(const Duration(seconds: 45));

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return response.bodyBytes;
    }

    throw _apiException(response);
  }

  Future<dynamic> _sendJson(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    bool authenticated = true,
    Duration timeout = const Duration(seconds: 25),
  }) async {
    final request = http.Request(method, apiUri(path, query));
    request.headers.addAll(_headers(authenticated: authenticated));

    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }

    return _execute(request, timeout);
  }

  Future<dynamic> _sendMultipart(
    String path, {
    required Map<String, String> fields,
    String? filePath,
    String? fileName,
    Duration timeout = const Duration(seconds: 90),
  }) async {
    final request = http.MultipartRequest('POST', apiUri(path));
    request.headers.addAll(_headers());
    request.fields.addAll(fields);

    if (filePath != null && filePath.isNotEmpty) {
      try {
        request.files.add(
          await http.MultipartFile.fromPath(
            'file',
            filePath,
            filename: fileName,
          ),
        );
      } catch (_) {
        throw ApiException.network('Could not read the selected file.');
      }
    }

    return _execute(request, timeout);
  }

  Future<dynamic> _execute(http.BaseRequest request, Duration timeout) async {
    try {
      final streamed = await request.send().timeout(timeout);
      final response = await http.Response.fromStream(streamed);
      final text = utf8.decode(response.bodyBytes, allowMalformed: true);
      dynamic data;

      if (text.isNotEmpty) {
        final contentType = response.headers['content-type'] ?? '';
        if (contentType.contains('json')) {
          try {
            data = jsonDecode(text);
          } catch (_) {
            data = text;
          }
        } else {
          data = text;
        }
      }

      if (response.statusCode >= 200 && response.statusCode < 300) {
        return data ?? const <String, dynamic>{};
      }

      if (response.statusCode == 401 && token != null) {
        onUnauthorized?.call();
      }

      throw _apiException(response, data: data);
    } on TimeoutException {
      throw ApiException.network('The server took too long to respond. Try again.');
    } on ApiException {
      rethrow;
    } catch (_) {
      throw ApiException.network();
    }
  }

  ApiException _apiException(http.Response response, {dynamic data}) {
    final body = data is Map ? asMap(data) : <String, dynamic>{};
    final errors = <String, List<String>>{};

    final rawErrors = body['errors'];
    if (rawErrors is Map) {
      rawErrors.forEach((key, value) {
        if (value is List) {
          errors['$key'] = value.map((item) => '$item').toList(growable: false);
        }
      });
    }

    var message = asText(body['message']);
    if (message.isEmpty) {
      message = errors.values.expand((values) => values).firstOrNull ??
          'Request failed with status ${response.statusCode}.';
    }

    return ApiException(
      statusCode: response.statusCode,
      message: message,
      errors: errors,
    );
  }

  Map<String, String> _headers({bool authenticated = true}) {
    return {
      'Accept': 'application/json',
      'X-Locale': locale,
      if (authenticated && token != null) 'Authorization': 'Bearer $token',
    };
  }
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull {
    for (final item in this) {
      return item;
    }
    return null;
  }
}
