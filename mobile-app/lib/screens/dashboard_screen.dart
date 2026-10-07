import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../services/auth_service.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'expenses_screen.dart';
import 'holidays_screen.dart';
import 'leave_form_screen.dart';
import 'payslips_screen.dart';
import 'wfh_form_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.onOpenTab});

  /// Switch bottom-nav tab: 'attendance' | 'requests' | 'approvals'.
  final ValueChanged<String> onOpenTab;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();

  Future<void> _clock(String type) async {
    try {
      final res = await ApiClient.instance.post('/attendance/clock', {'type': type});
      if (!mounted) return;
      showSnack(context, '${res['message']} ${res['time'] ?? ''}');
      _key.currentState?.reload();
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    }
  }

  Future<void> _open(Widget page) async {
    final changed = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => page));
    if (changed == true) _key.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    final user = AuthService.instance.user;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: const Padding(padding: EdgeInsets.all(10), child: BrandMark()),
        title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Hello, ${user?.name.split(' ').first ?? ''}', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
          Text([user?.employeeCode, user?.designation].whereType<String>().where((s) => s.isNotEmpty).join(' · '),
              style: theme.textTheme.bodySmall),
        ]),
      ),
      body: AsyncView<Map<String, dynamic>>(
        key: _key,
        load: () => ApiClient.instance.get('/dashboard'),
        builder: (context, data, reload) {
          final today = data['today'] as Map<String, dynamic>;
          final balances = (data['leave_balances'] as List).cast<Map<String, dynamic>>();
          final pending = data['my_pending'] as Map<String, dynamic>;
          final holidays = (data['upcoming_holidays'] as List).cast<Map<String, dynamic>>();
          final approvals = (data['approvals_waiting'] as num?)?.toInt() ?? 0;
          final celebrations = ((data['celebrations'] as List?) ?? const []).cast<Map<String, dynamic>>();

          return ListView(
            padding: const EdgeInsets.only(bottom: 24),
            children: [
              _TodayCard(today: today, onClock: _clock, onOpen: () => widget.onOpenTab('attendance')),
              if (approvals > 0)
                Card(
                  color: theme.colorScheme.tertiaryContainer,
                  child: ListTile(
                    leading: const Icon(Icons.fact_check_outlined),
                    title: Text('$approvals request${approvals == 1 ? '' : 's'} waiting for your approval'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => widget.onOpenTab('approvals'),
                  ),
                ),
              if ((pending['sent_back'] as num? ?? 0) > 0)
                Card(
                  color: theme.colorScheme.errorContainer,
                  child: ListTile(
                    leading: const Icon(Icons.undo),
                    title: Text('${pending['sent_back']} request(s) sent back to you for changes'),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => widget.onOpenTab('requests'),
                  ),
                ),
              if (celebrations.isNotEmpty) _Celebrations(items: celebrations),
              const SectionTitle('Quick actions'),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Wrap(spacing: 8, runSpacing: 8, children: [
                  _QuickAction(icon: Icons.beach_access_outlined, label: 'Apply Leave', onTap: () => _open(const LeaveFormScreen())),
                  _QuickAction(icon: Icons.home_work_outlined, label: 'Work From Home', onTap: () => _open(const WfhFormScreen())),
                  _QuickAction(icon: Icons.receipt_long_outlined, label: 'Expense Claim', onTap: () => _open(const ExpensesScreen())),
                  _QuickAction(icon: Icons.description_outlined, label: 'Payslips', onTap: () => _open(const PayslipsScreen())),
                ]),
              ),
              SectionTitle('Leave balance', trailing: TextButton(onPressed: () => widget.onOpenTab('requests'), child: const Text('View'))),
              if (balances.isEmpty)
                const Padding(padding: EdgeInsets.symmetric(horizontal: 16), child: Text('No leave balances for this year yet.'))
              else
                SizedBox(
                  height: 92,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: balances.length,
                    separatorBuilder: (_, _) => const SizedBox(width: 8),
                    itemBuilder: (_, i) => _BalanceTile(balance: balances[i]),
                  ),
                ),
              const SectionTitle('My open requests'),
              Card(
                child: Column(children: [
                  _PendingRow('Leave', pending['leave'], Icons.beach_access_outlined),
                  _PendingRow('Work From Home', pending['wfh'], Icons.home_work_outlined),
                  _PendingRow('Regularization', pending['regularization'], Icons.edit_calendar_outlined),
                  _PendingRow('Expense claims', pending['expense'], Icons.receipt_long_outlined),
                ]),
              ),
              SectionTitle('Upcoming holidays', trailing: TextButton(onPressed: () => _open(const HolidaysScreen()), child: const Text('All'))),
              if (holidays.isEmpty)
                const Padding(padding: EdgeInsets.symmetric(horizontal: 16), child: Text('No upcoming holidays.'))
              else
                Card(
                  child: Column(
                    children: holidays
                        .map((h) => ListTile(
                              dense: true,
                              leading: const Icon(Icons.celebration_outlined),
                              title: Text(h['name'] as String),
                              subtitle: Text(h['type_label'] as String),
                              trailing: Text(dayLabel(h['date'] as String?)),
                            ))
                        .toList(),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _TodayCard extends StatelessWidget {
  const _TodayCard({required this.today, required this.onClock, required this.onOpen});

  final Map<String, dynamic> today;
  final void Function(String type) onClock;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final canClock = today['can_clock'] == true;
    final firstIn = today['first_in'] as String?;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 6),
      decoration: BoxDecoration(gradient: Brand.headerGradient, borderRadius: BorderRadius.circular(16)),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text('Today · ${dayLabel(today['date'] as String?)}', style: theme.textTheme.titleSmall?.copyWith(color: Colors.white70)),
              const Spacer(),
              if (today['status_label'] != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(20)),
                  child: Text(today['status_label'] as String, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12)),
                ),
            ]),
            const SizedBox(height: 8),
            Row(children: [
              _TimeBlock('In', timeLabel(firstIn)),
              _TimeBlock('Out', timeLabel(today['last_out'] as String?)),
              _TimeBlock('Hours', today['hours'] == null ? '—' : num2(today['hours'] as num)),
            ]),
            if (canClock) ...[
              const SizedBox(height: 12),
              Row(children: [
                Expanded(
                  child: FilledButton.icon(
                    style: FilledButton.styleFrom(backgroundColor: Brand.orange, foregroundColor: Colors.white),
                    onPressed: () => onClock('in'),
                    icon: const Icon(Icons.login),
                    label: Text(firstIn == null ? 'Clock in' : 'Clock in again'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: Colors.white70)),
                    onPressed: firstIn == null ? null : () => onClock('out'),
                    icon: const Icon(Icons.logout),
                    label: const Text('Clock out'),
                  ),
                ),
              ]),
              const SizedBox(height: 4),
              Text('Approved Work From Home today — clock in and out from the app.', style: theme.textTheme.bodySmall?.copyWith(color: Colors.white70)),
            ],
          ]),
        ),
      ),
    );
  }
}

class _TimeBlock extends StatelessWidget {
  const _TimeBlock(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.white70)),
          Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700, color: Colors.white)),
        ]),
      );
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final width = (MediaQuery.of(context).size.width - 24 - 24) / 4;
    return SizedBox(
      width: width.clamp(70, 120),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Column(children: [
            CircleAvatar(
              radius: 24,
              backgroundColor: Brand.orange.withValues(alpha: 0.14),
              child: Icon(icon, color: Brand.orange),
            ),
            const SizedBox(height: 6),
            Text(label, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall, maxLines: 2),
          ]),
        ),
      ),
    );
  }
}

class _BalanceTile extends StatelessWidget {
  const _BalanceTile({required this.balance});

  final Map<String, dynamic> balance;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: 120,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(border: Border.all(color: theme.colorScheme.outlineVariant), borderRadius: BorderRadius.circular(12)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
        Text(num2(balance['available'] as num), style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
        Text(balance['name'] as String, maxLines: 2, overflow: TextOverflow.ellipsis, style: theme.textTheme.bodySmall),
      ]),
    );
  }
}

class _PendingRow extends StatelessWidget {
  const _PendingRow(this.label, this.count, this.icon);

  final String label;
  final dynamic count;
  final IconData icon;

  @override
  Widget build(BuildContext context) => ListTile(
        dense: true,
        leading: Icon(icon),
        title: Text(label),
        trailing: Text('${count ?? 0} pending', style: TextStyle(color: (count ?? 0) > 0 ? Theme.of(context).colorScheme.primary : null)),
      );
}

/// Company-wide birthdays & work anniversaries (all teams): today's as cards, then the week.
class _Celebrations extends StatelessWidget {
  const _Celebrations({required this.items});

  final List<Map<String, dynamic>> items;

  static String _what(Map<String, dynamic> c, {required bool today}) {
    if (c['type'] == 'birthday') return today ? 'Birthday today' : 'Birthday';
    final years = c['years'];
    return '$years-year work anniversary${today ? ' today' : ''}';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final today = items.where((c) => c['is_today'] == true).toList();
    final upcoming = items.where((c) => c['is_today'] != true).toList();

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SectionTitle('Celebrations'),
      if (today.isNotEmpty)
        SizedBox(
          height: 96,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: today.length,
            separatorBuilder: (_, _) => const SizedBox(width: 10),
            itemBuilder: (_, i) {
              final c = today[i];
              final birthday = c['type'] == 'birthday';
              final color = birthday ? Brand.orange : Brand.teal;
              return Container(
                width: 230,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.10),
                  border: Border.all(color: color.withValues(alpha: 0.35)),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(children: [
                  Text(birthday ? '🎂' : '🎉', style: const TextStyle(fontSize: 30)),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                      Text('${c['name']}${c['is_me'] == true ? ' (you)' : ''}',
                          maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                      Text(_what(c, today: true), maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 12)),
                      Text([c['designation'], c['department']].whereType<String>().join(' · '),
                          maxLines: 1, overflow: TextOverflow.ellipsis, style: theme.textTheme.bodySmall),
                    ]),
                  ),
                ]),
              );
            },
          ),
        ),
      if (upcoming.isNotEmpty)
        Card(
          margin: EdgeInsets.fromLTRB(16, today.isEmpty ? 0 : 10, 16, 0),
          child: Column(
            children: upcoming
                .map((c) => ListTile(
                      dense: true,
                      leading: Text(c['type'] == 'birthday' ? '🎂' : '🎉', style: const TextStyle(fontSize: 20)),
                      title: Text(c['name'] as String),
                      subtitle: Text([_what(c, today: false), c['department']].whereType<String>().join(' · ')),
                      trailing: Text(c['days_away'] == 1 ? 'Tomorrow' : dayLabel(c['date'] as String?)),
                    ))
                .toList(),
          ),
        ),
    ]);
  }
}

