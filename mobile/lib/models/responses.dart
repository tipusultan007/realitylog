import '../core/json_utils.dart';
import 'memory.dart';
import 'user.dart';

class DashboardData {
  const DashboardData({
    required this.greeting,
    required this.serverDate,
    required this.stats,
    required this.typeBreakdown,
    required this.recentMemories,
    required this.upcomingReminders,
    required this.recentSearches,
    required this.onThisDay,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final statsJson = asMap(json['stats']);
    return DashboardData(
      greeting: asText(json['greeting']),
      serverDate: asDate(json['server_date']),
      stats: statsJson.map((key, value) => MapEntry(key, asInt(value))),
      typeBreakdown: asMaps(json['type_breakdown']).map(TypeCount.fromJson).toList(growable: false),
      recentMemories: asMaps(json['recent_memories']).map(Memory.fromJson).toList(growable: false),
      upcomingReminders: asMaps(json['upcoming_reminders']).map(Reminder.fromJson).toList(growable: false),
      recentSearches: asTextList(json['recent_searches']),
      onThisDay: asMaps(json['on_this_day']).map(Memory.fromJson).toList(growable: false),
    );
  }

  final String greeting;
  final DateTime? serverDate;
  final Map<String, int> stats;
  final List<TypeCount> typeBreakdown;
  final List<Memory> recentMemories;
  final List<Reminder> upcomingReminders;
  final List<String> recentSearches;
  final List<Memory> onThisDay;

  int stat(String key) => stats[key] ?? 0;
}

class MemoryPage {
  const MemoryPage({
    required this.memories,
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.totalForMode,
    required this.filters,
    required this.typeCounts,
    required this.collections,
  });

  factory MemoryPage.fromJson(Map<String, dynamic> json) {
    final meta = asMap(json['meta']);
    return MemoryPage(
      memories: asMaps(json['data']).map(Memory.fromJson).toList(growable: false),
      page: asInt(meta['current_page'], 1),
      lastPage: asInt(meta['last_page'], 1),
      perPage: asInt(meta['per_page'], 12),
      total: asInt(meta['total']),
      totalForMode: asInt(json['total_for_mode']),
      filters: asMap(json['filters']),
      typeCounts: asMaps(json['type_counts']).map(TypeCount.fromJson).toList(growable: false),
      collections: asMaps(json['collections']).map(Collection.fromJson).toList(growable: false),
    );
  }

  final List<Memory> memories;
  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final int totalForMode;
  final Map<String, dynamic> filters;
  final List<TypeCount> typeCounts;
  final List<Collection> collections;

  bool get hasMore => page < lastPage;
}

class SearchDateRange {
  const SearchDateRange({required this.label, required this.start, required this.end});

  factory SearchDateRange.fromJson(Map<String, dynamic> json) {
    return SearchDateRange(
      label: asText(json['label']),
      start: asDate(json['start']),
      end: asDate(json['end']),
    );
  }

  final String label;
  final DateTime? start;
  final DateTime? end;
}

class SearchSource {
  const SearchSource({
    required this.matchType,
    required this.excerpt,
    required this.score,
    required this.similarity,
    required this.vectorMatch,
    required this.date,
    required this.memory,
  });

  factory SearchSource.fromJson(Map<String, dynamic> json) {
    return SearchSource(
      matchType: asText(json['match_type']),
      excerpt: asText(json['excerpt']),
      score: json['score'] is num ? (json['score'] as num).toDouble() : 0,
      similarity:
          json['similarity'] is num ? (json['similarity'] as num).toDouble() : 0,
      vectorMatch: asBool(json['vector_match']),
      date: asText(json['date']),
      memory: Memory.fromJson(asMap(json['memory'])),
    );
  }

  final String matchType;
  final String excerpt;
  final double score;
  final double similarity;
  final bool vectorMatch;
  final String date;
  final Memory memory;
}

class SearchHit {
  const SearchHit({
    required this.matchType,
    required this.score,
    required this.similarity,
    required this.vectorMatch,
    required this.exactMatches,
    required this.semanticMatches,
    required this.memory,
  });

  factory SearchHit.fromJson(Map<String, dynamic> json) {
    return SearchHit(
      matchType: asText(json['match_type']),
      score: json['score'] is num ? (json['score'] as num).toDouble() : 0,
      similarity:
          json['similarity'] is num ? (json['similarity'] as num).toDouble() : 0,
      vectorMatch: asBool(json['vector_match']),
      exactMatches: asInt(json['exact_matches']),
      semanticMatches: asInt(json['semantic_matches']),
      memory: Memory.fromJson(asMap(json['memory'])),
    );
  }

  final String matchType;
  final double score;
  final double similarity;
  final bool vectorMatch;
  final int exactMatches;
  final int semanticMatches;
  final Memory memory;
}

class SearchResultData {
  const SearchResultData({
    required this.query,
    required this.locale,
    required this.hasAnswer,
    required this.answer,
    required this.answerKind,
    required this.confidence,
    required this.confidenceLabel,
    required this.intent,
    required this.total,
    required this.tooShort,
    required this.dateRange,
    required this.sources,
    required this.results,
    required this.filters,
    required this.recentSearches,
  });

  factory SearchResultData.fromJson(Map<String, dynamic> json) {
    final dateRangeJson = json['date_range'];
    return SearchResultData(
      query: asText(json['query']),
      locale: asText(json['locale'], 'en'),
      hasAnswer: asBool(json['has_answer']),
      answer: asNullableText(json['answer']),
      answerKind: asNullableText(json['answer_kind']),
      confidence: asText(json['confidence'], 'low'),
      confidenceLabel: asText(json['confidence_label']),
      intent: asNullableText(json['intent']),
      total: asInt(json['total']),
      tooShort: asBool(json['too_short']),
      dateRange: dateRangeJson is Map ? SearchDateRange.fromJson(asMap(dateRangeJson)) : null,
      sources: asMaps(json['sources']).map(SearchSource.fromJson).toList(growable: false),
      results: asMaps(json['results']).map(SearchHit.fromJson).toList(growable: false),
      filters: asMap(json['filters']),
      recentSearches: asTextList(json['recent_searches']),
    );
  }

  final String query;
  final String locale;
  final bool hasAnswer;
  final String? answer;
  final String? answerKind;
  final String confidence;
  final String confidenceLabel;
  final String? intent;
  final int total;
  final bool tooShort;
  final SearchDateRange? dateRange;
  final List<SearchSource> sources;
  final List<SearchHit> results;
  final Map<String, dynamic> filters;
  final List<String> recentSearches;
}

class TimelineGroup {
  const TimelineGroup({required this.date, required this.label, required this.memories});

  factory TimelineGroup.fromJson(Map<String, dynamic> json) {
    return TimelineGroup(
      date: asText(json['date']),
      label: asText(json['label']),
      memories: asMaps(json['memories']).map(Memory.fromJson).toList(growable: false),
    );
  }

  final String date;
  final String label;
  final List<Memory> memories;
}

class TimelineData {
  const TimelineData({
    required this.month,
    required this.previousMonth,
    required this.nextMonth,
    required this.monthTotal,
    required this.groups,
    required this.undated,
    required this.type,
  });

  factory TimelineData.fromJson(Map<String, dynamic> json) {
    return TimelineData(
      month: asText(json['month']),
      previousMonth: asText(json['previous_month']),
      nextMonth: asText(json['next_month']),
      monthTotal: asInt(json['month_total']),
      groups: asMaps(json['groups']).map(TimelineGroup.fromJson).toList(growable: false),
      undated: asMaps(json['undated']).map(Memory.fromJson).toList(growable: false),
      type: asText(json['type'], 'all'),
    );
  }

  final String month;
  final String previousMonth;
  final String nextMonth;
  final int monthTotal;
  final List<TimelineGroup> groups;
  final List<Memory> undated;
  final String type;
}

class RemindersData {
  const RemindersData({
    required this.overdue,
    required this.upcoming,
    required this.completed,
    required this.memories,
  });

  factory RemindersData.fromJson(Map<String, dynamic> json) {
    return RemindersData(
      overdue: asMaps(json['overdue']).map(Reminder.fromJson).toList(growable: false),
      upcoming: asMaps(json['upcoming']).map(Reminder.fromJson).toList(growable: false),
      completed: asMaps(json['completed']).map(Reminder.fromJson).toList(growable: false),
      memories: asMaps(json['memories']).map(MemoryOption.fromJson).toList(growable: false),
    );
  }

  final List<Reminder> overdue;
  final List<Reminder> upcoming;
  final List<Reminder> completed;
  final List<MemoryOption> memories;
}

class SettingsData {
  const SettingsData({required this.user, required this.settings, required this.meta});

  factory SettingsData.fromJson(Map<String, dynamic> json) {
    return SettingsData(
      user: User.fromJson(asMap(json['user'])),
      settings: UserSettings.fromJson(asMap(json['settings'])),
      meta: asMap(json['meta']),
    );
  }

  final User user;
  final UserSettings settings;
  final Map<String, dynamic> meta;
}

class MemorySuggestion {
  const MemorySuggestion({
    required this.memory,
    required this.score,
    required this.similarity,
    required this.reason,
    required this.sharedEntities,
    required this.sharedTags,
  });

  factory MemorySuggestion.fromJson(Map<String, dynamic> json) {
    return MemorySuggestion(
      memory: Memory.fromJson(asMap(json['memory'])),
      score: json['score'] is num ? (json['score'] as num).toDouble() : 0,
      similarity:
          json['similarity'] is num ? (json['similarity'] as num).toDouble() : 0,
      reason: asText(json['reason'], 'type'),
      sharedEntities: asTextList(json['shared_entities']),
      sharedTags: asTextList(json['shared_tags']),
    );
  }

  final Memory memory;
  final double score;
  final double similarity;
  final String reason;
  final List<String> sharedEntities;
  final List<String> sharedTags;
}

class MemoryDetail {
  const MemoryDetail({
    required this.memory,
    required this.related,
    required this.suggested,
    required this.linkable,
  });

  factory MemoryDetail.fromJson(Map<String, dynamic> json) {
    return MemoryDetail(
      memory: Memory.fromJson(asMap(json['data'])),
      related: asMaps(json['related']).map(Memory.fromJson).toList(growable: false),
      suggested: asMaps(json['suggested'])
          .map(MemorySuggestion.fromJson)
          .toList(growable: false),
      linkable: asMaps(json['linkable']).map(MemoryOption.fromJson).toList(growable: false),
    );
  }

  final Memory memory;
  final List<Memory> related;
  final List<MemorySuggestion> suggested;
  final List<MemoryOption> linkable;
}
