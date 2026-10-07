/// Build-time settings. Release builds take them from release-config.json via
/// build-release.ps1 (--dart-define-from-file); development uses the defaults below.
class AppConfig {
  /// Default HRMS server (a PC on the local Wi-Fi). Use http://10.0.2.2:8000 for the Android emulator.
  static const String defaultServerUrl =
      String.fromEnvironment('HRMS_URL', defaultValue: 'http://192.168.1.57:8000');

  /// Whether the login screen shows the "Server address" field (handy for testing;
  /// turn off for a locked-down release with --dart-define=ALLOW_SERVER_CHANGE=false).
  static const bool allowServerChange =
      bool.fromEnvironment('ALLOW_SERVER_CHANGE', defaultValue: true);

  static const String appName = 'VODO HRMS';
}
