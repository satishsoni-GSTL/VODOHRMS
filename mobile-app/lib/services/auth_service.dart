import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../api/api_client.dart';
import '../config.dart';
import 'push_service.dart';

class AppUser {
  AppUser(this.json);

  final Map<String, dynamic> json;

  String get name => (json['name'] ?? '') as String;
  String get employeeCode => (json['employee_code'] ?? '') as String;
  String? get email => json['email'] as String?;
  String? get designation => json['designation'] as String?;
  String? get department => json['department'] as String?;
  bool get isApprover => json['is_approver'] == true;
  bool get isManager => json['is_manager'] == true;
  bool get mustChangePassword => json['must_change_password'] == true;

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return (parts.first[0] + (parts.length > 1 ? parts.last[0] : '')).toUpperCase();
  }
}

/// One-time login: the device token from /api/mobile/login is kept in secure storage
/// (Android Keystore / iOS Keychain) and sent with every API call. The employee stays signed
/// in until they sign out, HR revokes the device, or the login is deactivated.
class AuthService extends ChangeNotifier {
  AuthService._();
  static final AuthService instance = AuthService._();

  static const _kToken = 'device_token';
  static const _kServer = 'server_url';
  static const _kUser = 'user';

  final _storage = const FlutterSecureStorage();
  final _api = ApiClient.instance;

  String _serverUrl = AppConfig.defaultServerUrl;
  AppUser? _user;
  String? _signedOutMessage;

  String get serverUrl => _serverUrl;
  AppUser? get user => _user;
  bool get isSignedIn => _api.token != null;

  /// Why the employee landed back on the login screen (shown once).
  String? takeSignedOutMessage() {
    final m = _signedOutMessage;
    _signedOutMessage = null;
    return m;
  }

  Future<void> load() async {
    _serverUrl = await _storage.read(key: _kServer) ?? AppConfig.defaultServerUrl;
    _api.baseUrl = _serverUrl;
    _api.token = await _storage.read(key: _kToken);
    _api.onUnauthorized = () => _signOutLocally('You were signed out of the HRMS. Please sign in again.');

    final userJson = await _storage.read(key: _kUser);
    if (userJson != null) {
      try {
        _user = AppUser(jsonDecode(userJson) as Map<String, dynamic>);
      } catch (_) {}
    }
  }

  static String normaliseServer(String url) {
    var s = url.trim();
    if (s.isEmpty) return AppConfig.defaultServerUrl;
    if (!s.startsWith('http://') && !s.startsWith('https://')) s = 'https://$s';
    while (s.endsWith('/')) {
      s = s.substring(0, s.length - 1);
    }
    return s;
  }

  Future<void> login({required String login, required String password, required String serverUrl}) async {
    final server = normaliseServer(serverUrl);
    // Release builds only ever talk to the HRMS over HTTPS.
    if (kReleaseMode && !server.startsWith('https://')) {
      throw ApiException('The HRMS server address must start with https://');
    }

    _api.baseUrl = server;
    _api.token = null;

    final body = await _api.post('/login', {'login': login.trim(), 'password': password, ...await _deviceDetails()});

    _serverUrl = _api.baseUrl;
    _api.token = body['token'] as String;
    await _storage.write(key: _kToken, value: _api.token);
    await _storage.write(key: _kServer, value: _serverUrl);
    await _setUser(body['user'] as Map<String, dynamic>);
    unawaited(PushService.instance.registerWithServer());
  }

  /// Refreshes the cached profile summary (name, approver flag) in the background.
  Future<void> refreshUser() async {
    try {
      final body = await _api.get('/me');
      await _setUser(body['user'] as Map<String, dynamic>);
    } catch (_) {}
  }

  Future<void> logout() async {
    try {
      await _api.post('/logout');
    } catch (_) {
      // Offline: forget the token anyway; HR can revoke it under "Mobile App Devices".
    }
    await _signOutLocally(null);
  }

  Future<void> _setUser(Map<String, dynamic> json) async {
    _user = AppUser(json);
    await _storage.write(key: _kUser, value: jsonEncode(json));
    notifyListeners();
  }

  Future<void> _signOutLocally(String? message) async {
    if (_api.token == null) return;
    unawaited(PushService.instance.unregister());
    _api.token = null;
    _user = null;
    _signedOutMessage = message;
    await _storage.delete(key: _kToken);
    await _storage.delete(key: _kUser);
    notifyListeners();
  }

  Future<Map<String, String>> _deviceDetails() async {
    var deviceName = 'Unknown device';
    var platform = Platform.operatingSystem;
    var appVersion = '';

    try {
      final info = DeviceInfoPlugin();
      if (Platform.isAndroid) {
        final a = await info.androidInfo;
        deviceName = '${a.manufacturer} ${a.model}';
      } else if (Platform.isIOS) {
        final i = await info.iosInfo;
        deviceName = i.utsname.machine;
        platform = 'ios';
      }
    } catch (_) {}

    try {
      appVersion = (await PackageInfo.fromPlatform()).version;
    } catch (_) {}

    return {'device_name': deviceName, 'platform': platform, 'app_version': appVersion};
  }
}
