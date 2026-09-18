import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class UserSession {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(migrateWithBackup: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.unlocked),
  );

  static const _kAccessToken = 'access_token';
  static const _kUserId = 'user_id';
  static const _kPhone = 'phone';
  static const _kNationalId = 'national_id';
  static const _kDateOfBirth = 'date_of_birth';
  static const _kNinStatus = 'nin_status';

  static Future<void> saveSession({
    required int userId,
    required String accessToken,
    required String name,
    required String role,
    required String phone,
    String nationalId = '',
    String dateOfBirth = '',
    String ninStatus = '',
    int? creditScore,
    String? creditBand,
    String? creditRating,
    double? defaultingPercentage,
  }) async {
    await Future.wait([
      _storage.write(key: _kAccessToken, value: accessToken),
      _storage.write(key: _kUserId, value: userId.toString()),
      _storage.write(key: _kPhone, value: phone),
      _storage.write(key: _kNationalId, value: nationalId),
      _storage.write(key: _kDateOfBirth, value: dateOfBirth),
      _storage.write(key: _kNinStatus, value: ninStatus),
    ]);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('name', name);
    await prefs.setString('role', role);
    if (creditScore != null) await prefs.setInt('credit_score', creditScore);
    if (creditBand != null) await prefs.setString('credit_band', creditBand);
    if (creditRating != null) await prefs.setString('credit_rating', creditRating);
    if (defaultingPercentage != null) {
      await prefs.setDouble('defaulting_percentage', defaultingPercentage);
    }
  }

  static Future<void> saveAuthPayload(Map<String, dynamic> payload) async {
    final user = (payload['user'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
    await saveSession(
      userId: (user['id'] as num).toInt(),
      accessToken: payload['access_token']?.toString() ?? '',
      name: user['name']?.toString() ?? '',
      role: user['role']?.toString() ?? 'customer',
      phone: user['phone']?.toString() ?? '',
      nationalId: user['national_id']?.toString() ?? '',
      dateOfBirth: user['date_of_birth']?.toString() ?? '',
      ninStatus: user['nin_status']?.toString() ?? '',
    );
  }

  static Future<void> saveNinValidation({
    required String nationalId,
    required String dateOfBirth,
    required String ninStatus,
  }) async {
    await Future.wait([
      _storage.write(key: _kNationalId, value: nationalId),
      _storage.write(key: _kDateOfBirth, value: dateOfBirth),
      _storage.write(key: _kNinStatus, value: ninStatus),
    ]);
  }

  static Future<String?> getAccessToken() => _storage.read(key: _kAccessToken);
  static Future<int?> getUserId() async {
    final val = await _storage.read(key: _kUserId);
    return val != null ? int.tryParse(val) : null;
  }
  static Future<String?> getPhone() => _storage.read(key: _kPhone);
  static Future<String?> getNationalId() => _storage.read(key: _kNationalId);
  static Future<String?> getDateOfBirth() => _storage.read(key: _kDateOfBirth);
  static Future<String?> getNinStatus() => _storage.read(key: _kNinStatus);

  static Future<Map<String, dynamic>> getProfileData() async {
    final results = await Future.wait([
      getUserId(), getAccessToken(), getPhone(),
      getNationalId(), getDateOfBirth(), getNinStatus(),
    ]);
    final prefs = await SharedPreferences.getInstance();
    return {
      'user_id': results[0],
      'access_token': results[1] as String?,
      'phone': results[2] as String?,
      'national_id': results[3] as String?,
      'date_of_birth': results[4] as String?,
      'nin_status': results[5] as String?,
      'name': prefs.getString('name'),
      'role': prefs.getString('role'),
    };
  }

  static Future<void> clear() async {
    await _storage.deleteAll();
    final prefs = await SharedPreferences.getInstance();
    for (final key in [
      'name','role','credit_score','credit_band','credit_rating','defaulting_percentage'
    ]) {
      await prefs.remove(key);
    }
  }
}
