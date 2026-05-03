import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Manages user session data.
///
/// Sensitive fields (token, IDs, PII) are stored in encrypted secure storage.
/// Non-sensitive display fields (name, role, credit info) stay in SharedPreferences.
class UserSession {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );

  // ── Secure-storage keys ──────────────────────────────────────────────────
  static const _kAccessToken = 'access_token';
  static const _kUserId = 'user_id';
  static const _kPhone = 'phone';
  static const _kNationalId = 'national_id';
  static const _kDateOfBirth = 'date_of_birth';
  static const _kNinStatus = 'nin_status';

  // ── Write ─────────────────────────────────────────────────────────────────

  static Future<void> saveSession({
    required int userId,
    required String accessToken,
    required String name,
    required String role,
    required String phone,
    required String nationalId,
    required String dateOfBirth,
    required String ninStatus,
    required int creditScore,
    required String creditBand,
    required String creditRating,
    required double defaultingPercentage,
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
    await prefs.setInt('credit_score', creditScore);
    await prefs.setString('credit_band', creditBand);
    await prefs.setString('credit_rating', creditRating);
    await prefs.setDouble('defaulting_percentage', defaultingPercentage);
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

  // ── Read ──────────────────────────────────────────────────────────────────

  static Future<String?> getAccessToken() =>
      _storage.read(key: _kAccessToken);

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
      getUserId(),
      getAccessToken(),
      getPhone(),
      getNationalId(),
      getDateOfBirth(),
      getNinStatus(),
    ]);
    final prefs = await SharedPreferences.getInstance();
    return {
      'user_id': results[0] as int?,
      'access_token': results[1] as String?,
      'phone': results[2] as String?,
      'national_id': results[3] as String?,
      'date_of_birth': results[4] as String?,
      'nin_status': results[5] as String?,
      'name': prefs.getString('name'),
      'role': prefs.getString('role'),
      'credit_score': prefs.getInt('credit_score'),
      'credit_band': prefs.getString('credit_band'),
      'credit_rating': prefs.getString('credit_rating'),
      'defaulting_percentage': prefs.getDouble('defaulting_percentage'),
    };
  }

  // ── Clear ─────────────────────────────────────────────────────────────────

  static Future<void> clear() async {
    await _storage.deleteAll();
    final prefs = await SharedPreferences.getInstance();
    // Preserve onboarding flag; only remove session-specific keys.
    await prefs.remove('name');
    await prefs.remove('role');
    await prefs.remove('credit_score');
    await prefs.remove('credit_band');
    await prefs.remove('credit_rating');
    await prefs.remove('defaulting_percentage');
  }
}
