import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:opfin/services/distribution_channel.dart';

void main() {
  test('Google, Apple and Huawei releases identify their actual channel', () {
    expect(resolveDistributionChannel(configured: '', platform: TargetPlatform.iOS, web: false), 'app_store');
    expect(resolveDistributionChannel(configured: '', platform: TargetPlatform.android, web: false), 'play_store');
    expect(resolveDistributionChannel(configured: 'huawei_appgallery', platform: TargetPlatform.android, web: false), 'huawei_appgallery');
    expect(resolveDistributionChannel(configured: '', platform: TargetPlatform.linux, web: true), 'web');
  });
  test('a native release cannot misdeclare itself as the web channel', () {
    expect(() => resolveDistributionChannel(configured: 'web', platform: TargetPlatform.iOS, web: false), throwsStateError);
    expect(() => resolveDistributionChannel(configured: 'web', platform: TargetPlatform.android, web: false), throwsStateError);
    expect(() => resolveDistributionChannel(configured: 'unknown', platform: TargetPlatform.android, web: false), throwsStateError);
  });
}
