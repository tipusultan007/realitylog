import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api_exception.dart';
import '../../core/app_state.dart';
import '../../l10n/strings.dart';
import '../../widgets/brand.dart';
import '../../widgets/language_toggle.dart';

class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key});

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final _codeController = TextEditingController();

  bool _register = false;
  bool _obscurePassword = true;
  String? _error;
  String? _challengeToken;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();

    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final app = context.read<AppState>();
    setState(() => _error = null);

    try {
      if (_register) {
        await app.register(
          name: _nameController.text.trim(),
          email: _emailController.text.trim(),
          password: _passwordController.text,
          passwordConfirmation: _confirmController.text,
        );
      } else {
        await app.login(
          _emailController.text.trim(),
          _passwordController.text,
        );
      }
    } on TwoFactorChallengeRequired catch (challenge) {
      if (mounted) {
        setState(() {
          _challengeToken = challenge.challengeToken;
          _codeController.clear();
          _error = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.detail);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _error = context.s.tr('errorUnknown'));
      }
    }
  }

  Future<void> _verifyChallenge() async {
    final challengeToken = _challengeToken;
    if (challengeToken == null) {
      return;
    }

    final code = _codeController.text.trim();
    if (code.isEmpty) {
      setState(() => _error = context.s.tr('authTwoFactorMissing'));
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() => _error = null);

    try {
      await context.read<AppState>().completeTwoFactorLogin(challengeToken, code);
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.detail);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _error = context.s.tr('errorUnknown'));
      }
    }
  }

  void _cancelChallenge() {
    setState(() {
      _challengeToken = null;
      _codeController.clear();
      _error = null;
    });
  }

  Widget _challengeView(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Icon(Icons.verified_user_outlined, size: 40, color: scheme.primary),
        const SizedBox(height: 12),
        Text(s.tr('authTwoFactorTitle'), style: theme.textTheme.titleLarge),
        const SizedBox(height: 8),
        Text(
          s.tr('authTwoFactorHint'),
          style: theme.textTheme.bodySmall?.copyWith(
            color: scheme.onSurfaceVariant,
            height: 1.4,
          ),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _codeController,
          autofocus: true,
          autocorrect: false,
          enableSuggestions: false,
          textCapitalization: TextCapitalization.characters,
          textInputAction: TextInputAction.done,
          decoration: InputDecoration(
            labelText: s.tr('authTwoFactorCode'),
            prefixIcon: const Icon(Icons.pin_outlined),
          ),
          onSubmitted: (_) => _verifyChallenge(),
        ),
        if (_error != null) ...[
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: scheme.errorContainer,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.error_outline, size: 18, color: scheme.onErrorContainer),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _error!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onErrorContainer,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 18),
        FilledButton(
          onPressed: app.busy ? null : _verifyChallenge,
          child: app.busy
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2.2),
                )
              : Text(s.tr('authTwoFactorVerify')),
        ),
        TextButton(
          onPressed: app.busy ? null : _cancelChallenge,
          child: Text(s.tr('authTwoFactorBack')),
        ),
      ],
    );
  }

  void _useDemo() {
    setState(() {
      _register = false;
      _emailController.text = 'demo@realitylog.app';
      _passwordController.text = 'password';
      _error = null;
    });
    _submit();
  }

  void _switchMode() {
    setState(() {
      _register = !_register;
      _error = null;
      _formKey.currentState?.reset();
    });
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = context.s;
    final scheme = Theme.of(context).colorScheme;
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: Stack(
          children: [
            Positioned(
              top: -70,
              right: -50,
              child: _Glow(
                color: scheme.primary.withValues(alpha: 0.16),
                size: 220,
              ),
            ),
            Positioned(
              bottom: -80,
              left: -60,
              child: _Glow(
                color: scheme.secondary.withValues(alpha: 0.13),
                size: 240,
              ),
            ),
            Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(22, 18, 22, 30),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 460),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const AppLogo(size: 54),
                          const Spacer(),
                          LanguageToggle(
                            locale: app.locale,
                            onChanged: (code) {
                              app.setInterfaceLocale(code, sync: false);
                            },
                            compact: true,
                          ),
                        ],
                      ),
                      const SizedBox(height: 22),
                      Text(
                        _register ? s.tr('authCreateSubtitle') : s.tr('authSubtitle'),
                        style: theme.textTheme.headlineMedium?.copyWith(
                          fontFamily: 'serif',
                          height: 1.18,
                        ),
                      ),
                      const SizedBox(height: 20),
                      Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: scheme.surface,
                          borderRadius: BorderRadius.circular(26),
                          border: Border.all(
                            color: scheme.outlineVariant.withValues(alpha: 0.6),
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.04),
                              blurRadius: 30,
                              offset: const Offset(0, 14),
                            ),
                          ],
                        ),
                        child: _challengeToken == null
                            ? AutofillGroup(
                                child: Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Text(
                                  _register ? s.tr('authSignUp') : s.tr('authWelcome'),
                                  style: theme.textTheme.titleLarge,
                                ),
                                const SizedBox(height: 16),
                                if (_register) ...[
                                  TextFormField(
                                    controller: _nameController,
                                    textInputAction: TextInputAction.next,
                                    textCapitalization: TextCapitalization.words,
                                    autofillHints: const [AutofillHints.name],
                                    decoration: InputDecoration(
                                      labelText: s.tr('authName'),
                                      prefixIcon: const Icon(Icons.person_outline),
                                    ),
                                    validator: (value) {
                                      if ((value ?? '').trim().isEmpty) {
                                        return s.tr('authNameRequired');
                                      }
                                      return null;
                                    },
                                  ),
                                  const SizedBox(height: 12),
                                ],
                                TextFormField(
                                  controller: _emailController,
                                  keyboardType: TextInputType.emailAddress,
                                  textInputAction: TextInputAction.next,
                                  autofillHints: const [AutofillHints.email],
                                  autocorrect: false,
                                  decoration: InputDecoration(
                                    labelText: s.tr('authEmail'),
                                    prefixIcon: const Icon(Icons.alternate_email_rounded),
                                  ),
                                  validator: (value) {
                                    final email = (value ?? '').trim();
                                    if (email.isEmpty) {
                                      return s.tr('authEmailRequired');
                                    }
                                    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
                                      return s.tr('authInvalidEmail');
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 12),
                                TextFormField(
                                  controller: _passwordController,
                                  obscureText: _obscurePassword,
                                  textInputAction: _register ? TextInputAction.next : TextInputAction.done,
                                  autofillHints: _register
                                      ? const [AutofillHints.newPassword]
                                      : const [AutofillHints.password],
                                  onFieldSubmitted: (_) {
                                    if (!_register) {
                                      _submit();
                                    }
                                  },
                                  decoration: InputDecoration(
                                    labelText: s.tr('authPassword'),
                                    prefixIcon: const Icon(Icons.lock_outline_rounded),
                                    suffixIcon: IconButton(
                                      tooltip: _obscurePassword ? 'Show' : 'Hide',
                                      onPressed: () {
                                        setState(() => _obscurePassword = !_obscurePassword);
                                      },
                                      icon: Icon(
                                        _obscurePassword
                                            ? Icons.visibility_outlined
                                            : Icons.visibility_off_outlined,
                                      ),
                                    ),
                                  ),
                                  validator: (value) {
                                    final password = value ?? '';
                                    if (password.isEmpty) {
                                      return s.tr('authPasswordRequired');
                                    }
                                    if (_register &&
                                        (password.length < 8 ||
                                            !RegExp(r'[A-Za-z]').hasMatch(password) ||
                                            !RegExp(r'\d').hasMatch(password))) {
                                      return s.tr('authPasswordHint');
                                    }
                                    return null;
                                  },
                                ),
                                if (_register) ...[
                                  const SizedBox(height: 12),
                                  TextFormField(
                                    controller: _confirmController,
                                    obscureText: _obscurePassword,
                                    textInputAction: TextInputAction.done,
                                    autofillHints: const [AutofillHints.newPassword],
                                    onFieldSubmitted: (_) => _submit(),
                                    decoration: InputDecoration(
                                      labelText: s.tr('authConfirmPassword'),
                                      prefixIcon: const Icon(Icons.lock_reset_rounded),
                                    ),
                                    validator: (value) {
                                      if ((value ?? '').isEmpty) {
                                        return s.tr('authConfirmRequired');
                                      }
                                      if (value != _passwordController.text) {
                                        return s.tr('authPasswordMismatch');
                                      }
                                      return null;
                                    },
                                  ),
                                ],
                                if (_error != null) ...[
                                  const SizedBox(height: 14),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                    decoration: BoxDecoration(
                                      color: scheme.errorContainer,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Icon(Icons.error_outline, size: 18, color: scheme.onErrorContainer),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: Text(
                                            _error!,
                                            style: theme.textTheme.bodySmall?.copyWith(
                                              color: scheme.onErrorContainer,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 18),
                                FilledButton(
                                  onPressed: app.busy ? null : _submit,
                                  child: app.busy
                                      ? const SizedBox(
                                          width: 20,
                                          height: 20,
                                          child: CircularProgressIndicator(strokeWidth: 2.2),
                                        )
                                      : Text(_register ? s.tr('authSignUp') : s.tr('authSignIn')),
                                ),
                                if (!_register) ...[
                                  const SizedBox(height: 10),
                                  TextButton.icon(
                                    onPressed: app.busy ? null : _useDemo,
                                    icon: const Icon(Icons.bolt_outlined, size: 18),
                                    label: Text(s.tr('authDemoFill')),
                                  ),
                                  Text(
                                    s.tr('authDemoHint'),
                                    textAlign: TextAlign.center,
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      color: scheme.onSurfaceVariant,
                                    ),
                                  ),
                                ],
                                const SizedBox(height: 10),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Text(
                                      _register ? s.tr('authHaveAccount') : s.tr('authNoAccount'),
                                      style: theme.textTheme.bodySmall?.copyWith(
                                        color: scheme.onSurfaceVariant,
                                      ),
                                    ),
                                    TextButton(
                                      onPressed: app.busy ? null : _switchMode,
                                      child: Text(
                                        _register ? s.tr('authSignIn') : s.tr('authSignUp'),
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        )
                            : _challengeView(context),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        s.tr('authTerms'),
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                          height: 1.45,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Glow extends StatelessWidget {
  const _Glow({required this.color, required this.size});

  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [color, color.withValues(alpha: 0)],
          ),
        ),
      ),
    );
  }
}
