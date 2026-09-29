import 'package:shared_preferences/shared_preferences.dart';

class DeviceContextService {
  static const String _keyOrgId = 'device_org_id';
  static const String _keyOrgCode = 'device_org_code';
  static const String _keyOrgName = 'device_org_name';
  static const String _keyOrgLogo = 'device_org_logo';
  static const String _keyOrgPrimaryColor = 'device_org_primary_color';
  static const String _keyLocationId = 'device_selected_location_id';
  static const String _keyAdminPin = 'device_admin_pin';

  /// Cek apakah perangkat sudah terkonfigurasi dengan organisasi
  static Future<bool> isDeviceConfigured() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.containsKey(_keyOrgId) && prefs.getInt(_keyOrgId) != null;
  }

  /// Mengambil ID Organisasi yang tersimpan di perangkat
  static Future<int?> getOrganizationId() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_keyOrgId);
  }

  /// Mengambil Kode Organisasi yang tersimpan
  static Future<String?> getOrganizationCode() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyOrgCode);
  }

  /// Mengambil Nama Organisasi
  static Future<String?> getOrganizationName() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyOrgName);
  }

  /// Mengambil URL Logo Organisasi
  static Future<String?> getLogoUrl() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyOrgLogo);
  }

  /// Mengambil Warna Primer Organisasi
  static Future<String?> getPrimaryColor() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyOrgPrimaryColor);
  }

  /// Mengambil ID Cabang Terpilih
  static Future<int?> getSelectedLocationId() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_keyLocationId);
  }

  /// Menyimpan Konteks Organisasi ke Perangkat
  static Future<void> saveOrganizationContext({
    required int organizationId,
    required String code,
    required String name,
    String? logoUrl,
    String? primaryColor,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_keyOrgId, organizationId);
    await prefs.setString(_keyOrgCode, code);
    await prefs.setString(_keyOrgName, name);
    if (logoUrl != null && logoUrl.isNotEmpty) {
      await prefs.setString(_keyOrgLogo, logoUrl);
    } else {
      await prefs.remove(_keyOrgLogo);
    }
    if (primaryColor != null && primaryColor.isNotEmpty) {
      await prefs.setString(_keyOrgPrimaryColor, primaryColor);
    }
  }

  /// Menyimpan Cabang Kantor Terpilih
  static Future<void> saveSelectedLocationId(int locationId) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_keyLocationId, locationId);
  }

  /// Reset / Putuskan Hubungan Organisasi dari Perangkat
  static Future<void> clearOrganizationContext() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyOrgId);
    await prefs.remove(_keyOrgCode);
    await prefs.remove(_keyOrgName);
    await prefs.remove(_keyOrgLogo);
    await prefs.remove(_keyOrgPrimaryColor);
    await prefs.remove(_keyLocationId);
  }

  /// Validasi PIN Admin untuk Masuk ke Pengaturan Perangkat (Default: '1234' atau PIN kustom)
  static Future<bool> verifyAdminPin(String inputPin) async {
    final prefs = await SharedPreferences.getInstance();
    final savedPin = prefs.getString(_keyAdminPin) ?? '1234';
    return inputPin.trim() == savedPin;
  }

  /// Mengubah PIN Admin Perangkat
  static Future<void> setAdminPin(String newPin) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyAdminPin, newPin.trim());
  }
}
