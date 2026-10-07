import 'package:flutter/material.dart';

/// GlobalSpace brand colours, sampled from the logo.
class Brand {
  /// Wordmark teal — primary colour.
  static const teal = Color(0xFF0C8481);
  static const tealDark = Color(0xFF0A6B69);

  /// The "G" mark gradient.
  static const orange = Color(0xFFF1892C);
  static const yellow = Color(0xFFEEAE2D);
  static const red = Color(0xFFF6322A);
  static const blue = Color(0xFF2AA4F1);
  static const green = Color(0xFFA7D253);

  /// Header / hero gradient: teal wordmark into the mark's blue.
  static const headerGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [tealDark, teal, Color(0xFF1A9AAE)],
  );

  /// The mark's own sweep (used as a thin accent strip).
  static const markGradient = LinearGradient(colors: [red, orange, yellow, green, blue]);
}

ThemeData buildTheme(Brightness brightness) {
  final dark = brightness == Brightness.dark;

  final scheme = ColorScheme.fromSeed(
    seedColor: Brand.teal,
    brightness: brightness,
  ).copyWith(
    primary: dark ? const Color(0xFF4FC3BE) : Brand.teal,
    onPrimary: dark ? const Color(0xFF003735) : Colors.white,
    secondary: Brand.orange,
    onSecondary: Colors.white,
    tertiary: Brand.blue,
    error: dark ? const Color(0xFFFF8A80) : const Color(0xFFD32F2F),
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: dark ? const Color(0xFF0F1716) : const Color(0xFFF5F8F8),
    appBarTheme: AppBarTheme(
      backgroundColor: dark ? const Color(0xFF0F1716) : Colors.white,
      foregroundColor: dark ? Colors.white : Brand.tealDark,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 1,
      titleTextStyle: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.w700,
        color: dark ? Colors.white : Brand.tealDark,
      ),
    ),
    cardTheme: CardThemeData(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      elevation: 0,
      color: dark ? const Color(0xFF172322) : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: dark ? const Color(0xFF243534) : const Color(0xFFE2ECEB)),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: dark ? const Color(0xFF131D1C) : Colors.white,
      indicatorColor: Brand.teal.withValues(alpha: dark ? 0.35 : 0.14),
      surfaceTintColor: Colors.transparent,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontSize: 12,
          fontWeight: states.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
        ),
      ),
    ),
    floatingActionButtonTheme: const FloatingActionButtonThemeData(
      backgroundColor: Brand.orange,
      foregroundColor: Colors.white,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: scheme.primary, width: 2),
      ),
    ),
    tabBarTheme: TabBarThemeData(
      labelColor: scheme.primary,
      indicatorColor: Brand.orange,
      labelStyle: const TextStyle(fontWeight: FontWeight.w700),
    ),
    chipTheme: ChipThemeData(side: BorderSide.none, backgroundColor: scheme.surfaceContainerHighest),
  );
}

/// The full GlobalSpace logo (wordmark), e.g. on the sign-in screen.
class BrandLogo extends StatelessWidget {
  const BrandLogo({super.key, this.height = 40});

  final double height;

  @override
  Widget build(BuildContext context) => Image.asset('assets/images/logo.png', height: height, fit: BoxFit.contain);
}

/// Just the "G" mark.
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.size = 32});

  final double size;

  @override
  Widget build(BuildContext context) => Image.asset('assets/images/logo_mark.png', width: size, height: size);
}

/// Thin strip in the logo's gradient, used under headers.
class BrandStrip extends StatelessWidget {
  const BrandStrip({super.key, this.height = 3});

  final double height;

  @override
  Widget build(BuildContext context) => Container(height: height, decoration: const BoxDecoration(gradient: Brand.markGradient));
}
