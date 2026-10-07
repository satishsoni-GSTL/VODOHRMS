// Play Store screenshots, rendered from the real app screens with DEMO data (no real
// employee information). Run from mobile-app/:
//
//   flutter test tool/store_screenshots_test.dart --update-goldens
//   php tool/frame_screenshots.php
//
// Raw 1080×2340 captures land in store/screenshots/raw/, framed versions with captions in
// store/screenshots/. Kept out of test/ so normal `flutter test` runs don't depend on it.

import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:vodo_hrms/screens/home_shell.dart';
import 'package:vodo_hrms/screens/leave_form_screen.dart';
import 'package:vodo_hrms/screens/login_screen.dart';
import 'package:vodo_hrms/screens/payslips_screen.dart';
import 'package:vodo_hrms/services/auth_service.dart';
import 'package:vodo_hrms/theme.dart';
import 'package:vodo_hrms/utils/format.dart';

const _server = 'https://hrms.globalspace.in';

final _today = DateTime.now();
String _d(int offsetDays) => ymd(_today.add(Duration(days: offsetDays)));
String get _ym => ymd(_today).substring(0, 7);

final _user = {
  'name': 'Aarav Mehta',
  'employee_code': 'GS101',
  'email': 'aarav.mehta@example.com',
  'designation': 'Sales Manager',
  'department': 'Sales',
  'must_change_password': false,
  'is_approver': true,
  'is_manager': true,
};

// ---- demo API --------------------------------------------------------------------------

Map<String, dynamic> _attendanceMonth() {
  final first = DateTime(_today.year, _today.month);
  final days = <Map<String, dynamic>>[];
  for (var d = first; d.month == first.month; d = d.add(const Duration(days: 1))) {
    final date = ymd(d);
    final future = d.isAfter(_today);
    final weekend = d.weekday >= 6;
    String code;
    if (future) {
      code = '';
    } else if (weekend) {
      code = 'WO';
    } else if (d.day == 6) {
      code = 'L';
    } else if (d.day == 9 || d.day == 16) {
      code = 'WFH';
    } else if (d.day == 13) {
      code = 'HD';
    } else {
      code = 'P';
    }
    final worked = ['P', 'WFH', 'HD'].contains(code);
    days.add({
      'date': date,
      'code': code,
      'label': const {'P': 'Present', 'WFH': 'Work From Home', 'HD': 'Half Day', 'L': 'Leave', 'WO': 'Weekly Off'}[code] ?? '',
      'first_in': worked ? '09:${(12 + d.day % 17).toString().padLeft(2, '0')}:00' : null,
      'last_out': worked ? (code == 'HD' ? '13:45:00' : '18:${(20 + d.day % 30).toString().padLeft(2, '0')}:00') : null,
      'hours': worked ? (code == 'HD' ? 4.4 : 8.2 + (d.day % 5) / 10) : null,
    });
  }
  return {
    'month': _ym,
    'days': days,
    'totals': {
      'present': days.where((d) => d['code'] == 'P').length,
      'half_day': days.where((d) => d['code'] == 'HD').length,
      'wfh': days.where((d) => d['code'] == 'WFH').length,
      'on_duty': 0,
      'leave': days.where((d) => d['code'] == 'L').length,
      'holiday': 0,
      'weekly_off': days.where((d) => d['code'] == 'WO').length,
      'absent': 0,
      'missing_punch': 0,
      'hours': 142.6,
      'avg_hours': 8.4,
    },
  };
}

Map<String, dynamic> _leaveType(int id, String name, String code, num available, num used, {bool paid = true}) => {
      'id': id,
      'name': name,
      'code': code,
      'is_paid': paid,
      'attachment_required': false,
      'min_days': null,
      'max_days': null,
      'balance': {'opening': 0, 'credited': available + used, 'used': used, 'available': available},
    };

final _routes = <String, Map<String, dynamic> Function(Uri)>{
  '/me': (_) => {'user': _user},
  '/push-token': (_) => {'ok': true},
  '/dashboard': (_) => {
        'user': _user,
        'today': {'date': ymd(_today), 'status': 'present', 'status_label': 'Present', 'first_in': '09:14:00', 'last_out': null, 'hours': null, 'can_clock': false},
        'leave_balances': [
          {'code': 'CL', 'name': 'Casual Leave', 'available': 6},
          {'code': 'SL', 'name': 'Sick Leave', 'available': 7.5},
          {'code': 'EL', 'name': 'Earned Leave', 'available': 12},
        ],
        'my_pending': {'leave': 1, 'wfh': 0, 'regularization': 1, 'expense': 2, 'sent_back': 0},
        'approvals_waiting': 4,
        'upcoming_holidays': [
          {'id': 1, 'name': 'Diwali (Laxmi Poojan)', 'date': _d(9), 'type': 'company', 'type_label': 'Company Holiday', 'is_optional': false},
          {'id': 2, 'name': 'Diwali (Deepavali)', 'date': _d(10), 'type': 'company', 'type_label': 'Company Holiday', 'is_optional': false},
          {'id': 3, 'name': 'Christmas Day', 'date': _d(54), 'type': 'company', 'type_label': 'Company Holiday', 'is_optional': false},
        ],
        'celebrations': [],
      },
  '/attendance': (_) => _attendanceMonth(),
  '/leave': (_) => {
        'year': _today.year,
        'types': [
          _leaveType(1, 'Casual Leave', 'CL', 6, 4),
          _leaveType(2, 'Sick Leave', 'SL', 7.5, 2.5),
          _leaveType(3, 'Earned Leave', 'EL', 12, 3),
          _leaveType(4, 'Leave Without Pay', 'LWP', 0, 0, paid: false),
        ],
        'applications': [
          {'id': 11, 'leave_type_id': 1, 'leave_type': 'Casual Leave', 'from_date': _d(14), 'to_date': _d(15), 'days': 2, 'is_half_day': false, 'reason': 'Family function in Pune', 'status': 'pending', 'status_label': 'Pending', 'remarks': null, 'can_reapply': false},
          {'id': 10, 'leave_type_id': 2, 'leave_type': 'Sick Leave', 'from_date': _d(-24), 'to_date': _d(-24), 'days': 1, 'is_half_day': false, 'reason': 'Fever', 'status': 'approved', 'status_label': 'Approved', 'remarks': 'Get well soon', 'can_reapply': false},
          {'id': 9, 'leave_type_id': 1, 'leave_type': 'Casual Leave', 'from_date': _d(-40), 'to_date': _d(-40), 'days': 0.5, 'is_half_day': true, 'reason': 'Bank work', 'status': 'sent_back', 'status_label': 'Sent Back', 'remarks': 'Please apply as first half', 'can_reapply': true},
        ],
      },
  '/leave/preview': (_) => {'days': 2},
  '/approvals': (_) => {
        'items': [
          {
            'id': 101, 'type': 'leave', 'title': 'Casual Leave', 'workflow': 'Leave Approval', 'level': 1,
            'employee': {'name': 'Priya Nair', 'employee_code': 'GS114'},
            'details': [
              {'label': 'From', 'value': dateLabel(_d(3))},
              {'label': 'To', 'value': dateLabel(_d(4))},
              {'label': 'Days', 'value': '2'},
              {'label': 'Reason', 'value': 'Sister\'s wedding'},
            ],
            'submitted_at': '${_d(-1)}T10:15:00+05:30', 'trail': [],
          },
          {
            'id': 102, 'type': 'expense', 'title': 'Expense EXP-202610-0042', 'workflow': 'Expense Approval', 'level': 1,
            'employee': {'name': 'Rohan Kulkarni', 'employee_code': 'GS122'},
            'details': [
              {'label': 'Claim date', 'value': dateLabel(_d(-2))},
              {'label': 'Amount', 'value': '₹6,450.50'},
              {'label': 'Items', 'value': '3'},
              {'label': 'Project / client', 'value': 'Tata Motors – Pune'},
            ],
            'submitted_at': '${_d(-2)}T17:40:00+05:30', 'trail': [],
          },
          {
            'id': 103, 'type': 'wfh', 'title': 'Work From Home', 'workflow': 'WFH Approval', 'level': 1,
            'employee': {'name': 'Sneha Joshi', 'employee_code': 'GS131'},
            'details': [
              {'label': 'From', 'value': dateLabel(_d(2))},
              {'label': 'To', 'value': dateLabel(_d(2))},
              {'label': 'Working days', 'value': '1'},
              {'label': 'Reason', 'value': 'Internet installation at home'},
            ],
            'submitted_at': '${_d(-1)}T09:05:00+05:30', 'trail': [],
          },
        ],
      },
  '/team': (_) {
    final people = [
      ('Priya Nair', 'GS114', 'Sales Executive', 'P', 'Present', '09:08:00', null),
      ('Rohan Kulkarni', 'GS122', 'Key Account Manager', 'P', 'Present', '09:21:00', null),
      ('Sneha Joshi', 'GS131', 'Sales Executive', 'WFH', 'Work From Home', '09:30:00', null),
      ('Vikram Singh', 'GS117', 'Area Sales Manager', 'L', 'On Leave', null, null),
      ('Ananya Iyer', 'GS140', 'Sales Coordinator', 'P', 'Present', '08:55:00', null),
      ('Karan Desai', 'GS136', 'Sales Executive', '', 'Not yet marked', null, null),
      ('Meera Pillai', 'GS128', 'Sales Executive', 'P', 'Present', '09:12:00', null),
    ];
    return {
      'date': ymd(_today),
      'summary': {'total': 7, 'present': 4, 'wfh': 1, 'on_leave': 1, 'absent': 0, 'not_marked': 1, 'off': 0},
      'members': [
        for (var i = 0; i < people.length; i++)
          {
            'id': 200 + i, 'name': people[i].$1, 'employee_code': people[i].$2, 'designation': people[i].$3, 'department': 'Sales',
            'is_direct': true, 'status_code': people[i].$4, 'status_label': people[i].$5, 'first_in': people[i].$6, 'last_out': people[i].$7, 'hours': null,
          },
      ],
    };
  },
  '/payslips/7': (_) => {
        'id': 7,
        'payroll_month': ymd(DateTime(_today.year, _today.month - 1)).substring(0, 7),
        'paid_days': 30, 'lop_days': 0, 'lop_amount': 0,
        'earnings': [
          {'label': 'Basic', 'amount': 32000},
          {'label': 'House Rent Allowance', 'amount': 16000},
          {'label': 'Conveyance Allowance', 'amount': 1600},
          {'label': 'Medical Allowance', 'amount': 1250},
          {'label': 'Special Allowance', 'amount': 9150},
        ],
        'deductions': [
          {'label': 'Provident Fund (Employee)', 'amount': 3000},
          {'label': 'Professional Tax', 'amount': 200},
          {'label': 'Income Tax (TDS)', 'amount': 2450},
        ],
        'gross': 60000, 'total_deductions': 5650, 'net_pay': 54350, 'has_pdf': true,
      },
};

http.Client _demoClient() => MockClient((request) async {
      final path = request.url.path.replaceFirst('/api/mobile', '');
      final handler = _routes[path];
      if (handler == null) return http.Response(jsonEncode({'message': 'not mocked: $path'}), 404);
      return http.Response(jsonEncode(handler(request.url)), 200, headers: {'content-type': 'application/json; charset=utf-8'});
    });

// ---- harness ---------------------------------------------------------------------------

Future<void> _loadFonts() async {
  final flutterRoot = File(Platform.resolvedExecutable).parent.parent.parent.parent.parent.parent.path; // …/flutter
  final fontsDir = '$flutterRoot/bin/cache/artifacts/material_fonts';

  final roboto = FontLoader('Roboto');
  for (final f in ['roboto-regular.ttf', 'roboto-medium.ttf', 'roboto-bold.ttf', 'roboto-black.ttf', 'roboto-light.ttf']) {
    roboto.addFont(Future.value(ByteData.view(File('$fontsDir/$f').readAsBytesSync().buffer)));
  }
  await roboto.load();

  final icons = FontLoader('MaterialIcons');
  icons.addFont(Future.value(ByteData.view(File('$fontsDir/materialicons-regular.otf').readAsBytesSync().buffer)));
  await icons.load();
}

/// On a phone, styles without a fontFamily use the platform font (Roboto); the test renderer
/// has no platform font, so name it explicitly.
ThemeData _theme() {
  final t = buildTheme(Brightness.light);
  return t.copyWith(
    appBarTheme: t.appBarTheme.copyWith(titleTextStyle: t.appBarTheme.titleTextStyle?.copyWith(fontFamily: 'Roboto')),
    textTheme: t.textTheme.apply(fontFamily: 'Roboto'),
  );
}

Future<void> _shoot(WidgetTester tester, String name, Widget home, {Future<void> Function()? then}) async {
  await http.runWithClient(() async {
    tester.view.physicalSize = const Size(1080, 2340);
    tester.view.devicePixelRatio = 2.625;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: _theme(),
      home: home,
    ));
    // Let the mocked API calls complete and animations finish.
    for (var i = 0; i < 6; i++) {
      await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
      await tester.pump(const Duration(milliseconds: 300));
    }
    if (then != null) {
      await then();
      for (var i = 0; i < 6; i++) {
        await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
        await tester.pump(const Duration(milliseconds: 300));
      }
    }
    await tester.runAsync(() => precacheImage(const AssetImage('assets/images/logo.png'), tester.element(find.byType(Scaffold).first)));
    await tester.runAsync(() => precacheImage(const AssetImage('assets/images/logo_mark.png'), tester.element(find.byType(Scaffold).first)));
    await tester.pump();

    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../store/screenshots/raw/$name.png'));
  }, _demoClient);
}

// ignore_for_file: invalid_use_of_visible_for_testing_member

void main() {
  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    await _loadFonts();
    AuthService.instance.debugSignIn(serverUrl: _server, user: _user);
  });

  testWidgets('01 home', (t) => _shoot(t, '01-home', const HomeShell()));

  testWidgets('02 attendance', (t) => _shoot(t, '02-attendance', const HomeShell(),
      then: () async => t.tap(find.text('Attendance').last)));

  testWidgets('03 requests', (t) => _shoot(t, '03-requests', const HomeShell(),
      then: () async => t.tap(find.text('Requests').last)));

  testWidgets('04 apply leave', (t) => _shoot(t, '04-apply-leave', LeaveFormScreen(reapplyFrom: {
        'leave_type_id': 1,
        'from_date': _d(14),
        'to_date': _d(15),
        'is_half_day': false,
        'reason': 'Family function in Pune',
      })));

  testWidgets('05 approvals', (t) => _shoot(t, '05-approvals', const HomeShell(),
      then: () async => t.tap(find.text('Team').last)));

  testWidgets('06 team today', (t) => _shoot(t, '06-team', const HomeShell(), then: () async {
        await t.tap(find.text('Team').last);
        await t.pump(const Duration(milliseconds: 400));
        await t.tap(find.text('Today'));
      }));

  testWidgets('07 payslip', (t) => _shoot(t, '07-payslip',
      PayslipDetailScreen(id: 7, month: ymd(DateTime(_today.year, _today.month - 1)).substring(0, 7))));

  testWidgets('08 sign in', (t) => _shoot(t, '08-sign-in', const LoginScreen()));
}
