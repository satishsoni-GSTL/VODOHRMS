import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
import '../services/auth_service.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'change_password_screen.dart';
import 'expenses_screen.dart';
import 'holidays_screen.dart';
import 'loans_screen.dart';
import 'payslips_screen.dart';
import 'policies_screen.dart';
import 'profile_screen.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  void _open(BuildContext context, Widget page) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));

  Future<void> _signOut(BuildContext context) async {
    final ok = await confirmDialog(context, 'Sign out?', 'You will need to sign in again on this phone.', confirm: 'Sign out');
    if (ok) await AuthService.instance.logout();
  }

  @override
  Widget build(BuildContext context) {
    final user = AuthService.instance.user;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('More')),
      body: ListView(children: [
        ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          leading: CircleAvatar(radius: 26, child: Text(user?.initials ?? '?', style: const TextStyle(fontWeight: FontWeight.w700))),
          title: Text(user?.name ?? '', style: theme.textTheme.titleMedium),
          subtitle: Text([user?.employeeCode, user?.designation, user?.department].whereType<String>().where((s) => s.isNotEmpty).join(' · ')),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => _open(context, const ProfileScreen()),
        ),
        const Divider(),
        _item(context, Icons.description_outlined, 'Salary slips', const PayslipsScreen()),
        _item(context, Icons.receipt_long_outlined, 'Expense claims', const ExpensesScreen()),
        _item(context, Icons.celebration_outlined, 'Holidays', const HolidaysScreen()),
        _item(context, Icons.account_balance_wallet_outlined, 'Loans & salary advance', const LoansScreen()),
        _item(context, Icons.policy_outlined, 'HR policies', const PoliciesScreen()),
        const Divider(),
        _item(context, Icons.lock_reset, 'Change password', const ChangePasswordScreen()),
        ListTile(
          leading: const Icon(Icons.privacy_tip_outlined),
          title: const Text('Privacy policy'),
          trailing: const Icon(Icons.open_in_new, size: 18),
          onTap: () async {
            final ok = await launchUrl(Uri.parse('${AuthService.instance.serverUrl}/privacy-policy'), mode: LaunchMode.externalApplication);
            if (!ok && context.mounted) showSnack(context, 'Could not open the browser.', error: true);
          },
        ),
        ListTile(
          leading: const Icon(Icons.info_outline),
          title: const Text('About'),
          onTap: () async {
            final info = await PackageInfo.fromPlatform();
            if (!context.mounted) return;
            showAboutDialog(
              context: context,
              applicationName: AppConfig.appName,
              applicationVersion: '${info.version} (${info.buildNumber})',
              applicationIcon: const BrandMark(size: 48),
              applicationLegalese: '© ${DateTime.now().year} GlobalSpace',
            );
          },
        ),
        ListTile(
          leading: Icon(Icons.logout, color: theme.colorScheme.error),
          title: Text('Sign out', style: TextStyle(color: theme.colorScheme.error)),
          onTap: () => _signOut(context),
        ),
      ]),
    );
  }

  Widget _item(BuildContext context, IconData icon, String label, Widget page) => ListTile(
        leading: Icon(icon),
        title: Text(label),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => _open(context, page),
      );
}
