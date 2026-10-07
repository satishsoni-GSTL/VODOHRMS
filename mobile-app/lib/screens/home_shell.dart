import 'package:flutter/material.dart';

import '../services/auth_service.dart';
import '../services/push_service.dart';
import 'attendance_screen.dart';
import 'change_password_screen.dart';
import 'dashboard_screen.dart';
import 'expenses_screen.dart';
import 'holidays_screen.dart';
import 'loans_screen.dart';
import 'more_screen.dart';
import 'payslips_screen.dart';
import 'requests_screen.dart';
import 'team_screen.dart';

/// Bottom navigation: Home · Attendance · Requests · Team (managers / approvers) · More.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    AuthService.instance.refreshUser().then((_) {
      // First login with a temporary password → ask to change it.
      if (mounted && (AuthService.instance.user?.mustChangePassword ?? false)) {
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ChangePasswordScreen(forced: true)));
      }
    });

    // Keep this phone's push token registered (it can change between launches).
    PushService.instance.registerWithServer();
    PushService.instance.pendingScreen.addListener(_onNotificationTap);
    WidgetsBinding.instance.addPostFrameCallback((_) => _onNotificationTap());
  }

  @override
  void dispose() {
    PushService.instance.pendingScreen.removeListener(_onNotificationTap);
    super.dispose();
  }

  bool get _hasTeamTab {
    final u = AuthService.instance.user;
    return (u?.isManager ?? false) || (u?.isApprover ?? false);
  }

  /// Open the screen a tapped push notification points at.
  void _onNotificationTap() {
    final screen = PushService.instance.pendingScreen.value;
    if (screen == null || !mounted) return;
    PushService.instance.pendingScreen.value = null;

    Navigator.of(context).popUntil((r) => r.isFirst);

    Widget? page;
    switch (screen) {
      case 'attendance':
        _go(1);
      case 'requests':
        _go(2);
      case 'approvals':
        _go(_hasTeamTab ? 3 : 0);
      case 'payslips':
        page = const PayslipsScreen();
      case 'expenses':
        page = const ExpensesScreen();
      case 'holidays':
        page = const HolidaysScreen();
      case 'loans':
        page = const LoansScreen();
      default:
        _go(0);
    }

    if (page != null) Navigator.of(context).push(MaterialPageRoute(builder: (_) => page!));
  }

  void _go(int index) => setState(() => _index = index);

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: AuthService.instance,
      builder: (context, _) {
        final user = AuthService.instance.user;
        final isManager = user?.isManager ?? false;
        final isApprover = user?.isApprover ?? false;

        final pages = <Widget>[
          DashboardScreen(onOpenTab: (tab) => _go(switch (tab) {
                'attendance' => 1,
                'requests' => 2,
                'approvals' => _hasTeamTab ? 3 : 0,
                _ => 0,
              })),
          const AttendanceScreen(),
          const RequestsScreen(),
          if (_hasTeamTab) TeamScreen(showApprovals: isApprover, showTeam: isManager),
          const MoreScreen(),
        ];

        final destinations = <NavigationDestination>[
          const NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
          const NavigationDestination(icon: Icon(Icons.calendar_month_outlined), selectedIcon: Icon(Icons.calendar_month), label: 'Attendance'),
          const NavigationDestination(icon: Icon(Icons.event_note_outlined), selectedIcon: Icon(Icons.event_note), label: 'Requests'),
          if (_hasTeamTab)
            NavigationDestination(
              icon: const Icon(Icons.groups_outlined),
              selectedIcon: const Icon(Icons.groups),
              label: isManager ? 'Team' : 'Approvals',
            ),
          const NavigationDestination(icon: Icon(Icons.menu), label: 'More'),
        ];

        final index = _index.clamp(0, pages.length - 1);

        return Scaffold(
          body: IndexedStack(index: index, children: pages),
          bottomNavigationBar: NavigationBar(
            selectedIndex: index,
            onDestinationSelected: _go,
            destinations: destinations,
          ),
        );
      },
    );
  }
}
