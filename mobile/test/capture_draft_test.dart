import 'package:flutter_test/flutter_test.dart';
import 'package:realitylog_mobile/core/draft_store.dart';

void main() {
  group('capture drafts', () {
    test('round-trips through JSON without losing fields', () {
      final draft = CaptureDraft(
        sourceType: 'document',
        title: 'ফ্রিজের ওয়ারেন্টি',
        body: 'Walton fridge, BDT 18,000',
        sourceUrl: 'https://example.com/warranty',
        tags: 'fridge, warranty',
        language: 'bn',
        eventDate: '2026-03-01',
        collectionId: 4,
        filePath: '/data/user/0/app/files/capture_drafts/warranty.pdf',
        fileName: 'warranty.pdf',
        savedAt: DateTime(2026, 10, 21, 9, 30),
      );

      final restored = CaptureDraft.fromJson(draft.toJson());

      expect(restored.sourceType, 'document');
      expect(restored.title, 'ফ্রিজের ওয়ারেন্টি');
      expect(restored.body, 'Walton fridge, BDT 18,000');
      expect(restored.sourceUrl, 'https://example.com/warranty');
      expect(restored.tags, 'fridge, warranty');
      expect(restored.language, 'bn');
      expect(restored.eventDate, '2026-03-01');
      expect(restored.collectionId, 4);
      expect(restored.filePath, draft.filePath);
      expect(restored.fileName, 'warranty.pdf');
      expect(restored.savedAt, draft.savedAt);
      expect(restored.isEmpty, isFalse);
    });

    test('a draft with only whitespace counts as empty', () {
      final draft = CaptureDraft(
        sourceType: 'text',
        title: '   ',
        body: '',
        sourceUrl: '',
        tags: '',
        language: 'en',
        eventDate: null,
        collectionId: null,
        filePath: null,
        fileName: null,
        savedAt: DateTime(2026, 10, 21),
      );

      expect(draft.isEmpty, isTrue);
    });

    test('an attached file keeps the draft alive', () {
      final draft = CaptureDraft(
        sourceType: 'image',
        title: '',
        body: '',
        sourceUrl: '',
        tags: '',
        language: 'en',
        eventDate: null,
        collectionId: null,
        filePath: '/tmp/receipt.jpg',
        fileName: 'receipt.jpg',
        savedAt: DateTime(2026, 10, 21),
      );

      expect(draft.isEmpty, isFalse);
    });
  });
}
