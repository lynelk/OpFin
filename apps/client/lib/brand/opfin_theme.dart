import 'package:flutter/material.dart';
import 'brand_colors.dart';

abstract final class OpFinTheme {
  static ThemeData get light {
    const scheme = ColorScheme.light(
      primary: OpFinColors.indigo,
      onPrimary: OpFinColors.white,
      primaryContainer: OpFinColors.periwinkle,
      onPrimaryContainer: OpFinColors.indigoStrong,
      secondary: OpFinColors.apricot,
      onSecondary: OpFinColors.ink,
      secondaryContainer: OpFinColors.ivory,
      onSecondaryContainer: OpFinColors.ink,
      surface: OpFinColors.white,
      onSurface: OpFinColors.ink,
      onSurfaceVariant: OpFinColors.muted,
      outline: OpFinColors.muted,
      outlineVariant: OpFinColors.line,
      error: OpFinColors.danger,
      onError: OpFinColors.white,
    );
    final base = ThemeData(
      useMaterial3: true,
      fontFamily: 'Inter',
      colorScheme: scheme,
      scaffoldBackgroundColor: OpFinColors.ivory,
      primaryColor: OpFinColors.indigo,
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
    );
    return base.copyWith(
      textTheme: base.textTheme.copyWith(
        bodyLarge: const TextStyle(fontFamily: 'Inter', fontSize: 16, height: 1.5, color: OpFinColors.ink),
        bodyMedium: const TextStyle(fontFamily: 'Inter', fontSize: 14, height: 1.45, color: OpFinColors.ink),
        titleLarge: const TextStyle(fontFamily: 'Inter', fontSize: 24, height: 1.3, fontWeight: FontWeight.w700, color: OpFinColors.indigo),
        labelLarge: const TextStyle(fontFamily: 'Inter', fontSize: 14, fontWeight: FontWeight.w600),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: OpFinColors.ivory,
        foregroundColor: OpFinColors.ink,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        titleTextStyle: TextStyle(fontFamily: 'Inter', fontSize: 20, height: 1.3, fontWeight: FontWeight.w700, color: OpFinColors.indigo),
      ),
      cardTheme: CardThemeData(
        color: OpFinColors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: OpFinColors.line)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: OpFinColors.white,
        labelStyle: const TextStyle(color: OpFinColors.muted),
        hintStyle: const TextStyle(color: OpFinColors.muted),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: OpFinColors.muted)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: OpFinColors.indigo, width: 2)),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(style: ElevatedButton.styleFrom(
        backgroundColor: OpFinColors.indigo,
        foregroundColor: OpFinColors.white,
        minimumSize: const Size(48, 48),
        elevation: 0,
        textStyle: const TextStyle(fontFamily: 'Inter', fontSize: 16, fontWeight: FontWeight.w600),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      )),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(
        backgroundColor: OpFinColors.indigo, foregroundColor: OpFinColors.white, minimumSize: const Size(48, 48),
      )),
      outlinedButtonTheme: OutlinedButtonThemeData(style: OutlinedButton.styleFrom(
        foregroundColor: OpFinColors.indigo, minimumSize: const Size(48, 48), side: const BorderSide(color: OpFinColors.indigo),
      )),
      textButtonTheme: TextButtonThemeData(style: TextButton.styleFrom(
        foregroundColor: OpFinColors.indigo, minimumSize: const Size(48, 48),
      )),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: OpFinColors.white,
        selectedItemColor: OpFinColors.indigo,
        unselectedItemColor: OpFinColors.muted,
        selectedLabelStyle: TextStyle(fontFamily: 'Inter', fontWeight: FontWeight.w600),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: OpFinColors.white, indicatorColor: OpFinColors.periwinkle,
      ),
      dividerColor: OpFinColors.line,
      iconTheme: const IconThemeData(color: OpFinColors.indigo),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: OpFinColors.indigo),
    );
  }
  static ThemeData get highContrastLight {
    final base = light;
    const scheme = ColorScheme.light(
      primary: OpFinColors.indigoStrong,
      onPrimary: OpFinColors.white,
      primaryContainer: OpFinColors.white,
      onPrimaryContainer: OpFinColors.ink,
      secondary: OpFinColors.ink,
      onSecondary: OpFinColors.white,
      secondaryContainer: OpFinColors.white,
      onSecondaryContainer: OpFinColors.ink,
      surface: OpFinColors.white,
      onSurface: OpFinColors.ink,
      onSurfaceVariant: OpFinColors.ink,
      outline: OpFinColors.ink,
      outlineVariant: OpFinColors.muted,
      error: OpFinColors.danger,
      onError: OpFinColors.white,
    );

    return base.copyWith(
      colorScheme: scheme,
      scaffoldBackgroundColor: OpFinColors.white,
      dividerColor: OpFinColors.ink,
      focusColor: OpFinColors.apricot,
      cardTheme: CardThemeData(
        color: OpFinColors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: OpFinColors.ink, width: 2),
        ),
      ),
      inputDecorationTheme: base.inputDecorationTheme.copyWith(
        labelStyle: const TextStyle(color: OpFinColors.ink),
        hintStyle: const TextStyle(color: OpFinColors.ink),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: OpFinColors.ink, width: 2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: OpFinColors.indigoStrong, width: 3),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: OpFinColors.indigoStrong,
          minimumSize: const Size(48, 48),
          side: const BorderSide(color: OpFinColors.ink, width: 2),
        ),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: OpFinColors.white,
        indicatorColor: OpFinColors.apricot,
      ),
    );
  }
}

class OpFinSymbol extends StatelessWidget {
  const OpFinSymbol({super.key, this.size = 48, this.reverse = false});
  final double size;
  final bool reverse;

  @override
  Widget build(BuildContext context) => Image.asset(
    reverse ? 'assets/brand/opfin-symbol-reverse.png' : 'assets/brand/opfin-symbol.png',
    width: size,
    height: size,
    fit: BoxFit.contain,
    semanticLabel: 'OpFin',
  );
}
