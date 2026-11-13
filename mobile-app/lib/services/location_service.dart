import 'package:geolocator/geolocator.dart';

/// Thrown with a message that can be shown to the employee as-is.
class LocationException implements Exception {
  LocationException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// One-shot foreground position for geo-tagged attendance punches.
class LocationService {
  LocationService._();

  static Future<Position> current() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw LocationException('Location (GPS) is off. Turn it on to clock in or out.');
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied) {
      throw LocationException('Location permission is needed to clock in or out.');
    }
    if (permission == LocationPermission.deniedForever) {
      throw LocationException('Location permission is blocked. Allow it for VODO HRMS in your phone settings.');
    }

    try {
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 20)),
      );
    } catch (_) {
      throw LocationException('Could not get your location. Move to an open area and try again.');
    }
  }
}
