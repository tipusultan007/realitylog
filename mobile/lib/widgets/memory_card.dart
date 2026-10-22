import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/app_state.dart';
import '../core/formatters.dart';
import '../l10n/strings.dart';
import '../models/memory.dart';
import 'authenticated_image.dart';
import 'common.dart';

class MemoryCard extends StatelessWidget {
  const MemoryCard({super.key, required this.memory, this.onTap});

  final Memory memory;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final dateFormat = app.settings?.dateFormat ?? 'd M Y';
    final locale = app.locale;
    final cover = memory.cover;
    final preview = (memory.summary?.trim().isNotEmpty ?? false)
        ? memory.summary!.trim()
        : (memory.excerpt.trim().isNotEmpty ? memory.excerpt.trim() : memory.body.trim());

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (cover != null && cover.isImage)
              AuthenticatedImage(
                url: app.api.resolveMediaUrl(cover.url),
                headers: app.api.authHeaders,
                height: 150,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
              ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      InfoPill(
                        label: context.s.tr('type.${memory.memoryType}'),
                        icon: _typeIcon(memory.memoryType),
                        tone: scheme.secondaryContainer,
                      ),
                      if (memory.isArchived) ...[
                        const SizedBox(width: 6),
                        InfoPill(
                          label: context.s.tr('memoryArchive'),
                          icon: Icons.inventory_2_outlined,
                          tone: scheme.tertiary,
                        ),
                      ],
                      const Spacer(),
                      if (memory.isFavorite)
                        Icon(Icons.star_rounded, size: 20, color: scheme.tertiary),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    memory.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontSize: 17,
                      height: 1.25,
                    ),
                  ),
                  if (preview.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Text(
                      preview,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Icon(Icons.event_outlined, size: 14, color: scheme.onSurfaceVariant),
                      const SizedBox(width: 5),
                      Expanded(
                        child: Text(
                          formatDate(
                            memory.eventDate ?? memory.capturedAt,
                            dateFormat,
                            locale,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ),
                      if (memory.amount != null) ...[
                        Text(
                          memory.amount!,
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: scheme.primary,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(width: 10),
                      ],
                      if (memory.attachments.isNotEmpty) ...[
                        Icon(Icons.attach_file_rounded, size: 14, color: scheme.onSurfaceVariant),
                        const SizedBox(width: 2),
                        Text(
                          '${memory.attachments.length}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ],
                  ),
                  if (memory.tags.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: memory.tags
                          .take(4)
                          .map(
                            (tag) => Text(
                              '#${tag.name}',
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: scheme.secondary,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          )
                          .toList(growable: false),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  IconData _typeIcon(String type) {
    return switch (type) {
      'receipt' => Icons.receipt_long_outlined,
      'purchase' => Icons.shopping_bag_outlined,
      'document' => Icons.description_outlined,
      'person' => Icons.person_outline,
      'place' => Icons.place_outlined,
      'event' => Icons.event_outlined,
      'repair' => Icons.build_outlined,
      'task' => Icons.checklist_outlined,
      'idea' => Icons.lightbulb_outline,
      'travel' => Icons.flight_takeoff_outlined,
      'meeting' => Icons.groups_outlined,
      'warranty' => Icons.verified_outlined,
      'medical' => Icons.medical_services_outlined,
      'link' => Icons.link_outlined,
      _ => Icons.sticky_note_2_outlined,
    };
  }
}
