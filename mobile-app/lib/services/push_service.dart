import 'dart:async';
import 'dart:io';
import 'dart:ui' show Color;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../api/api_client.dart';

/// Firebase push notifications.
///
/// Configured at build time (no google-services.json needed), from the Firebase console →
/// Project settings → Your apps:
///
///   --dart-define=FIREBASE_API_KEY=...            (Web API key / Android "current_key")
///   --dart-define=FIREBASE_PROJECT_ID=...
///   --dart-define=FIREBASE_SENDER_ID=...          (Cloud Messaging sender ID)
///   --dart-define=FIREBASE_ANDROID_APP_ID=1:...:android:...
///   --dart-define=FIREBASE_IOS_APP_ID=1:...:ios:...
///
/// Without these the app works normally, just without push. The server sends pushes when
/// FIREBASE_CREDENTIALS is set in the HRMS .env (see FirebasePushService).
class PushService {
  PushService._();
  static final PushService instance = PushService._();

  static const _apiKey = String.fromEnvironment('FIREBASE_API_KEY');
  static const _projectId = String.fromEnvironment('FIREBASE_PROJECT_ID');
  static const _senderId = String.fromEnvironment('FIREBASE_SENDER_ID');
  static const _androidAppId = String.fromEnvironment('FIREBASE_ANDROID_APP_ID');
  static const _iosAppId = String.fromEnvironment('FIREBASE_IOS_APP_ID');

  static const _channel = AndroidNotificationChannel(
    'hrms_default', // must match the channel_id the server sends
    'HRMS notifications',
    description: 'Approvals, request updates, payslips and announcements',
    importance: Importance.high,
  );

  final _local = FlutterLocalNotificationsPlugin();
  bool _ready = false;
  StreamSubscription<String>? _tokenSub;

  /// The app screen a tapped notification asks for ('approvals', 'requests', 'payslips'…).
  /// HomeShell listens and navigates, then clears it.
  final ValueNotifier<String?> pendingScreen = ValueNotifier(null);

  bool get isConfigured {
    final appId = Platform.isIOS ? _iosAppId : _androidAppId;
    return _apiKey.isNotEmpty && _projectId.isNotEmpty && _senderId.isNotEmpty && appId.isNotEmpty;
  }

  /// Initialise Firebase and notification display. Safe to call when not configured.
  Future<void> init() async {
    if (_ready || !isConfigured) return;

    try {
      await Firebase.initializeApp(
        options: FirebaseOptions(
          apiKey: _apiKey,
          appId: Platform.isIOS ? _iosAppId : _androidAppId,
          messagingSenderId: _senderId,
          projectId: _projectId,
        ),
      );

      await _local.initialize(
        const InitializationSettings(
          android: AndroidInitializationSettings('@drawable/ic_stat_notification'),
          iOS: DarwinInitializationSettings(),
        ),
        onDidReceiveNotificationResponse: (r) => _openScreen(r.payload),
      );
      await _local
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(_channel);

      // App in the foreground: Android doesn't show FCM notifications itself, so show one.
      FirebaseMessaging.onMessage.listen(_showForeground);
      await FirebaseMessaging.instance.setForegroundNotificationPresentationOptions(alert: true, badge: true, sound: true);

      // Tapped while the app was in the background / closed.
      FirebaseMessaging.onMessageOpenedApp.listen((m) => _openScreen(m.data['screen'] as String?));
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) _openScreen(initial.data['screen'] as String?);

      _ready = true;
    } catch (e) {
      debugPrint('Push disabled: $e');
    }
  }

  /// After sign-in: ask permission and register this phone's token with the HRMS.
  Future<void> registerWithServer() async {
    if (!_ready) return;

    try {
      final settings = await FirebaseMessaging.instance.requestPermission(alert: true, badge: true, sound: true);
      if (settings.authorizationStatus == AuthorizationStatus.denied) return;

      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await ApiClient.instance.post('/push-token', {'token': token});

      _tokenSub ??= FirebaseMessaging.instance.onTokenRefresh.listen((t) async {
        try {
          await ApiClient.instance.post('/push-token', {'token': t});
        } catch (_) {}
      });
    } catch (e) {
      debugPrint('Push registration failed: $e');
    }
  }

  /// On sign-out: drop the local token so a new sign-in gets a fresh one.
  Future<void> unregister() async {
    await _tokenSub?.cancel();
    _tokenSub = null;
    if (!_ready) return;
    try {
      await FirebaseMessaging.instance.deleteToken();
    } catch (_) {}
  }

  void _showForeground(RemoteMessage message) {
    final n = message.notification;
    if (n == null) return;

    _local.show(
      message.hashCode,
      n.title,
      n.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _channel.id,
          _channel.name,
          channelDescription: _channel.description,
          importance: Importance.high,
          priority: Priority.high,
          icon: '@drawable/ic_stat_notification',
          color: const Color(0xFF0C8481),
        ),
        iOS: const DarwinNotificationDetails(),
      ),
      payload: message.data['screen'] as String?,
    );
  }

  void _openScreen(String? screen) {
    if (screen != null && screen.isNotEmpty) pendingScreen.value = screen;
  }
}
