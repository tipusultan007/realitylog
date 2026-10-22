import 'package:intl/intl.dart';

String apiDatePattern(String pattern) {
  return switch (pattern) {
    'd M Y' => 'd MMM yyyy',
    'j F Y' => 'd MMMM yyyy',
    'd/m/Y' => 'dd/MM/yyyy',
    'Y-m-d' => 'yyyy-MM-dd',
    _ => 'd MMM yyyy',
  };
}

String formatDate(DateTime? date, String apiPattern, String locale) {
  if (date == null) {
    return '';
  }
  return DateFormat(apiDatePattern(apiPattern), locale).format(date);
}

String formatDateTime(DateTime? date, String locale) {
  if (date == null) {
    return '';
  }
  return DateFormat.yMMMd(locale).add_jm().format(date);
}

String formatMonthLabel(String month, String locale) {
  final parsed = DateTime.tryParse('$month-01');
  if (parsed == null) {
    return month;
  }
  return DateFormat.yMMMM(locale).format(parsed);
}

String formatTimeOfDay(String value, String locale) {
  final parts = value.split(':');
  if (parts.length < 2) {
    return value;
  }
  final hour = int.tryParse(parts[0]) ?? 0;
  final minute = int.tryParse(parts[1]) ?? 0;
  final date = DateTime(2000, 1, 1, hour, minute);
  return DateFormat.jm(locale).format(date);
}

/// Serializes a local date/time with its explicit UTC offset.
///
/// The API stores timestamps in the application timezone. Sending a bare
/// `DateTime.toUtc().toIso8601String()` makes the server reinterpret the UTC
/// clock time as local time, shifting reminders by the offset (for example,
/// 9:00 AM becoming 3:00 AM). The offset keeps the intended wall-clock time.
String localIsoTimestamp(DateTime dateTime) {
  String two(int value) => value.toString().padLeft(2, '0');

  final offset = dateTime.timeZoneOffset;
  final sign = offset.isNegative ? '-' : '+';
  final absolute = offset.abs();
  final offsetHours = two(absolute.inHours);
  final offsetMinutes = two(absolute.inMinutes.remainder(60));

  return '${dateTime.year.toString().padLeft(4, '0')}-'
      '${two(dateTime.month)}-${two(dateTime.day)}T'
      '${two(dateTime.hour)}:${two(dateTime.minute)}:${two(dateTime.second)}'
      '$sign$offsetHours:$offsetMinutes';
}

String humanBytes(int bytes) {
  if (bytes >= 1048576) {
    return '${(bytes / 1048576).toStringAsFixed(1)} MB';
  }
  if (bytes >= 1024) {
    return '${(bytes / 1024).round()} KB';
  }
  return '$bytes B';
}

String dayLabel(DateTime date, String locale) {
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final target = DateTime(date.year, date.month, date.day);
  final difference = today.difference(target).inDays;

  if (difference == 0) {
    return DateFormat.yMMMMd(locale).format(date);
  }
  if (difference == 1) {
    return DateFormat.yMMMMd(locale).format(date);
  }
  return DateFormat.yMMMMd(locale).format(date);
}
