import 'package:flutter/foundation.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../config/supabase_config.dart';
import '../utils/date_time_helper.dart';
import 'location_service.dart';

enum AttendanceStatus {
  checkInSuccess,
  checkOutSuccess,
  alreadyCompleted,
  error,
}

class OfficeLocation {
  final int id;
  final String name;
  final double? latitude;
  final double? longitude;
  final int? radiusMeter;
  final int? organizationId;

  OfficeLocation({
    required this.id,
    required this.name,
    this.latitude,
    this.longitude,
    this.radiusMeter,
    this.organizationId,
  });

  factory OfficeLocation.fromJson(Map<String, dynamic> json) {
    return OfficeLocation(
      id: int.parse((json['lokasi_id'] ?? json['id']).toString()),
      name: json['nama_kantor']?.toString() ?? 'Cabang',
      latitude: double.tryParse(json['latitude']?.toString() ?? ''),
      longitude: double.tryParse(json['longitude']?.toString() ?? ''),
      radiusMeter: int.tryParse(json['radius_meter']?.toString() ?? ''),
      organizationId: json['organization_id'] != null
          ? int.tryParse(json['organization_id'].toString())
          : null,
    );
  }
}

class OrganizationInfo {
  final int id;
  final String name;
  final String code;
  final String? logoUrl;
  final String primaryColorHex;
  final String status;

  OrganizationInfo({
    required this.id,
    required this.name,
    required this.code,
    this.logoUrl,
    required this.primaryColorHex,
    required this.status,
  });
}

class WorkScheduleInfo {
  final String checkInTime;
  final String checkOutTime;

  WorkScheduleInfo({
    required this.checkInTime,
    required this.checkOutTime,
  });
}

class AttendanceResult {
  final AttendanceStatus status;
  final String employeeName;
  final String employeeId;
  final String? profileImageUrl;
  final String checkInTime;
  final String? checkOutTime;
  final String checkInDate;
  final String? duration;
  final String workScheme;
  final String? nfcSerialNumber;

  AttendanceResult({
    required this.status,
    required this.employeeName,
    required this.employeeId,
    this.profileImageUrl,
    required this.checkInTime,
    this.checkOutTime,
    required this.checkInDate,
    this.duration,
    this.workScheme = 'WFO',
    this.nfcSerialNumber,
  });
}

class AttendanceService {
  final SupabaseClient _supabase = Supabase.instance.client;

  /// Validasi Kode Organisasi / Display Token untuk Aktivasi Perangkat
  Future<OrganizationInfo> validateOrganizationCode(String inputCode) async {
    final cleanCode = inputCode.trim();
    if (cleanCode.isEmpty) {
      throw Exception('Silakan masukkan Kode Organisasi atau Token.');
    }

    try {
      // 1. Cari organisasi berdasarkan kode_organisasi atau display_token
      final List<dynamic> orgResults = await _supabase
          .from('organizations')
          .select('organization_id, nama_organisasi, kode_organisasi, status, display_token')
          .or('kode_organisasi.ilike.$cleanCode,display_token.eq.$cleanCode')
          .limit(1);

      if (orgResults.isEmpty) {
        throw Exception('Organisasi dengan kode/token "$cleanCode" tidak ditemukan.');
      }

      final org = orgResults.first;
      final String status = org['status']?.toString().toLowerCase() ?? 'aktif';
      if (status != 'aktif' && status != 'active') {
        throw Exception('Organisasi ini sedang nonaktif. Silakan hubungi Super Admin.');
      }

      final int orgId = int.parse(org['organization_id'].toString());
      final String orgName = org['nama_organisasi']?.toString() ?? 'Organisasi';
      final String orgCode = org['kode_organisasi']?.toString() ?? cleanCode;

      // 2. Ambil branding dari tabel `settings` untuk organisasi ini
      final branding = await fetchCompanyProfile(organizationId: orgId);

      return OrganizationInfo(
        id: orgId,
        name: branding['company_name']?.isNotEmpty == true
            ? branding['company_name']!
            : orgName,
        code: orgCode,
        logoUrl: branding['company_logo'],
        primaryColorHex: branding['primary_color'] ?? '#0891B2',
        status: status,
      );
    } catch (e) {
      if (e is Exception) rethrow;
      debugPrint('Error validateOrganizationCode: $e');
      throw Exception('Gagal memvalidasi kode organisasi. Periksa koneksi internet.');
    }
  }

  /// Mengambil profil perusahaan (nama, logo, primary_color) dari tabel `settings` terisolasi per organisasi
  Future<Map<String, String?>> fetchCompanyProfile({int? organizationId}) async {
    try {
      var query = _supabase.from('settings').select('key, value, organization_id');

      if (organizationId != null) {
        query = query.eq('organization_id', organizationId);
      }

      final List<dynamic> data = await query;

      String? name;
      String? logoUrl;
      String? primaryColorHex = '#0891B2';

      if (data.isNotEmpty) {
        for (var row in data) {
          final key = row['key']?.toString();
          final val = row['value'];
          if (key == 'company_name' && val != null && val.toString().isNotEmpty) {
            name = val.toString();
          } else if (key == 'company_logo' && val != null && val.toString().isNotEmpty) {
            final logoPath = val.toString();
            logoUrl = '${SupabaseConfig.url}/storage/v1/object/public/$logoPath';
          } else if (key == 'primary_color' && val != null && val.toString().isNotEmpty) {
            primaryColorHex = val.toString();
          }
        }
      }

      return {
        'company_name': name,
        'company_logo': logoUrl,
        'primary_color': primaryColorHex,
      };
    } catch (e) {
      debugPrint('Error fetchCompanyProfile: $e');
      return {
        'company_name': null,
        'company_logo': null,
        'primary_color': '#0891B2',
      };
    }
  }

  /// Mengambil daftar seluruh cabang / lokasi kantor aktif dari tabel `lokasi_kantor` terisolasi per organisasi
  Future<List<OfficeLocation>> fetchLocations({int? organizationId}) async {
    try {
      var query = _supabase
          .from('lokasi_kantor')
          .select('lokasi_id, nama_kantor, latitude, longitude, radius_meter, organization_id');

      if (organizationId != null) {
        query = query.eq('organization_id', organizationId);
      }

      final List<dynamic> data = await query.order('lokasi_id', ascending: true);

      if (data.isNotEmpty) {
        return data.map((e) => OfficeLocation.fromJson(e)).toList();
      }
    } catch (e) {
      debugPrint('Error fetchLocations: $e');
    }

    return [];
  }

  /// Mengambil jadwal kerja aktif (jam masuk & jam pulang) untuk organisasi
  Future<WorkScheduleInfo?> fetchWorkSchedule({int? organizationId}) async {
    try {
      var query = _supabase
          .from('jadwal_kerja')
          .select('jam_masuk, jam_pulang, organization_id');

      if (organizationId != null) {
        query = query.eq('organization_id', organizationId);
      }

      final List<dynamic> data = await query
          .order('tanggal_berlaku', ascending: false)
          .limit(1);

      if (data.isNotEmpty) {
        final row = data.first;
        final rawMasuk = row['jam_masuk']?.toString() ?? '08:00';
        final rawPulang = row['jam_pulang']?.toString() ?? '17:00';

        String formatTime(String timeStr) {
          final parts = timeStr.split(':');
          if (parts.length >= 2) {
            return '${parts[0].padLeft(2, '0')}:${parts[1].padLeft(2, '0')}';
          }
          return timeStr;
        }

        return WorkScheduleInfo(
          checkInTime: formatTime(rawMasuk),
          checkOutTime: formatTime(rawPulang),
        );
      }
    } catch (e) {
      debugPrint('Error fetchWorkSchedule: $e');
    }
    return null;
  }

  /// Stream Realtime untuk sinkronisasi otomatis cabang dari Supabase per organisasi
  Stream<List<OfficeLocation>> streamLocations({int? organizationId}) {
    final stream = _supabase.from('lokasi_kantor').stream(primaryKey: ['lokasi_id']);
    
    return stream.map((data) {
      var filtered = data;
      if (organizationId != null) {
        filtered = data
            .where((e) =>
                e['organization_id'] != null &&
                int.tryParse(e['organization_id'].toString()) == organizationId)
            .toList();
      }
      return filtered.map((e) => OfficeLocation.fromJson(e)).toList();
    });
  }

  /// Memproses presensi kartu NFC dengan isolasi organisasi ketat
  Future<AttendanceResult> processNfcTap(
    String nfcSerialNumber, {
    int? organizationId,
    OfficeLocation? selectedLocation,
  }) async {
    // 1. Identifikasi kartu di tabel `nfc`
    final nfcData = await _supabase
        .from('nfc')
        .select('pegawai_id')
        .eq('nfc_serial_number', nfcSerialNumber)
        .maybeSingle();

    if (nfcData == null || nfcData['pegawai_id'] == null) {
      throw Exception('Kartu ($nfcSerialNumber) tidak terdaftar');
    }

    final int pegawaiId = int.parse(nfcData['pegawai_id'].toString());

    // 2. Ambil data profil pegawai dari tabel `pegawai` (termasuk organization_id)
    dynamic pegawaiData;
    try {
      pegawaiData = await _supabase
          .from('pegawai')
          .select('pegawai_id, nama_pegawai, nip, status, foto_profile, organization_id')
          .eq('pegawai_id', pegawaiId)
          .maybeSingle();
    } catch (e) {
      debugPrint('Error query pegawai: $e');
      throw Exception('Data pegawai gagal diperoleh, silakan coba lagi');
    }

    if (pegawaiData == null) {
      throw Exception('Data pegawai ($nfcSerialNumber) tidak ditemukan');
    }

    // 🔒 ISOLASI DATA MULTI-ORGANISASI:
    // Validasi apakah pegawai milik organisasi yang aktif di perangkat ini
    if (organizationId != null && pegawaiData['organization_id'] != null) {
      final int employeeOrgId = int.parse(pegawaiData['organization_id'].toString());
      if (employeeOrgId != organizationId) {
        throw Exception('Kartu ini terdaftar pada organisasi lain. Presensi ditolak.');
      }
    }

    // Validasi status pegawai (Harus Aktif)
    final String? employeeStatus = pegawaiData['status']?.toString().trim();
    if (employeeStatus == null || employeeStatus.toLowerCase() != 'aktif') {
      throw Exception('Akun pegawai kartu ($nfcSerialNumber) tidak aktif. Presensi ditolak.');
    }

    final String employeeName = pegawaiData['nama_pegawai']?.toString() ?? 'Pegawai';
    final String employeeNip = pegawaiData['nip']?.toString() ?? 'N/A';
    final String? rawFoto = pegawaiData['foto_profile']?.toString();
    final String? profileImageUrl = (rawFoto != null && rawFoto.isNotEmpty)
        ? '${SupabaseConfig.url}/storage/v1/object/public/$rawFoto'
        : null;

    final now = DateTime.now();
    final String todayDateIso = DateTimeHelper.formatDateIso(now);
    final String todayFormatted = DateTimeHelper.formatDateIndonesian(now);
    final String currentTimeFormatted = DateTimeHelper.formatTime(now);

    // Validasi & Ambil koordinat GPS nyata dari sensor perangkat saat ini (Wajib GPS Nyata)
    final deviceGps = await LocationService.getCurrentPosition(strict: true);
    if (deviceGps == null) {
      throw Exception('Gagal mendapatkan koordinat lokasi perangkat. Silakan coba lagi.');
    }
    final double currentLatitude = deviceGps.latitude;
    final double currentLongitude = deviceGps.longitude;

    // 3. Cek sesi absensi pada HARI INI (ambil sesi terbaru)
    final existingAttendance = await _supabase
        .from('absensi')
        .select()
        .eq('pegawai_id', pegawaiId)
        .eq('tanggal_absensi', todayDateIso)
        .order('jam_checkin', ascending: false)
        .limit(1)
        .maybeSingle();

    final String branchName = selectedLocation?.name ?? 'Kantor';
    final int? locationId = selectedLocation?.id;

    // 4. Tentukan Alur Transaksi Multi-Session (Check In / Check Out Berulang)
    if (existingAttendance == null || existingAttendance['jam_checkout'] != null) {
      // KONDISI 1: Belum ada absensi hari ini ATAU sesi sebelumnya sudah check-out
      // -> Lakukan CHECK-IN SESI BARU (INSERT)
      int? jadwalId;
      try {
        var jadwalQuery = _supabase.from('jadwal_kerja').select('jadwal_id');
        if (organizationId != null) {
          jadwalQuery = jadwalQuery.eq('organization_id', organizationId);
        }
        final jadwal = await jadwalQuery
            .order('tanggal_berlaku', ascending: false)
            .limit(1)
            .maybeSingle();
        if (jadwal != null && jadwal['jadwal_id'] != null) {
          jadwalId = int.tryParse(jadwal['jadwal_id'].toString());
        }
      } catch (_) {}

      final String checkInNote =
          'Check-in via Public Mobile App di $branchName (UID NFC: $nfcSerialNumber)';

      final insertPayload = {
        'pegawai_id': pegawaiId,
        'tanggal_absensi': todayDateIso,
        'jam_checkin': now.toIso8601String(),
        'skema_kerja': 'WFO',
        'status_kehadiran': 'Hadir',
        'catatan': checkInNote,
        'latitude': currentLatitude,
        'longitude': currentLongitude,
        if (locationId != null) 'lokasi_id': locationId,
        if (jadwalId != null) 'jadwal_id': jadwalId,
      };

      try {
        await _supabase.from('absensi').insert(insertPayload);
      } catch (e) {
        debugPrint('Error insert check-in: $e');
        throw Exception('Check In gagal disimpan, silakan coba lagi');
      }

      return AttendanceResult(
        status: AttendanceStatus.checkInSuccess,
        employeeName: employeeName,
        employeeId: employeeNip,
        profileImageUrl: profileImageUrl,
        checkInTime: currentTimeFormatted,
        checkInDate: todayFormatted,
        workScheme: 'WFO',
        nfcSerialNumber: nfcSerialNumber,
      );
    } else {
      // KONDISI 2: Sedang ada sesi aktif yang belum check-out (jam_checkout masih NULL)
      // -> Lakukan CHECK-OUT PADA SESI TERSEBUT (UPDATE)
      final DateTime checkInDateTime = DateTime.tryParse(
            existingAttendance['jam_checkin']?.toString() ?? '',
          ) ??
          now;

      final durationText = DateTimeHelper.calculateDuration(checkInDateTime, now);
      final String existingNote = existingAttendance['catatan']?.toString() ??
          'Check-in via Public Mobile App';
      final String checkOutNote =
          '$existingNote | Check-out via Public Mobile App di $branchName (Durasi: $durationText)';

      try {
        await _supabase
            .from('absensi')
            .update({
              'jam_checkout': now.toIso8601String(),
              'catatan': checkOutNote,
              'latitude_checkout': currentLatitude,
              'longitude_checkout': currentLongitude,
            })
            .eq('absensi_id', existingAttendance['absensi_id']);
      } catch (e) {
        debugPrint('Error update check-out: $e');
        throw Exception('Check Out gagal disimpan, silakan coba lagi');
      }

      return AttendanceResult(
        status: AttendanceStatus.checkOutSuccess,
        employeeName: employeeName,
        employeeId: employeeNip,
        profileImageUrl: profileImageUrl,
        checkInTime: DateTimeHelper.formatTime(checkInDateTime),
        checkOutTime: currentTimeFormatted,
        checkInDate: todayFormatted,
        duration: durationText,
        workScheme: existingAttendance['skema_kerja']?.toString() ?? 'WFO',
        nfcSerialNumber: nfcSerialNumber,
      );
    }
  }
}
