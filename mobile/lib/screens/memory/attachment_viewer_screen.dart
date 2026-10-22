import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../models/memory.dart';
import '../../widgets/authenticated_image.dart';

class AttachmentViewerScreen extends StatelessWidget {
  const AttachmentViewerScreen({
    super.key,
    required this.attachment,
    required this.url,
    required this.headers,
  });

  final MemoryAttachment attachment;
  final String url;
  final Map<String, String> headers;

  @override
  Widget build(BuildContext context) {
    final isSvg = attachment.isSvg;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(attachment.originalName),
      ),
      body: attachment.isImage
          ? InteractiveViewer(
              minScale: 0.8,
              maxScale: 5,
              child: Center(
                child: isSvg
                    ? SvgPicture.network(
                        url,
                        headers: headers,
                        fit: BoxFit.contain,
                      )
                    : AuthenticatedImage(
                        url: url,
                        headers: headers,
                        fit: BoxFit.contain,
                      ),
              ),
            )
          : Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.insert_drive_file_outlined, size: 54, color: scheme.outline),
                  const SizedBox(height: 12),
                  Text(
                    attachment.originalName,
                    style: const TextStyle(color: Colors.white70),
                  ),
                ],
              ),
            ),
    );
  }
}
