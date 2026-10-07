import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'regularization_form_screen.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key, this.memberId, this.memberName});

  /// Set when a manager views a team member's month (read-only).
  final int? memberId;
  final String? memberName;

  bool get isTeamMember => memberId != null;

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();

  String get _ym => DateFormat('yyyy-MM').format(_month);

  void _shift(int months) {
    final next = DateTime(_month.year, _month.month + months);
    if (next.isAfter(DateTime.now())) return;
    setState(() => _month = next);
    _key.currentState?.reload();
  }

  Future<void> _regularize(Map<String, dynamic>? day) async {
    final done = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => RegularizationFormScreen(
        date: day == null ? null : parseDate(day['date'] as String?),
        firstIn: day?['first_in'] as String?,
        lastOut: day?['last_out'] as String?,
      ),
    ));
    if (done == true) _key.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    final isCurrentMonth = _month.year == DateTime.now().year && _month.month == DateTime.now().month;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.memberName ?? 'My Attendance'),
        actions: [
          if (!widget.isTeamMember)
            IconButton(tooltip: 'Request regularization', icon: const Icon(Icons.edit_calendar_outlined), onPressed: () => _regularize(null)),
        ],
      ),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          child: Row(children: [
            IconButton(icon: const Icon(Icons.chevron_left), onPressed: () => _shift(-1)),
            Expanded(child: Text(DateFormat('MMMM yyyy').format(_month), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium)),
            IconButton(icon: const Icon(Icons.chevron_right), onPressed: isCurrentMonth ? null : () => _shift(1)),
          ]),
        ),
        Expanded(
          child: AsyncView<Map<String, dynamic>>(
            key: _key,
            load: () => ApiClient.instance.get(
              widget.isTeamMember ? '/team/${widget.memberId}/attendance' : '/attendance',
              query: {'month': _ym},
            ),
            builder: (context, data, reload) {
              final totals = data['totals'] as Map<String, dynamic>;
              final days = (data['days'] as List).cast<Map<String, dynamic>>().reversed.toList();
              final today = ymd(DateTime.now());

              return ListView(
                padding: const EdgeInsets.only(bottom: 24),
                children: [
                  _Totals(totals: totals),
                  ...days
                      .where((d) => (d['date'] as String).compareTo(today) <= 0)
                      .map((d) => _DayTile(day: d, onTap: widget.isTeamMember ? null : () => _regularize(d))),
                  if (!widget.isTeamMember)
                    const Padding(
                      padding: EdgeInsets.all(16),
                      child: Text('Tap a day to request a correction (missed punch, wrong time, on duty…).', textAlign: TextAlign.center),
                    ),
                ],
              );
            },
          ),
        ),
      ]),
    );
  }
}

class _Totals extends StatelessWidget {
  const _Totals({required this.totals});

  final Map<String, dynamic> totals;

  @override
  Widget build(BuildContext context) {
    final items = [
      ('P', 'Present', totals['present']),
      ('WFH', 'WFH', totals['wfh']),
      ('HD', 'Half day', totals['half_day']),
      ('L', 'Leave', totals['leave']),
      ('A', 'Absent', totals['absent']),
      ('MP', 'Missed punch', totals['missing_punch']),
      ('H', 'Holiday', totals['holiday']),
      ('WO', 'Week off', totals['weekly_off']),
    ];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: items
                .where((i) => (i.$3 as num? ?? 0) > 0 || i.$1 == 'P' || i.$1 == 'A')
                .map((i) => Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(color: attendanceColor(i.$1).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
                      child: Text('${i.$2}: ${i.$3}', style: TextStyle(color: attendanceColor(i.$1), fontWeight: FontWeight.w600)),
                    ))
                .toList(),
          ),
          const SizedBox(height: 8),
          Text('Worked ${num2(totals['hours'] as num?)} h · avg ${num2(totals['avg_hours'] as num?)} h/day',
              style: Theme.of(context).textTheme.bodySmall),
        ]),
      ),
    );
  }
}

class _DayTile extends StatelessWidget {
  const _DayTile({required this.day, required this.onTap});

  final Map<String, dynamic> day;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final code = day['code'] as String;
    final color = attendanceColor(code);
    final firstIn = day['first_in'] as String?;
    final lastOut = day['last_out'] as String?;
    final hours = day['hours'] as num?;
    final date = parseDate(day['date'] as String?)!;

    return ListTile(
      onTap: onTap,
      leading: Container(
        width: 44,
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(10)),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Text(DateFormat('dd').format(date), style: TextStyle(fontWeight: FontWeight.w700, color: color)),
          Text(DateFormat('EEE').format(date), style: TextStyle(fontSize: 10, color: color)),
        ]),
      ),
      title: Text((day['label'] as String?)?.isNotEmpty == true ? day['label'] as String : '—'),
      subtitle: firstIn == null ? null : Text('In ${timeLabel(firstIn)}  ·  Out ${lastOut == null ? '—' : timeLabel(lastOut)}'),
      trailing: hours == null ? null : Text('${num2(hours)} h', style: Theme.of(context).textTheme.titleSmall),
    );
  }
}
