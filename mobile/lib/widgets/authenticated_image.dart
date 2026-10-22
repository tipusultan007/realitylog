import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class AuthenticatedImage extends StatelessWidget {
  const AuthenticatedImage({
    super.key,
    required this.url,
    required this.headers,
    this.fit = BoxFit.cover,
    this.width,
    this.height,
    this.borderRadius,
    this.iconSize = 28,
  });

  final String url;
  final Map<String, String> headers;
  final BoxFit fit;
  final double? width;
  final double? height;
  final BorderRadius? borderRadius;
  final double iconSize;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isSvg = url.toLowerCase().contains('.svg');

    Widget placeholder({Widget? child}) {
      return Container(
        width: width,
        height: height,
        color: scheme.surfaceContainerHigh,
        alignment: Alignment.center,
        child: child ??
            SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: scheme.onSurfaceVariant,
              ),
            ),
      );
    }

    Widget errorPlaceholder() {
      return Container(
        width: width,
        height: height,
        color: scheme.surfaceContainerHigh,
        alignment: Alignment.center,
        child: Icon(
          Icons.broken_image_outlined,
          size: iconSize,
          color: scheme.onSurfaceVariant,
        ),
      );
    }

    final image = isSvg
        ? SvgPicture.network(
            url,
            headers: headers,
            fit: fit,
            width: width,
            height: height,
            placeholderBuilder: (_) => placeholder(),
          )
        : Image.network(
            url,
            headers: headers,
            fit: fit,
            width: width,
            height: height,
            loadingBuilder: (context, child, progress) {
              if (progress == null) {
                return child;
              }
              return placeholder();
            },
            errorBuilder: (context, error, stackTrace) => errorPlaceholder(),
          );

    if (borderRadius == null) {
      return image;
    }

    return ClipRRect(borderRadius: borderRadius!, child: image);
  }
}
