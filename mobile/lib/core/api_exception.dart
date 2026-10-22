/// Raised after a correct password when the account has two-factor enabled.
///
/// The password alone is not enough; the caller must collect the second factor
/// and exchange [challengeToken] for a real session.
class TwoFactorChallengeRequired implements Exception {
  const TwoFactorChallengeRequired({
    required this.challengeToken,
    this.expiresAt,
    this.message = '',
  });

  final String challengeToken;
  final DateTime? expiresAt;
  final String message;

  @override
  String toString() => 'TwoFactorChallengeRequired';
}

class ApiException implements Exception {
  const ApiException({
    this.statusCode,
    required this.message,
    this.errors = const {},
    this.isNetwork = false,
  });

  factory ApiException.network([String? message]) {
    return ApiException(
      message: message ?? 'Could not reach the RealityLog server. Check the API address and your connection.',
      isNetwork: true,
    );
  }

  final int? statusCode;
  final String message;
  final Map<String, List<String>> errors;
  final bool isNetwork;

  bool get isUnauthorized => statusCode == 401;

  String get detail {
    for (final values in errors.values) {
      if (values.isNotEmpty) {
        return values.first;
      }
    }
    return message;
  }

  @override
  String toString() => 'ApiException($statusCode): $message';
}
