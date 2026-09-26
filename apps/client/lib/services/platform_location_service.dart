import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class PlatformLocationService {
  static const MethodChannel _channel = MethodChannel('co.opfin/location');

  static bool get supportsPreciseDeviceLocation =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.iOS;

  static Future<Map<String, dynamic>> currentLocation({
    String precision = 'approximate',
  }) async {
    if (kIsWeb) {
      throw Exception('Device location is not available through this mobile bridge.');
    }

    try {
      final result = await _channel.invokeMapMethod<String, dynamic>(
        'currentLocation',
        {'precision': supportsPreciseDeviceLocation ? precision : 'approximate'},
      );
      return result ?? <String, dynamic>{};
    } on PlatformException catch (error) {
      throw Exception(error.message ?? 'Unable to read the current location.');
    }
  }

  static Future<void> openMaps(String url) async {
    if (url.trim().isEmpty) return;
    if (kIsWeb) {
      throw Exception('Open this map link in your browser: $url');
    }

    try {
      await _channel.invokeMethod<bool>('openMaps', {'url': url});
    } on PlatformException catch (error) {
      throw Exception(error.message ?? 'Unable to open the map.');
    }
  }
}
