import 'package:flutter/foundation.dart';

/// Distribution is a release setting, never inferred from handset manufacturer.
/// Huawei Android builds set OPFIN_DISTRIBUTION_CHANNEL=huawei_appgallery.
String resolveDistributionChannel({String? configured, TargetPlatform? platform, bool? web}) {
  final requested = configured ?? const String.fromEnvironment('OPFIN_DISTRIBUTION_CHANNEL');
  final isWeb = web ?? kIsWeb;
  final target = platform ?? defaultTargetPlatform;
  if (requested.isNotEmpty) {
    const supported = {'web', 'play_store', 'app_store', 'huawei_appgallery'};
    if (!supported.contains(requested)) {
      throw StateError('Unsupported OpFin distribution channel.');
    }
    if (!isWeb && target == TargetPlatform.iOS && requested != 'app_store') {
      throw StateError('iOS releases must declare the Apple App Store channel.');
    }
    if (!isWeb && target == TargetPlatform.android && !{'play_store', 'huawei_appgallery'}.contains(requested)) {
      throw StateError('Android releases must declare their distribution store.');
    }
    return requested;
  }
  if (isWeb) return 'web';
  return target == TargetPlatform.iOS ? 'app_store' : 'play_store';
}
