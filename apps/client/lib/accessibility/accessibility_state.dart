import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AccessibilitySettings {
  const AccessibilitySettings({
    this.largeText = false,
    this.reducedMotion = false,
    this.simpleLanguage = true,
    this.highContrast = false,
  });

  final bool largeText;
  final bool reducedMotion;
  final bool simpleLanguage;
  final bool highContrast;

  AccessibilitySettings copyWith({
    bool? largeText,
    bool? reducedMotion,
    bool? simpleLanguage,
    bool? highContrast,
  }) =>
      AccessibilitySettings(
        largeText: largeText ?? this.largeText,
        reducedMotion: reducedMotion ?? this.reducedMotion,
        simpleLanguage: simpleLanguage ?? this.simpleLanguage,
        highContrast: highContrast ?? this.highContrast,
      );
}

abstract final class OpFinAccessibility {
  static final ValueNotifier<AccessibilitySettings> settings =
      ValueNotifier(const AccessibilitySettings());

  static Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    settings.value = AccessibilitySettings(
      largeText: prefs.getBool('opfin_large_text') ?? false,
      reducedMotion: prefs.getBool('opfin_reduced_motion') ?? false,
      simpleLanguage: prefs.getBool('opfin_simple_language') ?? true,
      highContrast: prefs.getBool('opfin_high_contrast') ?? false,
    );
  }

  static Future<void> update(AccessibilitySettings value) async {
    settings.value = value;
    final prefs = await SharedPreferences.getInstance();
    await Future.wait([
      prefs.setBool('opfin_large_text', value.largeText),
      prefs.setBool('opfin_reduced_motion', value.reducedMotion),
      prefs.setBool('opfin_simple_language', value.simpleLanguage),
      prefs.setBool('opfin_high_contrast', value.highContrast),
    ]);
  }
}
