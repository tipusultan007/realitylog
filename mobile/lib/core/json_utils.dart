String asText(dynamic value, [String fallback = '']) {
  if (value == null) {
    return fallback;
  }
  if (value is String) {
    return value;
  }
  return value.toString();
}

String? asNullableText(dynamic value) {
  if (value == null) {
    return null;
  }
  final text = value is String ? value : value.toString();
  return text.trim().isEmpty ? null : text;
}

int asInt(dynamic value, [int fallback = 0]) {
  if (value is int) {
    return value;
  }
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse('${value ?? ''}') ?? fallback;
}

bool asBool(dynamic value, [bool fallback = false]) {
  if (value is bool) {
    return value;
  }
  if (value is num) {
    return value != 0;
  }
  if (value is String) {
    return value == '1' || value.toLowerCase() == 'true';
  }
  return fallback;
}

Map<String, dynamic> asMap(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<Map<String, dynamic>> asMaps(dynamic value) {
  if (value is List) {
    return value.whereType<Map>().map(asMap).toList(growable: false);
  }
  return const [];
}

List<String> asTextList(dynamic value) {
  if (value is List) {
    return value.map((item) => asText(item)).where((item) => item.isNotEmpty).toList(growable: false);
  }
  return const [];
}

DateTime? asDate(dynamic value) {
  if (value is String && value.trim().isNotEmpty) {
    return DateTime.tryParse(value)?.toLocal();
  }
  return null;
}

String? dateOnly(dynamic value) {
  if (value is String && value.trim().isNotEmpty) {
    return value.length >= 10 ? value.substring(0, 10) : value;
  }
  return null;
}
