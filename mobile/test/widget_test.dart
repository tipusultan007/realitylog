import 'package:flutter_test/flutter_test.dart';
import 'package:realitylog_mobile/core/formatters.dart';
import 'package:realitylog_mobile/l10n/strings.dart';

void main() {
  group('bilingual interface strings', () {
    test('English and Bengali keys resolve', () {
      const english = S('en');
      const bangla = S('bn');

      expect(english.tr('navHome'), 'Home');
      expect(bangla.tr('navHome'), 'হোম');
      expect(english.tr('searchAnswer'), isNot(equals(bangla.tr('searchAnswer'))));
    });

    test('placeholders are replaced', () {
      const english = S('en');
      expect(english.tr('timelineCount', {'count': 3}), '3 memories');
    });

    test('unknown keys fall back to the key', () {
      const english = S('en');
      expect(english.tr('missing.key'), 'missing.key');
    });
  });

  group('date formatting', () {
    test('maps API date patterns to Dart patterns', () {
      expect(apiDatePattern('d M Y'), 'd MMM yyyy');
      expect(apiDatePattern('j F Y'), 'd MMMM yyyy');
      expect(apiDatePattern('d/m/Y'), 'dd/MM/yyyy');
      expect(apiDatePattern('Y-m-d'), 'yyyy-MM-dd');
    });

    test('local timestamps keep their wall-clock time with an explicit offset', () {
      final local = DateTime(2026, 10, 22, 9);
      final serialized = localIsoTimestamp(local);

      expect(serialized, contains('T09:00:00'));
      expect(DateTime.parse(serialized).isAtSameMomentAs(local), isTrue);
    });
  });
}
