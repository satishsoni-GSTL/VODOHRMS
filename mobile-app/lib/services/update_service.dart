import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:in_app_update/in_app_update.dart';

/// Automatic app updates through Google Play's in-app update API (Android only).
///
/// When a newer version is on Play, the update downloads in the background while the
/// employee keeps working (Play asks for consent once per version). Once downloaded it is
/// installed silently the next time the app goes to the background, so nobody is
/// interrupted. If they come back first, a snackbar offers "Restart" to install now.
///
/// Releases uploaded with in-app update priority 4–5 (Play Developer API
/// `inAppUpdatePriority`) use the blocking full-screen update instead.
///
/// Only works for installs from Google Play; sideloaded and debug builds are skipped.
/// On iOS the App Store's own automatic updates apply.
class UpdateService with WidgetsBindingObserver {
  UpdateService._();
  static final UpdateService instance = UpdateService._();

  /// Lets the service show its "Restart" snackbar from anywhere. Set on MaterialApp.
  final scaffoldMessengerKey = GlobalKey<ScaffoldMessengerState>();

  static const _checkEvery = Duration(hours: 6);
  static const _askAgainAfter = Duration(days: 3);
  static const _declinedKey = 'update_declined'; // "<versionCode>|<epoch ms>"

  final _storage = const FlutterSecureStorage();
  bool _started = false;
  bool _busy = false;
  bool _downloaded = false;
  bool _installing = false;
  DateTime? _lastCheck;
  StreamSubscription<InstallStatus>? _statusSub;

  bool get _supported => Platform.isAndroid && !kDebugMode;

  /// Call once after runApp. Checks now and again whenever the app resumes.
  void start() {
    if (_started || !_supported) return;
    _started = true;
    WidgetsBinding.instance.addObserver(this);
    _statusSub = InAppUpdate.installUpdateListener.listen(_onStatus, onError: (_) {});
    _check();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.resumed:
        _check();
      case AppLifecycleState.paused:
        // Installing while in the background is silent — no restart screen for the user.
        if (_downloaded) _install();
      default:
        break;
    }
  }

  void _onStatus(InstallStatus status) {
    if (status == InstallStatus.downloaded) {
      _downloaded = true;
      final lifecycle = WidgetsBinding.instance.lifecycleState;
      if (lifecycle == AppLifecycleState.resumed) {
        _offerRestart();
      } else {
        _install();
      }
    } else if (status == InstallStatus.failed || status == InstallStatus.canceled) {
      _downloaded = false;
    }
  }

  Future<void> _check() async {
    if (_busy || _installing) return;
    final now = DateTime.now();
    // Always re-check if a download finished while we weren't listening.
    if (!_downloaded && _lastCheck != null && now.difference(_lastCheck!) < _checkEvery) return;

    _busy = true;
    try {
      final info = await InAppUpdate.checkForUpdate();
      _lastCheck = now;

      if (info.installStatus == InstallStatus.downloaded) {
        _downloaded = true;
        _offerRestart();
        return;
      }

      if (info.updateAvailability == UpdateAvailability.developerTriggeredUpdateInProgress) {
        // An immediate update was interrupted — Play requires resuming it.
        if (info.immediateUpdateAllowed) await InAppUpdate.performImmediateUpdate();
        return;
      }

      if (info.updateAvailability != UpdateAvailability.updateAvailable) return;

      if (info.updatePriority >= 4 && info.immediateUpdateAllowed) {
        await InAppUpdate.performImmediateUpdate();
        return;
      }

      if (!info.flexibleUpdateAllowed) return;
      // Already downloading (e.g. started on an earlier launch) — the listener will catch it.
      if (info.installStatus == InstallStatus.downloading || info.installStatus == InstallStatus.pending) return;
      if (await _recentlyDeclined(info.availableVersionCode)) return;

      final result = await InAppUpdate.startFlexibleUpdate();
      if (result == AppUpdateResult.userDeniedUpdate) {
        await _storage.write(
          key: _declinedKey,
          value: '${info.availableVersionCode}|${now.millisecondsSinceEpoch}',
        );
      }
    } catch (e) {
      // Not installed from Play, no Play Store, offline… try again next resume.
      debugPrint('App update check failed: $e');
    } finally {
      _busy = false;
    }
  }

  Future<bool> _recentlyDeclined(int? versionCode) async {
    try {
      final saved = await _storage.read(key: _declinedKey);
      if (saved == null) return false;
      final parts = saved.split('|');
      if (parts.length != 2 || parts[0] != '$versionCode') return false;
      final at = DateTime.fromMillisecondsSinceEpoch(int.tryParse(parts[1]) ?? 0);
      return DateTime.now().difference(at) < _askAgainAfter;
    } catch (_) {
      return false;
    }
  }

  void _offerRestart() {
    final messenger = scaffoldMessengerKey.currentState;
    if (messenger == null) return;
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        content: const Text('A new version of the app is ready.'),
        duration: const Duration(days: 1), // stays until acted on or the app is backgrounded
        action: SnackBarAction(label: 'Restart', onPressed: _install),
      ));
  }

  Future<void> _install() async {
    if (_installing) return;
    _installing = true;
    try {
      await InAppUpdate.completeFlexibleUpdate(); // Play restarts the app on the new version
    } catch (e) {
      debugPrint('App update install failed: $e');
    } finally {
      _installing = false;
    }
  }

  void dispose() {
    _statusSub?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _started = false;
  }
}
