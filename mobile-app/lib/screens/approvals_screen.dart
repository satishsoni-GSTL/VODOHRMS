import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

/// Requests waiting for my approval (leave, WFH, regularization, expense, loan, resignation).
/// Approve, reject or send back — reject and send back need remarks, which the employee sees.
class ApprovalsScreen extends StatefulWidget {
  const ApprovalsScreen({super.key, this.embedded = false});

  /// Inside the Team tab (no own app bar).
  final bool embedded;

  @override
  State<ApprovalsScreen> createState() => _ApprovalsScreenState();
}

class _ApprovalsScreenState extends State<ApprovalsScreen> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  final Set<int> _busy = {};

  static const _icons = {
    'leave': Icons.beach_access_outlined,
    'wfh': Icons.home_work_outlined,
    'regularization': Icons.edit_calendar_outlined,
    'expense': Icons.receipt_long_outlined,
    'loan': Icons.account_balance_wallet_outlined,
    'resignation': Icons.exit_to_app,
  };

  Future<void> _act(Map<String, dynamic> item, String action) async {
    String? remarks;

    if (action == 'approve') {
      final ok = await confirmDialog(context, 'Approve?', '${item['title']} for ${(item['employee'] as Map)['name']}', confirm: 'Approve');
      if (!ok) return;
    } else {
      if (!mounted) return;
      remarks = await askRemarks(
        context,
        title: action == 'reject' ? 'Reject request' : 'Send back for changes',
        label: action == 'reject' ? 'Reason for rejection' : 'What needs to change',
        confirm: action == 'reject' ? 'Reject' : 'Send back',
      );
      if (remarks == null) return;
    }

    final id = item['id'] as int;
    setState(() => _busy.add(id));
    try {
      final res = await ApiClient.instance.post('/approvals/$id', {'action': action, 'remarks': remarks});
      if (!mounted) return;
      showSnack(context, res['message'] as String? ?? 'Done.');
      await _key.currentState?.reload();
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final body = AsyncView<Map<String, dynamic>>(
        key: _key,
        load: () => ApiClient.instance.get('/approvals'),
        builder: (context, data, _) {
          final items = (data['items'] as List).cast<Map<String, dynamic>>();
          if (items.isEmpty) return const EmptyState(icon: Icons.task_alt, message: 'Nothing waiting for your approval.');

          return ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: items.length,
            itemBuilder: (context, i) {
              final item = items[i];
              final employee = item['employee'] as Map<String, dynamic>;
              final details = (item['details'] as List).cast<Map<String, dynamic>>();
              final busy = _busy.contains(item['id']);

              return Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Icon(_icons[item['type']] ?? Icons.assignment_outlined),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(item['title'] as String, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                          Text('${employee['name']} · ${employee['employee_code']}', style: Theme.of(context).textTheme.bodySmall),
                        ]),
                      ),
                      Text(dateLabel((item['submitted_at'] as String?)?.substring(0, 10)), style: Theme.of(context).textTheme.bodySmall),
                    ]),
                    const Divider(height: 20),
                    ...details.map((d) => InfoRow(d['label'] as String, d['value'] as String?)),
                    const SizedBox(height: 8),
                    if (busy)
                      const Padding(padding: EdgeInsets.all(8), child: Center(child: CircularProgressIndicator()))
                    else
                      Row(children: [
                        TextButton(
                          onPressed: () => _act(item, 'reject'),
                          style: TextButton.styleFrom(foregroundColor: Theme.of(context).colorScheme.error),
                          child: const Text('Reject'),
                        ),
                        TextButton(onPressed: () => _act(item, 'send_back'), child: const Text('Send back')),
                        const Spacer(),
                        FilledButton.icon(onPressed: () => _act(item, 'approve'), icon: const Icon(Icons.check), label: const Text('Approve')),
                      ]),
                  ]),
                ),
              );
            },
          );
        },
      );

    return widget.embedded ? body : Scaffold(appBar: AppBar(title: const Text('Approvals')), body: body);
  }
}
