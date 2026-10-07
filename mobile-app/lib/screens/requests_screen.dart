import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'leave_form_screen.dart';
import 'regularization_form_screen.dart';
import 'wfh_form_screen.dart';

/// My Leave, Work From Home and Regularization requests, each with status, approver
/// remarks and "Reapply" for rejected / sent-back ones.
class RequestsScreen extends StatefulWidget {
  const RequestsScreen({super.key});

  @override
  State<RequestsScreen> createState() => _RequestsScreenState();
}

class _RequestsScreenState extends State<RequestsScreen> with SingleTickerProviderStateMixin {
  late final _tabs = TabController(length: 3, vsync: this)..addListener(() => setState(() {}));
  final _leaveKey = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  final _wfhKey = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  final _regKey = GlobalKey<AsyncViewState<Map<String, dynamic>>>();

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _open(Widget page, GlobalKey<AsyncViewState<Map<String, dynamic>>> key) async {
    final done = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => page));
    if (done == true) key.currentState?.reload();
  }

  void _new() => switch (_tabs.index) {
        0 => _open(const LeaveFormScreen(), _leaveKey),
        1 => _open(const WfhFormScreen(), _wfhKey),
        _ => _open(const RegularizationFormScreen(), _regKey),
      };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Requests'),
        bottom: TabBar(controller: _tabs, tabs: const [Tab(text: 'Leave'), Tab(text: 'WFH'), Tab(text: 'Regularization')]),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _new,
        icon: const Icon(Icons.add),
        label: Text(switch (_tabs.index) { 0 => 'Apply leave', 1 => 'Request WFH', _ => 'Regularize' }),
      ),
      body: TabBarView(controller: _tabs, children: [
        _LeaveTab(viewKey: _leaveKey, onReapply: (a) => _open(LeaveFormScreen(reapplyFrom: a), _leaveKey)),
        _WfhTab(viewKey: _wfhKey, onReapply: (r) => _open(WfhFormScreen(reapplyFrom: r), _wfhKey)),
        _RegularizationTab(
          viewKey: _regKey,
          onReapply: (r) => _open(
            RegularizationFormScreen(
              date: parseDate(r['attendance_date'] as String?),
              requestType: r['request_type'] as String?,
              firstIn: r['first_in'] as String?,
              lastOut: r['last_out'] as String?,
              reason: r['reason'] as String?,
            ),
            _regKey,
          ),
        ),
      ]),
    );
  }
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({required this.title, required this.subtitle, required this.item, this.onReapply, this.extra});

  final String title;
  final String subtitle;
  final Map<String, dynamic> item;
  final VoidCallback? onReapply;
  final String? extra;

  @override
  Widget build(BuildContext context) {
    final remarks = item['remarks'] as String?;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600))),
            StatusChip(status: item['status'] as String?, label: item['status_label'] as String? ?? ''),
          ]),
          const SizedBox(height: 4),
          Text(subtitle),
          if (extra != null && extra!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(extra!, style: Theme.of(context).textTheme.bodySmall, maxLines: 3, overflow: TextOverflow.ellipsis),
          ],
          if (remarks != null && remarks.isNotEmpty) RemarksBox(remarks),
          if (item['can_reapply'] == true && onReapply != null)
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(onPressed: onReapply, icon: const Icon(Icons.refresh, size: 18), label: const Text('Reapply')),
            ),
        ]),
      ),
    );
  }
}

class _LeaveTab extends StatelessWidget {
  const _LeaveTab({required this.viewKey, required this.onReapply});

  final GlobalKey<AsyncViewState<Map<String, dynamic>>> viewKey;
  final ValueChanged<Map<String, dynamic>> onReapply;

  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        key: viewKey,
        load: () => ApiClient.instance.get('/leave'),
        builder: (context, data, _) {
          final types = (data['types'] as List).cast<Map<String, dynamic>>();
          final apps = (data['applications'] as List).cast<Map<String, dynamic>>();

          return ListView(padding: const EdgeInsets.only(bottom: 96), children: [
            SectionTitle('Balance ${data['year']}'),
            Card(
              child: Column(
                children: types
                    .map((t) {
                      final b = t['balance'] as Map<String, dynamic>;
                      return ListTile(
                        dense: true,
                        title: Text(t['name'] as String),
                        subtitle: Text('Credited ${num2(b['credited'] as num)} · Used ${num2(b['used'] as num)}${t['is_paid'] == true ? '' : ' · Unpaid'}'),
                        trailing: Text(num2(b['available'] as num), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                      );
                    })
                    .toList(),
              ),
            ),
            const SectionTitle('Applications'),
            if (apps.isEmpty)
              const Padding(padding: EdgeInsets.all(24), child: Text('No leave applications this year.', textAlign: TextAlign.center))
            else
              ...apps.map((a) => _RequestCard(
                    title: '${a['leave_type']} · ${daysLabel(a['days'] as num?)}${a['is_half_day'] == true ? ' (half day)' : ''}',
                    subtitle: dateRange(a['from_date'] as String?, a['to_date'] as String?),
                    extra: a['reason'] as String?,
                    item: a,
                    onReapply: () => onReapply(a),
                  )),
          ]);
        },
      );
}

class _WfhTab extends StatelessWidget {
  const _WfhTab({required this.viewKey, required this.onReapply});

  final GlobalKey<AsyncViewState<Map<String, dynamic>>> viewKey;
  final ValueChanged<Map<String, dynamic>> onReapply;

  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        key: viewKey,
        load: () => ApiClient.instance.get('/wfh'),
        builder: (context, data, _) {
          final items = (data['items'] as List).cast<Map<String, dynamic>>();
          if (items.isEmpty) return const EmptyState(icon: Icons.home_work_outlined, message: 'No Work From Home requests yet.');

          return ListView(
            padding: const EdgeInsets.only(top: 8, bottom: 96),
            children: items
                .map((r) => _RequestCard(
                      title: 'Work From Home · ${daysLabel(r['working_days'] as num?)}',
                      subtitle: dateRange(r['from_date'] as String?, r['to_date'] as String?),
                      extra: r['reason'] as String?,
                      item: r,
                      onReapply: () => onReapply(r),
                    ))
                .toList(),
          );
        },
      );
}

class _RegularizationTab extends StatelessWidget {
  const _RegularizationTab({required this.viewKey, required this.onReapply});

  final GlobalKey<AsyncViewState<Map<String, dynamic>>> viewKey;
  final ValueChanged<Map<String, dynamic>> onReapply;

  @override
  Widget build(BuildContext context) => AsyncView<Map<String, dynamic>>(
        key: viewKey,
        load: () => ApiClient.instance.get('/regularizations'),
        builder: (context, data, _) {
          final items = (data['items'] as List).cast<Map<String, dynamic>>();
          if (items.isEmpty) return const EmptyState(icon: Icons.edit_calendar_outlined, message: 'No regularization requests yet.');

          return ListView(
            padding: const EdgeInsets.only(top: 8, bottom: 96),
            children: items
                .map((r) => _RequestCard(
                      title: r['request_type_label'] as String? ?? 'Regularization',
                      subtitle: '${dateLabel(r['attendance_date'] as String?)}'
                          '${r['first_in'] != null ? ' · In ${timeLabel(r['first_in'] as String?)}' : ''}'
                          '${r['last_out'] != null ? ' · Out ${timeLabel(r['last_out'] as String?)}' : ''}',
                      extra: r['reason'] as String?,
                      item: r,
                      onReapply: () => onReapply(r),
                    ))
                .toList(),
          );
        },
      );
}
