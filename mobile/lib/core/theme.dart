import 'package:flutter/material.dart';

abstract final class AppColors {
  static const ink = Color(0xFF211E19);
  static const paper = Color(0xFFF7F2E8);
  static const surface = Color(0xFFFFFDF8);
  static const terracotta = Color(0xFFB94B35);
  static const moss = Color(0xFF517A64);
  static const gold = Color(0xFFB8862F);
  static const darkInk = Color(0xFFF2ECE0);
  static const darkPaper = Color(0xFF151311);
  static const darkSurface = Color(0xFF1F1B18);
}

ThemeData buildAppTheme(Brightness brightness) {
  final isDark = brightness == Brightness.dark;
  final base = ColorScheme.fromSeed(
    seedColor: AppColors.terracotta,
    brightness: brightness,
  );

  final scheme = base.copyWith(
    primary: isDark ? const Color(0xFFF2A08C) : AppColors.terracotta,
    onPrimary: isDark ? const Color(0xFF3A0B02) : Colors.white,
    primaryContainer: isDark ? const Color(0xFF5C2217) : const Color(0xFFF8DED6),
    onPrimaryContainer: isDark ? const Color(0xFFFFDAD0) : const Color(0xFF3F0D03),
    secondary: isDark ? const Color(0xFFA7CDB7) : AppColors.moss,
    onSecondary: isDark ? const Color(0xFF123525) : Colors.white,
    secondaryContainer: isDark ? const Color(0xFF2C4A3B) : const Color(0xFFDDEBE2),
    onSecondaryContainer: isDark ? const Color(0xFFC4E8D3) : const Color(0xFF17382A),
    tertiary: isDark ? const Color(0xFFE8C47C) : AppColors.gold,
    onTertiary: isDark ? const Color(0xFF3C2A00) : Colors.white,
    surface: isDark ? AppColors.darkSurface : AppColors.surface,
    onSurface: isDark ? AppColors.darkInk : AppColors.ink,
    surfaceContainerLowest: isDark ? const Color(0xFF100E0C) : const Color(0xFFFFFCF5),
    surfaceContainerLow: isDark ? const Color(0xFF1B1815) : const Color(0xFFFBF6EC),
    surfaceContainer: isDark ? const Color(0xFF241F1B) : const Color(0xFFF3ECDF),
    surfaceContainerHigh: isDark ? const Color(0xFF2C2621) : const Color(0xFFEDE4D5),
    surfaceContainerHighest: isDark ? const Color(0xFF352E28) : const Color(0xFFE7DCCB),
    outline: isDark ? const Color(0xFF6E6257) : const Color(0xFFB5A797),
    outlineVariant: isDark ? const Color(0xFF4B423A) : const Color(0xFFD8CCBC),
  );

  final textTheme = ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
  ).textTheme.apply(
        bodyColor: scheme.onSurface,
        displayColor: scheme.onSurface,
      );

  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: scheme,
    scaffoldBackgroundColor: isDark ? AppColors.darkPaper : AppColors.paper,
    textTheme: textTheme.copyWith(
      headlineLarge: textTheme.headlineLarge?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: -0.8,
        height: 1.15,
      ),
      headlineMedium: textTheme.headlineMedium?.copyWith(
        fontWeight: FontWeight.w700,
        letterSpacing: -0.4,
        height: 1.2,
      ),
      titleLarge: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
      titleMedium: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
      bodyLarge: textTheme.bodyLarge?.copyWith(height: 1.55),
      bodyMedium: textTheme.bodyMedium?.copyWith(height: 1.5),
    ),
    appBarTheme: AppBarThemeData(
      backgroundColor: isDark ? AppColors.darkPaper : AppColors.paper,
      foregroundColor: scheme.onSurface,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: textTheme.titleLarge?.copyWith(
        color: scheme.onSurface,
        fontWeight: FontWeight.w700,
      ),
    ),
    cardTheme: CardThemeData(
      color: scheme.surface,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
        side: BorderSide(color: scheme.outlineVariant.withValues(alpha: 0.65)),
      ),
    ),
    inputDecorationTheme: InputDecorationThemeData(
      filled: true,
      fillColor: scheme.surfaceContainerHighest.withValues(alpha: isDark ? 0.35 : 0.45),
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide.none,
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(color: scheme.outlineVariant.withValues(alpha: 0.55)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(color: scheme.primary, width: 1.6),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide(color: scheme.error),
      ),
      labelStyle: TextStyle(color: scheme.onSurfaceVariant),
      hintStyle: TextStyle(color: scheme.onSurfaceVariant.withValues(alpha: 0.75)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(0, 52),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        textStyle: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 50),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        side: BorderSide(color: scheme.outlineVariant),
        textStyle: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        textStyle: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: isDark ? AppColors.darkSurface : AppColors.surface,
      surfaceTintColor: Colors.transparent,
      indicatorColor: scheme.primaryContainer,
      height: 72,
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      labelTextStyle: WidgetStatePropertyAll(
        textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w600),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: scheme.surfaceContainerHigh,
      selectedColor: scheme.primaryContainer,
      side: BorderSide.none,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      labelStyle: textTheme.labelLarge,
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    ),
    dividerTheme: DividerThemeData(
      color: scheme.outlineVariant.withValues(alpha: 0.55),
      space: 1,
      thickness: 1,
    ),
  );
}
