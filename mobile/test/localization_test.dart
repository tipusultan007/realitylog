import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:realitylog_mobile/l10n/strings.dart';

void main() {
  group('localization coverage', () {
    late Set<String> english;
    late Set<String> bangla;

    setUpAll(() {
      final source = File('lib/l10n/strings.dart').readAsStringSync();
      english = _keysIn(source, '_en = {');
      bangla = _keysIn(source, '_bn = {');
    });

    test('both languages define the same keys', () {
      expect(english, isNotEmpty);
      expect(bangla.difference(english), isEmpty,
          reason: 'Bengali-only keys: ${bangla.difference(english)}');
      expect(english.difference(bangla), isEmpty,
          reason: 'English-only keys: ${english.difference(bangla)}');
    });

    test('every key has a non-empty translation', () {
      const en = S('en');
      const bn = S('bn');
      for (final key in english) {
        expect(en.tr(key).trim(), isNotEmpty, reason: 'Empty English value for $key');
        expect(bn.tr(key).trim(), isNotEmpty, reason: 'Empty Bengali value for $key');
      }
    });
  });
}

Set<String> _keysIn(String source, String marker) {
  final start = source.indexOf(marker);
  if (start < 0) {
    return {};
  }
  final end = source.indexOf('\n  };', start);
  final body = source.substring(start, end < 0 ? source.length : end);
  return RegExp(r"^    '([^']+)':", multiLine: true)
      .allMatches(body)
      .map((match) => match.group(1)!)
      .toSet();
}
