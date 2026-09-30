import 'dart:async';
import 'package:flutter/material.dart';
import 'package:nfc_manager/nfc_manager.dart';

import '../../core/services/attendance_service.dart';
import '../../core/services/device_context_service.dart';
import '../../core/services/location_service.dart';
import '../../core/services/network_service.dart';
import '../../core/services/tts_service.dart';
import '../../core/utils/color_helper.dart';
import '../activation/device_activation_screen.dart';
import '../attendance/checkout_success_screen.dart';
import '../attendance/success_screen.dart';
import 'views/branch_selection_view.dart';
import 'widgets/clock_widget.dart';
import 'widgets/company_header.dart';
import 'widgets/location_picker_badge.dart';
import 'widgets/nfc_scan_area.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  final AttendanceService _attendanceService = AttendanceService();
  final TtsService _ttsService = TtsService();

  // Status Konfigurasi Organisasi Perangkat
  bool _isCheckingConfig = true;
  bool _isConfigured = false;
  int? _organizationId;
  String? _organizationCode;

  String _companyName = 'Sistem Absensi';
  String? _logoUrl;
  Color _primaryColor = const Color(0xFF0891B2);
  bool _isLoadingProfile = true;

  // Jadwal Kerja Resmi Organisasi
  WorkScheduleInfo? _workSchedule;

  // Daftar Cabang / Lokasi Kantor Dinamis (terisolasi per organisasi)
  List<OfficeLocation> _locations = [];
  OfficeLocation? _selectedLocation;
  bool _isLoadingLocations = false;
  StreamSubscription<List<OfficeLocation>>? _locationSubscription;
  bool _isOnline = true;
  StreamSubscription<bool>? _networkSubscription;
  bool _isNfcAvailable = true;

  // Double-scan protection flag
  bool _isProcessing = false;

  // Cooldown 5 detik per nomor seri kartu
  final Map<String, DateTime> _cardCooldowns = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    _ttsService.init();
    _checkInitialNetwork();
    _networkSubscription = NetworkService.onConnectivityChanged.listen((online) {
      if (mounted) setState(() => _isOnline = online);
    });

    _checkDeviceConfiguration();
    _initNfcListener();
  }

  Future<void> _checkInitialNetwork() async {
    final online = await NetworkService.hasInternetConnection();
    if (mounted) setState(() => _isOnline = online);
  }

  /// Memeriksa apakah perangkat sudah diaktivasi dengan organisasi tertentu
  Future<void> _checkDeviceConfiguration() async {
    final configured = await DeviceContextService.isDeviceConfigured();
    if (!configured) {
      if (mounted) {
        setState(() {
          _isConfigured = false;
          _isCheckingConfig = false;
        });
      }
      return;
    }

    final orgId = await DeviceContextService.getOrganizationId();
    final orgCode = await DeviceContextService.getOrganizationCode();
    final savedName = await DeviceContextService.getOrganizationName();
    final savedLogo = await DeviceContextService.getLogoUrl();
    final savedColor = await DeviceContextService.getPrimaryColor();
    final savedLocId = await DeviceContextService.getSelectedLocationId();

    if (mounted) {
      setState(() {
        _isConfigured = true;
        _isLoadingLocations = true;
        _isCheckingConfig = false;
        _organizationId = orgId;
        _organizationCode = orgCode;
        if (savedName != null) _companyName = savedName;
        _logoUrl = savedLogo;
        if (savedColor != null) {
          _primaryColor = ColorHelper.parseHexColor(
            savedColor,
            defaultColor: const Color(0xFF0891B2),
          );
        }
      });
    }

    // Muat data profil, jadwal kerja & cabang dari Supabase dengan isolasi organization_id
    await _loadCompanyProfile();
    await _loadWorkSchedule();
    await _loadLocations(initialSelectedId: savedLocId);
    _subscribeLocationUpdates();
  }

  /// Dipanggil saat aktivasi organisasi di DeviceActivationScreen sukses
  void _onActivationSuccess(OrganizationInfo info) {
    setState(() {
      _isConfigured = true;
      _isLoadingLocations = true;
      _organizationId = info.id;
      _organizationCode = info.code;
      _companyName = info.name;
      _logoUrl = info.logoUrl;
      _primaryColor = ColorHelper.parseHexColor(
        info.primaryColorHex,
        defaultColor: const Color(0xFF0891B2),
      );
      _isLoadingProfile = false;
      _selectedLocation = null;
    });

    _loadCompanyProfile();
    _loadWorkSchedule();
    _loadLocations();
    _subscribeLocationUpdates();
  }

  Future<void> _loadWorkSchedule() async {
    if (_organizationId == null) return;
    final schedule = await _attendanceService.fetchWorkSchedule(
      organizationId: _organizationId,
    );
    if (mounted) {
      setState(() {
        _workSchedule = schedule;
      });
    }
  }

  Future<void> _loadCompanyProfile() async {
    if (_organizationId == null) return;

    final profile = await _attendanceService.fetchCompanyProfile(
      organizationId: _organizationId,
    );

    if (mounted) {
      setState(() {
        if (profile['company_name'] != null && profile['company_name']!.isNotEmpty) {
          _companyName = profile['company_name']!;
        }
        _logoUrl = profile['company_logo'];
        _primaryColor = ColorHelper.parseHexColor(
          profile['primary_color'],
          defaultColor: const Color(0xFF0891B2),
        );
        _isLoadingProfile = false;
      });

      // Perbarui cache local
      await DeviceContextService.saveOrganizationContext(
        organizationId: _organizationId!,
        code: _organizationCode ?? '',
        name: _companyName,
        logoUrl: _logoUrl,
        primaryColor: profile['primary_color'],
      );
    }
  }

  Future<void> _loadLocations({int? initialSelectedId}) async {
    if (_organizationId == null) {
      if (mounted) {
        setState(() => _isLoadingLocations = false);
      }
      return;
    }

    if (mounted) {
      setState(() => _isLoadingLocations = true);
    }

    final locs = await _attendanceService.fetchLocations(
      organizationId: _organizationId,
    );

    if (mounted) {
      setState(() {
        _locations = locs;
        _isLoadingLocations = false;
        if (initialSelectedId != null) {
          _selectedLocation = locs.cast<OfficeLocation?>().firstWhere(
                (l) => l?.id == initialSelectedId,
                orElse: () => locs.isNotEmpty ? locs.first : null,
              );
        }
      });
    }
  }

  void _subscribeLocationUpdates() {
    if (_organizationId == null) return;

    try {
      _locationSubscription?.cancel();
      _locationSubscription = _attendanceService
          .streamLocations(organizationId: _organizationId)
          .listen(
        (locs) {
          if (mounted && locs.isNotEmpty) {
            setState(() {
              _locations = locs;
              if (_selectedLocation != null &&
                  !locs.any((l) => l.id == _selectedLocation!.id)) {
                _selectedLocation = locs.first;
              }
            });
          }
        },
        onError: (e) {
          debugPrint('Realtime stream error lokasi_kantor: $e');
        },
      );
    } catch (e) {
      debugPrint('Error subscribe lokasi_kantor: $e');
    }
  }

  Future<void> _initNfcListener() async {
    try {
      final isAvailable = await NfcManager.instance.isAvailable();
      if (mounted) {
        setState(() {
          _isNfcAvailable = isAvailable;
        });
      }
      if (!isAvailable) {
        debugPrint('NFC hardware tidak aktif / tersedia pada perangkat ini');
        Future.delayed(const Duration(milliseconds: 600), () {
          if (mounted && !_isNfcAvailable) {
            _ttsService.speakNfcDisabled();
          }
        });
        return;
      }

      NfcManager.instance.startSession(
        pollingOptions: {
          NfcPollingOption.iso14443,
          NfcPollingOption.iso15693,
          NfcPollingOption.iso18092,
        },
        onDiscovered: (NfcTag tag) async {
          String nfcId = '';
          try {
            // ignore: invalid_use_of_protected_member
            final dynamic pigeonTag = tag.data;
            if (pigeonTag != null && pigeonTag.id != null) {
              final List<int> idList = List<int>.from(pigeonTag.id);
              nfcId = idList
                  .map((e) => e.toRadixString(16).padLeft(2, '0').toUpperCase())
                  .join(':');
            }
          } catch (e) {
            debugPrint('Error parse NFC tag: $e');
          }

          if (nfcId.isNotEmpty) {
            _handleNfcAttendance(nfcId);
          }
        },
      );
    } catch (e) {
      debugPrint('Error inisialisasi NFC Session: $e');
      if (mounted) {
        setState(() {
          _isNfcAvailable = false;
        });
      }
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _initNfcListener();
      _checkInitialNetwork();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _locationSubscription?.cancel();
    _networkSubscription?.cancel();
    NfcManager.instance.stopSession();
    _ttsService.stop();
    super.dispose();
  }

  /// Memproses alur absensi NFC dengan isolasi organisasi ketat
  Future<void> _handleNfcAttendance(String nfcSerialNumber) async {
    final now = DateTime.now();

    // Cooldown 5 detik khusus nomor seri kartu ini
    if (_cardCooldowns.containsKey(nfcSerialNumber)) {
      final lastTap = _cardCooldowns[nfcSerialNumber]!;
      if (now.difference(lastTap).inMilliseconds < 5000) {
        debugPrint('Kartu $nfcSerialNumber masih dalam masa cooldown');
        return;
      }
    }

    if (_isProcessing) return;

    // 1. CEK STATUS INTERNET PROAKTIF
    if (!_isOnline) {
      _ttsService.speakNoInternet();
      if (mounted) {
        _showNoInternetAlert();
      }
      return;
    }

    // 2. CEK STATUS GPS (Wajib Aktif)
    final bool isGpsOn = await LocationService.isLocationEnabled();
    if (!isGpsOn) {
      _ttsService.speakLocationDisabled();
      if (mounted) {
        _showLocationDisabledAlert();
      }
      return;
    }

    _cardCooldowns[nfcSerialNumber] = now;
    setState(() => _isProcessing = true);

    try {
      final result = await _attendanceService.processNfcTap(
        nfcSerialNumber,
        organizationId: _organizationId,
        selectedLocation: _selectedLocation,
      );

      if (!mounted) return;

      debugPrint('[NFC SCAN] Converted Hex UID: $nfcSerialNumber');

      if (result.status == AttendanceStatus.checkInSuccess) {
        _ttsService.speakCheckIn(result.employeeName);

        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => SuccessScreen(
              employeeName: result.employeeName,
              employeeId: result.employeeId,
              checkInTime: result.checkInTime,
              checkInDate: result.checkInDate,
              status: result.workScheme,
              profileImageUrl: result.profileImageUrl,
              nfcSerialNumber: result.nfcSerialNumber ?? nfcSerialNumber,
            ),
          ),
        );
      } else if (result.status == AttendanceStatus.checkOutSuccess) {
        _ttsService.speakCheckOut(result.employeeName);

        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => CheckoutSuccessScreen(
              employeeName: result.employeeName,
              employeeId: result.employeeId,
              checkInTime: result.checkInTime,
              checkOutTime: result.checkOutTime ?? '-',
              duration: result.duration ?? '-',
              profileImageUrl: result.profileImageUrl,
              nfcSerialNumber: result.nfcSerialNumber ?? nfcSerialNumber,
            ),
          ),
        );
      } else if (result.status == AttendanceStatus.alreadyCompleted) {
        _ttsService.speakAlreadyCompleted(result.employeeName);

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFF0F172A),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            margin: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
            duration: const Duration(seconds: 3),
            content: Row(
              children: [
                const Icon(Icons.check_circle_outline_rounded, color: Color(0xFF10B981)),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    '${result.employeeName}, absensi hari ini sudah selesai.',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
        await Future.delayed(const Duration(seconds: 3));
      }
    } catch (e) {
      debugPrint('Error proses absensi: $e');
      if (mounted) {
        final rawError = e.toString().replaceAll('Exception:', '').trim();
        final bool isNoInternet = rawError.contains('SocketException') ||
            rawError.contains('ClientException') ||
            rawError.toLowerCase().contains('failed host lookup') ||
            rawError.toLowerCase().contains('network') ||
            rawError.toLowerCase().contains('connection') ||
            rawError.toLowerCase().contains('koneksi internet') ||
            rawError.toLowerCase().contains('koneksi terputus');
        final bool isGpsDisabled = e is LocationDisabledException ||
            rawError.contains('Location belum aktif') ||
            rawError.toLowerCase().contains('location belum aktif');
        final bool isPermissionDenied = e is LocationPermissionDeniedException ||
            rawError.contains('Izin akses lokasi');
        final bool isGpsFetchFailed = rawError.contains('Gagal mendapatkan koordinat') ||
            rawError.contains('koordinat lokasi');
        final bool isCheckInSaveFailed = rawError.contains('Check In gagal disimpan');
        final bool isCheckOutSaveFailed = rawError.contains('Check Out gagal disimpan');
        final bool isCardNotFound = rawError.contains('tidak terdaftar');
        final bool isOtherOrg = rawError.contains('organisasi lain');
        final bool isInactiveAccount = rawError.contains('tidak aktif');
        final bool isEmployeeFetchError = rawError.contains('Data pegawai') ||
            rawError.contains('gagal diperoleh') ||
            rawError.contains('tidak ditemukan') ||
            rawError.contains('PostgrestException') ||
            rawError.contains('PGRST');

        String displayMsg = rawError;

        if (isNoInternet) {
          _ttsService.speakNoInternet();
          _showNoInternetAlert();
          return;
        } else if (isGpsDisabled) {
          _ttsService.speakLocationDisabled();
          _showLocationDisabledAlert();
          return;
        } else if (isPermissionDenied) {
          _ttsService.speak('Izin akses lokasi belum diberikan.');
          displayMsg = 'Izin akses lokasi belum diberikan pada perangkat.';
        } else if (isGpsFetchFailed) {
          _ttsService.speakLocationFetchFailed();
          displayMsg = 'Gagal mendapatkan koordinat lokasi perangkat. Silakan coba lagi.';
        } else if (isCheckInSaveFailed) {
          _ttsService.speakCheckInSaveFailed();
          displayMsg = 'Check In gagal disimpan, silakan coba lagi.';
        } else if (isCheckOutSaveFailed) {
          _ttsService.speakCheckOutSaveFailed();
          displayMsg = 'Check Out gagal disimpan, silakan coba lagi.';
        } else if (isOtherOrg) {
          _ttsService.speak('Kartu terdaftar pada organisasi lain.');
          displayMsg = 'Kartu ini terdaftar pada organisasi lain. Presensi ditolak.';
        } else if (isInactiveAccount) {
          _ttsService.speakInactiveAccount();
          displayMsg = 'Akun pegawai tidak aktif. Presensi ditolak.';
        } else if (isCardNotFound) {
          _ttsService.speakCardNotFound();
          displayMsg = rawError;
        } else if (isEmployeeFetchError) {
          _ttsService.speakEmployeeFetchFailed();
          displayMsg = 'Data pegawai gagal diperoleh, silakan coba lagi.';
        } else {
          _ttsService.speak('Gagal memproses absensi.');
          displayMsg = 'Gagal memproses absensi. Silakan coba lagi.';
        }

        ScaffoldMessenger.of(context).hideCurrentSnackBar();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: const Color(0xFF0F172A),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
            duration: const Duration(seconds: 4),
            content: Row(
              children: [
                const Icon(
                  Icons.error_outline_rounded,
                  color: Color(0xFFEF4444),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    displayMsg,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
        await Future.delayed(const Duration(seconds: 4));
      }
    } finally {
      if (mounted) {
        setState(() => _isProcessing = false);
      }
    }
  }

  /// Menampilkan Dialog Admin Pengaturan Perangkat
  void _showAdminSettingsModal() {
    showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (context, setDialogState) {
          return Dialog(
            backgroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
            child: Padding(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: _primaryColor.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Icon(Icons.admin_panel_settings_rounded, color: _primaryColor, size: 22),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: Text(
                          'Pengaturan Perangkat',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close, size: 20),
                        onPressed: () => Navigator.pop(dialogCtx),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Organisasi: $_companyName (${_organizationCode ?? 'ID: $_organizationId'})',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF475569),
                    ),
                  ),
                  if (_selectedLocation != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      'Cabang Aktif: ${_selectedLocation!.name}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xFF64748B),
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  const Divider(height: 1, color: Color(0xFFE2E8F0)),
                  const SizedBox(height: 16),

                  // Opsi 1: Ganti Cabang
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.domain_rounded, color: Color(0xFF475569)),
                    title: const Text('Ganti Lokasi Cabang', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                    subtitle: const Text('Pilih kantor cabang lain untuk perangkat ini', style: TextStyle(fontSize: 11)),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () {
                      Navigator.pop(dialogCtx);
                      setState(() {
                        _selectedLocation = null;
                      });
                    },
                  ),

                  // Opsi 2: Sinkronkan Ulang Branding
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.sync_rounded, color: Color(0xFF475569)),
                    title: const Text('Sinkronkan Data & Branding', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                    subtitle: const Text('Perbarui logo, warna, dan daftar cabang terbaru', style: TextStyle(fontSize: 11)),
                    trailing: const Icon(Icons.chevron_right_rounded),
                    onTap: () async {
                      Navigator.pop(dialogCtx);
                      final messenger = ScaffoldMessenger.of(context);
                      await _loadCompanyProfile();
                      await _loadWorkSchedule();
                      await _loadLocations();
                      if (mounted) {
                        messenger.showSnackBar(
                          SnackBar(
                            content: const Text('Data, Jadwal & Branding berhasil disinkronkan.'),
                            backgroundColor: _primaryColor,
                            behavior: SnackBarBehavior.floating,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                        );
                      }
                    },
                  ),

                  // Opsi 3: Putuskan Hubungan / Reset Perangkat (Pindah Organisasi)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.link_off_rounded, color: Color(0xFFDC2626)),
                    title: const Text('Putuskan Sambungan Organisasi', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFFDC2626))),
                    subtitle: const Text('Reset dan hubungkan ke perusahaan lain', style: TextStyle(fontSize: 11, color: Color(0xFFEF4444))),
                    trailing: const Icon(Icons.chevron_right_rounded, color: Color(0xFFDC2626)),
                    onTap: () {
                      Navigator.pop(dialogCtx);
                      _showDisconnectConfirmDialog();
                    },
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  /// Dialog Konfirmasi Putus Hubungan Organisasi
  void _showDisconnectConfirmDialog() {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF2F2),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFFCA5A5)),
                ),
                child: const Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626), size: 32),
              ),
              const SizedBox(height: 18),
              const Text(
                'Putuskan Sambungan?',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                'Perangkat ini akan dilepas dari $_companyName dan kembali ke layar aktivasi awal.',
                style: const TextStyle(
                  fontSize: 13,
                  color: Color(0xFF64748B),
                  fontWeight: FontWeight.w500,
                  height: 1.4,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: TextButton(
                      style: TextButton.styleFrom(
                        foregroundColor: const Color(0xFF64748B),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      onPressed: () => Navigator.pop(context),
                      child: const Text('Batal', style: TextStyle(fontWeight: FontWeight.w700)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFDC2626),
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                      onPressed: () async {
                        Navigator.pop(context);
                        await DeviceContextService.clearOrganizationContext();
                        _locationSubscription?.cancel();
                        setState(() {
                          _isConfigured = false;
                          _organizationId = null;
                          _organizationCode = null;
                          _selectedLocation = null;
                          _locations = [];
                          _companyName = 'Sistem Absensi';
                          _logoUrl = null;
                        });
                      },
                      child: const Text('Ya, Lepas', style: TextStyle(fontWeight: FontWeight.w800)),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Menampilkan dialog peringatan ketika GPS / Location dalam kondisi OFF
  void _showLocationDisabledAlert() {
    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (context) => Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 28.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: _primaryColor.withOpacity(0.12),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: _primaryColor.withOpacity(0.25),
                    width: 2,
                  ),
                ),
                child: Icon(
                  Icons.location_off_rounded,
                  color: _primaryColor,
                  size: 34,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Location Belum Aktif',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                  letterSpacing: -0.3,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              const Text(
                'Location belum aktif. Silakan aktifkan Location untuk melakukan absensi.',
                style: TextStyle(
                  fontSize: 13,
                  color: Color(0xFF64748B),
                  fontWeight: FontWeight.w500,
                  height: 1.5,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                    elevation: 0,
                  ),
                  onPressed: () {
                    Navigator.pop(context);
                    LocationService.openLocationSettings();
                  },
                  icon: const Icon(Icons.settings_rounded, size: 18),
                  label: const Text(
                    'Aktifkan Location',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                      letterSpacing: 0.2,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              TextButton(
                style: TextButton.styleFrom(
                  foregroundColor: const Color(0xFF64748B),
                ),
                onPressed: () => Navigator.pop(context),
                child: const Text(
                  'Tutup',
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Menampilkan dialog peringatan ketika koneksi internet terputus
  void _showNoInternetAlert() {
    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (context) => Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 28.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: _primaryColor.withOpacity(0.12),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: _primaryColor.withOpacity(0.25),
                    width: 2,
                  ),
                ),
                child: Icon(
                  Icons.wifi_off_rounded,
                  color: _primaryColor,
                  size: 34,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Koneksi Terputus',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                  letterSpacing: -0.3,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              const Text(
                'Koneksi internet tidak tersedia. Silakan periksa jaringan Wi-Fi atau data seluler Anda untuk melakukan absensi.',
                style: TextStyle(
                  fontSize: 13,
                  color: Color(0xFF64748B),
                  fontWeight: FontWeight.w500,
                  height: 1.5,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                    elevation: 0,
                  ),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.refresh_rounded, size: 18),
                  label: const Text(
                    'Coba Lagi',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                      letterSpacing: 0.2,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              TextButton(
                style: TextButton.styleFrom(
                  foregroundColor: const Color(0xFF64748B),
                ),
                onPressed: () => Navigator.pop(context),
                child: const Text(
                  'Tutup',
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Menampilkan dialog peringatan ketika NFC dalam kondisi OFF / tidak aktif
  void _showNfcDisabledAlert() {
    showDialog(
      context: context,
      barrierDismissible: true,
      builder: (context) => Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 28.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: _primaryColor.withOpacity(0.12),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: _primaryColor.withOpacity(0.25),
                    width: 2,
                  ),
                ),
                child: Icon(
                  Icons.nfc_rounded,
                  color: _primaryColor,
                  size: 34,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'NFC Belum Aktif',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                  letterSpacing: -0.3,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              const Text(
                'NFC belum aktif. Silakan aktifkan NFC pada pengaturan perangkat Anda untuk melakukan absensi.',
                style: TextStyle(
                  fontSize: 13,
                  color: Color(0xFF64748B),
                  fontWeight: FontWeight.w500,
                  height: 1.5,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                    elevation: 0,
                  ),
                  onPressed: () => Navigator.pop(context),
                  child: const Text(
                    'Mengerti',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                      letterSpacing: 0.2,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    // 0. LOADING SCREEN: Mengecek status konfigurasi perangkat di awal
    if (_isCheckingConfig) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    // 1. TAMPILAN AKTIVASI: Jika perangkat belum terhubung ke organisasi
    if (!_isConfigured) {
      return DeviceActivationScreen(
        onActivationSuccess: _onActivationSuccess,
      );
    }

    // 2. TAMPILAN PEMILIHAN CABANG: Jika lokasi cabang belum dipilih
    if ((_isLoadingLocations && _locations.isEmpty) ||
      (_locations.isNotEmpty && _selectedLocation == null)) {
      return BranchSelectionView(
        companyName: _companyName,
        logoUrl: _logoUrl,
        locations: _locations,
        primaryColor: _primaryColor,
        onLocationConfirmed: (OfficeLocation chosenLocation) {
          setState(() {
            _selectedLocation = chosenLocation;
          });
          DeviceContextService.saveSelectedLocationId(chosenLocation.id);
        },
      );
    }

    // 3. TAMPILAN UTAMA: Setelah cabang dipilih, tampilkan scanner standby
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Color(0xFFFAFAFE),
              Color(0xFFF1F4F9),
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 22.0, vertical: 18.0),
            child: Column(
              children: [
                // Header Perusahaan dengan Badge Cabang & Menu Admin
                CompanyHeader(
                  companyName: _companyName,
                  logoUrl: _logoUrl,
                  isLoading: _isLoadingProfile,
                  primaryColor: _primaryColor,
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      LocationPickerBadge(
                        locations: _locations,
                        selectedLocation: _selectedLocation,
                        primaryColor: _primaryColor,
                        onLocationChanged: (newLoc) {
                          setState(() => _selectedLocation = newLoc);
                          DeviceContextService.saveSelectedLocationId(newLoc.id);
                        },
                      ),
                      const SizedBox(width: 6),
                      // Tombol Pengaturan Admin Perangkat
                      InkWell(
                        onTap: _showAdminSettingsModal,
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFE2E8F0), width: 1.2),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.02),
                                blurRadius: 6,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Icon(
                            Icons.settings_outlined,
                            size: 18,
                            color: Colors.blueGrey.shade700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                // Banner Peringatan Offline (Di Bawah Header)
                if (!_isOnline)
                  Container(
                    margin: const EdgeInsets.only(top: 14),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFFCA5A5), width: 1.2),
                    ),
                    child: const Row(
                      children: [
                        Icon(Icons.wifi_off_rounded, color: Color(0xFFDC2626), size: 18),
                        SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Koneksi internet terputus. Mohon periksa jaringan.',
                            style: TextStyle(
                              color: Color(0xFFDC2626),
                              fontWeight: FontWeight.w700,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                // Banner Peringatan NFC Belum Aktif
                if (!_isNfcAvailable)
                  Container(
                    margin: const EdgeInsets.only(top: 14),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                    decoration: BoxDecoration(
                      color: _primaryColor.withOpacity(0.08),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: _primaryColor.withOpacity(0.25), width: 1.2),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.nfc_rounded, color: _primaryColor, size: 18),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'NFC belum aktif. Silakan aktifkan NFC untuk melakukan absensi.',
                            style: TextStyle(
                              color: _primaryColor,
                              fontWeight: FontWeight.w700,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                const Spacer(flex: 1),

                // Digital Clock Hub dengan Aksen Warna Dinamis
                ClockWidget(
                  primaryColor: _primaryColor,
                ),

                // Badge Jadwal Jam Kerja Resmi Organisasi
                if (_workSchedule != null) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: const Color(0xFFE2E8F0), width: 1.2),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF0F172A).withValues(alpha: 0.03),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.access_time_filled_rounded,
                          size: 14,
                          color: _primaryColor,
                        ),
                        const SizedBox(width: 7),
                        Text(
                          'Jam Kerja: ${_workSchedule!.checkInTime} - ${_workSchedule!.checkOutTime}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF334155),
                            letterSpacing: 0.2,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                const Spacer(flex: 1),

                // Area Pemindai Kartu NFC
                Expanded(
                  flex: 8,
                  child: NfcScanArea(
                    isProcessing: _isProcessing,
                    isOnline: _isOnline,
                    isNfcAvailable: _isNfcAvailable,
                    primaryColor: _primaryColor,
                    onSimulateTap: () {
                      if (!_isNfcAvailable) {
                        _ttsService.speakNfcDisabled();
                        if (mounted) {
                          _showNfcDisabledAlert();
                        }
                        return;
                      }
                      _handleNfcAttendance('SIMULASI_ID');
                    },
                  ),
                ),

                const SizedBox(height: 16),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
