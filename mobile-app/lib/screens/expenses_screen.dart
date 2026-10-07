import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'expense_form_screen.dart';

class ExpensesScreen extends StatefulWidget {
  const ExpensesScreen({super.key});

  @override
  State<ExpensesScreen> createState() => _ExpensesScreenState();
}

class _ExpensesScreenState extends State<ExpensesScreen> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  Map<String, dynamic>? _lookups;

  Future<Map<String, dynamic>> _load() async {
    final data = await ApiClient.instance.get('/expenses');
    _lookups = data;
    return data;
  }

  Future<void> _newClaim() async {
    final lookups = _lookups ?? await _load();
    if (!mounted) return;
    final done = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => ExpenseFormScreen(lookups: lookups)));
    if (done == true) _key.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Expense Claims')),
      floatingActionButton: FloatingActionButton.extended(onPressed: _newClaim, icon: const Icon(Icons.add), label: const Text('New claim')),
      body: AsyncView<Map<String, dynamic>>(
        key: _key,
        load: _load,
        builder: (context, data, _) {
          final claims = (data['claims'] as List).cast<Map<String, dynamic>>();
          if (claims.isEmpty) return const EmptyState(icon: Icons.receipt_long_outlined, message: 'No expense claims yet.');

          return ListView.builder(
            padding: const EdgeInsets.only(top: 8, bottom: 96),
            itemCount: claims.length,
            itemBuilder: (context, i) {
              final c = claims[i];
              return Card(
                child: ListTile(
                  title: Text('${c['claim_number']}'),
                  subtitle: Text('${dateLabel(c['claim_date'] as String?)} · ${c['items']} item(s)'
                      '${(c['project_client'] as String?)?.isNotEmpty == true ? ' · ${c['project_client']}' : ''}'),
                  trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text(money(c['total_requested'] as num?), style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    StatusChip(status: c['status'] as String?, label: c['status_label'] as String? ?? ''),
                  ]),
                  onTap: () async {
                    final changed = await Navigator.of(context).push<bool>(MaterialPageRoute(
                      builder: (_) => ExpenseDetailScreen(id: c['id'] as int, lookups: data),
                    ));
                    if (changed == true) _key.currentState?.reload();
                  },
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class ExpenseDetailScreen extends StatefulWidget {
  const ExpenseDetailScreen({super.key, required this.id, required this.lookups});

  final int id;
  final Map<String, dynamic> lookups;

  @override
  State<ExpenseDetailScreen> createState() => _ExpenseDetailScreenState();
}

class _ExpenseDetailScreenState extends State<ExpenseDetailScreen> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  bool _changed = false;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.pop(context, _changed);
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Expense Claim')),
        body: AsyncView<Map<String, dynamic>>(
          key: _key,
          load: () => ApiClient.instance.get('/expenses/${widget.id}'),
          builder: (context, data, _) {
            final c = data['claim'] as Map<String, dynamic>;
            final lines = (c['lines'] as List).cast<Map<String, dynamic>>();
            final trail = (c['approval_trail'] as List).cast<Map<String, dynamic>>();
            final paid = c['paid'] as Map<String, dynamic>?;

            return ListView(padding: const EdgeInsets.only(bottom: 24), children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Expanded(child: Text(c['claim_number'] as String, style: Theme.of(context).textTheme.titleMedium)),
                      StatusChip(status: c['status'] as String?, label: c['status_label'] as String? ?? ''),
                    ]),
                    const SizedBox(height: 8),
                    InfoRow('Claim date', dateLabel(c['claim_date'] as String?)),
                    InfoRow('Project / client', c['project_client'] as String?),
                    InfoRow('Requested', money(c['total_requested'] as num?)),
                    if ((c['total_approved'] as num? ?? 0) > 0) InfoRow('Approved', money(c['total_approved'] as num?)),
                    if (paid != null) InfoRow('Paid', '${money(paid['amount'] as num?)} on ${dateLabel(paid['paid_on'] as String?)}'),
                  ]),
                ),
              ),
              if (c['can_resubmit'] == true)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                  child: FilledButton.icon(
                    icon: const Icon(Icons.edit_outlined),
                    label: const Text('Correct & resubmit'),
                    onPressed: () async {
                      final done = await Navigator.of(context).push<bool>(MaterialPageRoute(
                        builder: (_) => ExpenseFormScreen(lookups: widget.lookups, existing: c),
                      ));
                      if (done == true) {
                        _changed = true;
                        _key.currentState?.reload();
                      }
                    },
                  ),
                ),
              const SectionTitle('Items'),
              ...lines.map((l) => Card(
                    child: ListTile(
                      title: Text('${l['category']} · ${money(l['requested_amount'] as num?)}'),
                      subtitle: Text([
                        dateLabel(l['expense_date'] as String?),
                        if ((l['vendor'] as String?)?.isNotEmpty == true) l['vendor'],
                        if ((l['bill_number'] as String?)?.isNotEmpty == true) 'Bill ${l['bill_number']}',
                        if (l['approved_amount'] != null) 'Approved ${money(l['approved_amount'] as num?)}',
                        if ((l['description'] as String?)?.isNotEmpty == true) l['description'],
                      ].join(' · ')),
                      trailing: l['has_receipt'] == true
                          ? IconButton(
                              tooltip: 'View receipt',
                              icon: const Icon(Icons.receipt_outlined),
                              onPressed: () => downloadAndOpen(context, '/expense-lines/${l['id']}/receipt', 'receipt-${l['id']}'),
                            )
                          : null,
                    ),
                  )),
              if (trail.isNotEmpty) ...[
                const SectionTitle('Approval history'),
                ...trail.map((t) => ListTile(
                      dense: true,
                      leading: Icon(t['action'] == 'approve' ? Icons.check_circle_outline : Icons.info_outline),
                      title: Text('${t['action_label']} by ${t['by'] ?? '—'}'),
                      subtitle: (t['remarks'] as String?)?.isNotEmpty == true ? Text(t['remarks'] as String) : null,
                      trailing: Text(dateLabel((t['at'] as String?)?.substring(0, 10))),
                    )),
              ],
            ]);
          },
        ),
      ),
    );
  }
}
