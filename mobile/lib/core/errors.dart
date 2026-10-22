import 'api_exception.dart';
import '../l10n/strings.dart';

String describeError(Object error, S s) {
  if (error is ApiException) {
    if (error.isNetwork) {
      return s.tr('errorNetwork');
    }
    if (error.isUnauthorized) {
      return s.tr('errorUnauthorized');
    }
    if (error.detail.trim().isNotEmpty) {
      return error.detail;
    }
    return s.tr('errorServer');
  }
  return s.tr('errorUnknown');
}
