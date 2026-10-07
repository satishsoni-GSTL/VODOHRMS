import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'approvals_screen.dart';
import 'attendance_screen.dart';

/// Manager's "My Team" tab: Approvals (if they approve) · Today · Leave · Requests.
/// Covers direct and indirect reports.
class TeamScreen extends StatefulWidget {
  const TeamScreen({super.key, required this.showApprovals, required this.showTeam});

  final bool showApprovals;
  final bool showTeam;

  @override
  State<TeamScreen> createState() => _TeamScreenState();
}

class _TeamScreenState extends State<TeamScreen> with SingleTickerProviderStateMixin {
  late final List<(String, Widget)> _tabs = [
    if (widget.showApprovals) ('Approvals', const ApprovalsScreen(embedded: true)),
    if (widget.showTeam) ...[
      ('Today', const _TeamTodayTab()),
      ('Leave', const _TeamLeaveTab()),
      ('Requests', const _TeamRequestsTab()),
    ],
  ];

  late final _controller = TabController(length: _tabs.length, vsync: this);

  /// Lets a push notification open straight onto Approvals.
  void showApprovals() {
    if (widget.showApprovals) _controller.animateTo(0);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.showTeam ? 'My Team' : 'Approvals'),
        bottom: _tabs.length > 1
            ? TabBar(controller: _controller, tabs: [for (final t in _tabs) Tab(text: t.$1)])
            : null,
      ),
      body: TabBarView(controller: _controller, children: [for (final t in _tabs) t.$2]),
    );
  }
}

// ---- Today -------------------------------------------------------------------------------

class _TeamTodayTab extends StatefulWidget {
  const _TeamTodayTab();

  @override
  State<_TeamTodayTab> createState() => _TeamTodayTabState();
}

class _TeamTodayTabState extends State<_TeamTodayTab> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  DateTime _date = DateTime.now();
  String? _filter;

  bool get _isToday => ymd(_date) == ymd(DateTime.now());

  void _setDate(DateTime d) {
    setState(() => _date = d);
    _key.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: Row(children: [
          IconButton(icon: const Icon(Icons.chevron_left), onPressed: () => _setDate(_date.subtract(const Duration(days: 1)))),
          Expanded(
            child: TextButton(
              onPressed: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _date,
                  firstDate: DateTime(DateTime.now().year - 1),
                  lastDate: DateTime.now(),
                );
                if (picked != null) _setDate(picked);
              },
              child: Text(_isToday ? 'Today · ${DateFormat('EEE, dd MMM').format(_date)}' : DateFormat('EEE, dd MMM yyyy').format(_date)),
            ),
          ),
          IconButton(icon: const Icon(Icons.chevron_right), onPressed: _isToday ? null : () => _setDate(_date.add(const Duration(days: 1)))),
        ]),
      ),
      Expanded(
        child: AsyncView<Map<String, dynamic>>(
          key: _key,
          load: () => ApiClient.instance.get('/team', query: {'date': ymd(_date)}),
          builder: (context, data, _) {
            final s = data['summary'] as Map<String, dynamic>;
            final all = (data['members'] as List).cast<Map<String, dynamic>>();
            final members = _filter == null ? all : all.where((m) => _bucket(m['status_code'] as String) == _filter).toList();

            return ListView(padding: const EdgeInsets.only(bottom: 24), children: [
              _SummaryCard(summary: s, selected: _filter, onSelect: (f) => setState(() => _filter = _filter == f ? null : f)),
              if (members.isEmpty)
                const Padding(padding: EdgeInsets.all(32), child: Text('Nobody in this group.', textAlign: TextAlign.center))
              else
                ...members.map((m) => _MemberTile(member: m)),
            ]);
          },
        ),
      ),
    ]);
  }

  static String _bucket(String code) => switch (code) {
        'P' || 'HD' || 'OD' || 'MP' => 'present',
        'WFH' => 'wfh',
        'L' => 'on_leave',
        'A' => 'absent',
        'WO' || 'H' => 'off',
        _ => 'not_marked',
      };
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary, required this.selected, required this.onSelect});

  final Map<String, dynamic> summary;
  final String? selected;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final items = [
      ('present', 'In office', attendanceColor('P')),
      ('wfh', 'WFH', attendanceColor('WFH')),
      ('on_leave', 'On leave', attendanceColor('L')),
      ('absent', 'Absent', attendanceColor('A')),
      ('not_marked', 'Not in yet', Colors.blueGrey),
      ('off', 'Off', attendanceColor('WO')),
    ];

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 4, 16, 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(gradient: Brand.headerGradient, borderRadius: BorderRadius.circular(16)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('${summary['total']} team member${summary['total'] == 1 ? '' : 's'}',
            style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
        const SizedBox(height: 12),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final i in items)
            if ((summary[i.$1] as num? ?? 0) > 0)
              InkWell(
                onTap: () => onSelect(i.$1),
                borderRadius: BorderRadius.circular(20),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: selected == i.$1 ? Colors.white : Colors.white.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text('${i.$2} ${summary[i.$1]}',
                      style: TextStyle(color: selected == i.$1 ? Brand.tealDark : Colors.white, fontWeight: FontWeight.w600)),
                ),
              ),
        ]),
      ]),
    );
  }
}

class _MemberTile extends StatelessWidget {
  const _MemberTile({required this.member});

  final Map<String, dynamic> member;

  @override
  Widget build(BuildContext context) {
    final code = member['status_code'] as String;
    final color = code.isEmpty ? Colors.blueGrey : attendanceColor(code);
    final name = member['name'] as String;
    final firstIn = member['first_in'] as String?;

    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Brand.teal.withValues(alpha: 0.12),
          foregroundColor: Brand.tealDark,
          child: Text(name.isEmpty ? '?' : name.trim().split(RegExp(r'\s+')).map((p) => p[0]).take(2).join().toUpperCase()),
        ),
        title: Text(name),
        subtitle: Text([
          member['designation'] as String?,
          if (firstIn != null) 'In ${timeLabel(firstIn)}${member['last_out'] != null ? ' · Out ${timeLabel(member['last_out'] as String?)}' : ''}',
          if (member['is_direct'] != true) 'Indirect report',
        ].whereType<String>().where((s) => s.isNotEmpty).join(' · ')),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(20)),
          child: Text(member['status_label'] as String? ?? '', style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
        ),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => AttendanceScreen(memberId: member['id'] as int, memberName: name),
        )),
      ),
    );
  }
}

// ---- Leave -------------------------------------------------------------------------------

class _TeamLeaveTab extends StatefulWidget {
  const _TeamLeaveTab();

  @override
  State<_TeamLeaveTab> createState() => _TeamLeaveTabState();
}

class _TeamLeaveTabState extends State<_TeamLeaveTab> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);

  void _shift(int m) {
    setState(() => _month = DateTime(_month.year, _month.month + m));
    _key.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: Row(children: [
          IconButton(icon: const Icon(Icons.chevron_left), onPressed: () => _shift(-1)),
          Expanded(child: Text(DateFormat('MMMM yyyy').format(_month), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium)),
          IconButton(icon: const Icon(Icons.chevron_right), onPressed: () => _shift(1)),
        ]),
      ),
      Expanded(
        child: AsyncView<Map<String, dynamic>>(
          key: _key,
          load: () => ApiClient.instance.get('/team/leave', query: {'month': DateFormat('yyyy-MM').format(_month)}),
          builder: (context, data, _) {
            final items = (data['items'] as List).cast<Map<String, dynamic>>();
            if (items.isEmpty) return const EmptyState(icon: Icons.beach_access_outlined, message: 'No team leave this month.');

            return ListView(
              padding: const EdgeInsets.only(bottom: 24),
              children: items.map((a) {
                final emp = a['employee'] as Map<String, dynamic>;
                return Card(
                  child: ListTile(
                    title: Text('${emp['name']} · ${a['leave_type']}'),
                    subtitle: Text('${dateRange(a['from_date'] as String?, a['to_date'] as String?)} · ${daysLabel(a['days'] as num?)}'
                        '${a['is_half_day'] == true ? ' (half day)' : ''}'),
                    trailing: StatusChip(status: a['status'] as String?, label: a['status_label'] as String? ?? ''),
                  ),
                );
              }).toList(),
            );
          },
        ),
      ),
    ]);
  }
}

// ---- Requests ----------------------------------------------------------------------------

class _TeamRequestsTab extends StatefulWidget {
  const _TeamRequestsTab();

  @override
  State<_TeamRequestsTab> createState() => _TeamRequestsTabState();
}

class _TeamRequestsTabState extends State<_TeamRequestsTab> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  String _type = 'wfh';

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
        child: SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'wfh', label: Text('WFH')),
            ButtonSegment(value: 'regularization', label: Text('Regularize')),
            ButtonSegment(value: 'expense', label: Text('Expense')),
          ],
          selected: {_type},
          onSelectionChanged: (s) {
            setState(() => _type = s.first);
            _key.currentState?.reload();
          },
        ),
      ),
      Expanded(
        child: AsyncView<Map<String, dynamic>>(
          key: _key,
          load: () => ApiClient.instance.get('/team/requests', query: {'type': _type}),
          builder: (context, data, _) {
            final items = (data['items'] as List).cast<Map<String, dynamic>>();
            if (items.isEmpty) return const EmptyState(icon: Icons.inbox_outlined, message: 'No requests from your team.');

            return ListView(
              padding: const EdgeInsets.only(top: 4, bottom: 24),
              children: items.map((r) {
                final emp = r['employee'] as Map<String, dynamic>;
                final remarks = r['remarks'] as String?;
                return Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Expanded(child: Text(emp['name'] as String, style: const TextStyle(fontWeight: FontWeight.w600))),
                        StatusChip(status: r['status'] as String?, label: r['status_label'] as String? ?? ''),
                      ]),
                      const SizedBox(height: 4),
                      Text(r['title'] as String),
                      Text(r['subtitle'] as String? ?? '', style: Theme.of(context).textTheme.bodySmall),
                      if ((r['detail'] as String?)?.isNotEmpty == true)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(r['detail'] as String, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall),
                        ),
                      if (remarks != null && remarks.isNotEmpty) RemarksBox(remarks),
                    ]),
                  ),
                );
              }).toList(),
            );
          },
        ),
      ),
    ]);
  }
}
