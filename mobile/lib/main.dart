import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/app_state.dart';
import 'core/navigation.dart';
import 'core/theme.dart';
import 'l10n/strings.dart';
import 'screens/auth/auth_screen.dart';
import 'screens/shell/home_shell.dart';
import 'widgets/brand.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    ChangeNotifierProvider(
      create: (_) => AppState()..bootstrap(),
      child: const RealityLogApp(),
    ),
  );
}

class RealityLogApp extends StatelessWidget {
  const RealityLogApp({super.key});

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();

    return MaterialApp(
      navigatorKey: appNavigatorKey,
      debugShowCheckedModeBanner: false,
      title: 'RealityLog',
      theme: buildAppTheme(Brightness.light),
      darkTheme: buildAppTheme(Brightness.dark),
      themeMode: app.themeMode,
      locale: Locale(app.locale),
      supportedLocales: S.supported.map(Locale.new).toList(growable: false),
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: !app.bootstrapped
          ? const AppSplashScreen()
          : app.isSignedIn
              ? const HomeShell()
              : const AuthScreen(),
    );
  }
}
