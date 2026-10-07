import 'package:flutter_test/flutter_test.dart';
import 'package:vodo_hrms/services/auth_service.dart';
import 'package:vodo_hrms/utils/format.dart';

void main() {
  group('AuthService.normaliseServer', () {
    test('adds https and strips trailing slashes', () {
      expect(AuthService.normaliseServer('hrms.example.com/'), 'https://hrms.example.com');
      expect(AuthService.normaliseServer('  http://192.168.1.5:8000// '), 'http://192.168.1.5:8000');
    });

    test('falls back to the default server when blank', () {
      expect(AuthService.normaliseServer(''), isNotEmpty);
    });
  });

  group('format', () {
    test('money uses Indian grouping', () {
      expect(money(123456.5), '₹1,23,456.50');
    });

    test('dates and days', () {
      expect(dateLabel('2026-10-07'), '07 Oct 2026');
      expect(dateRange('2026-10-07', '2026-10-07'), '07 Oct 2026');
      expect(daysLabel(1), '1 day');
      expect(daysLabel(2.5), '2.5 days');
      expect(monthLabel('2026-09'), 'September 2026');
      expect(timeLabel('09:30:00'), '09:30');
    });
  });

  test('AppUser initials', () {
    expect(AppUser({'name': 'Siddhi Prashant Desai'}).initials, 'SD');
    expect(AppUser({'name': ''}).initials, '?');
  });
}
