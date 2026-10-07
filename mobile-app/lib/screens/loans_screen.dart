import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

class LoansScreen extends StatefulWidget {
  const LoansScreen({super.key});

  @override
  State<LoansScreen> createState() => _LoansScreenState();
}

class _LoansScreenState extends State<LoansScreen> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();

  Future<void> _request({Map<String, dynamic>? from}) async {
    final done = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _LoanForm(from: from),
    );
    if (done == true) _key.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Loans & Salary Advance')),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => _request(), icon: const Icon(Icons.add), label: const Text('Request')),
      body: AsyncView<Map<String, dynamic>>(
        key: _key,
        load: () => ApiClient.instance.get('/loans'),
        builder: (context, data, _) {
          final items = (data['items'] as List).cast<Map<String, dynamic>>();
          if (items.isEmpty) return const EmptyState(icon: Icons.account_balance_wallet_outlined, message: 'No loan or advance requests.');

          return ListView(
            padding: const EdgeInsets.only(top: 8, bottom: 96),
            children: items.map((l) {
              final remarks = l['remarks'] as String?;
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Expanded(child: Text('${l['type_label']} · ${money(l['requested_amount'] as num?)}', style: const TextStyle(fontWeight: FontWeight.w600))),
                      StatusChip(status: l['status'] as String?, label: l['status_label'] as String? ?? ''),
                    ]),
                    const SizedBox(height: 4),
                    Text('Requested ${dateLabel(l['request_date'] as String?)}'),
                    if (l['approved_amount'] != null) Text('Approved ${money(l['approved_amount'] as num?)}'
                        '${l['monthly_recovery'] != null ? ' · ${money(l['monthly_recovery'] as num?)}/month' : ''}'),
                    if ((l['outstanding_balance'] as num? ?? 0) > 0) Text('Outstanding ${money(l['outstanding_balance'] as num?)}'),
                    if (remarks != null && remarks.isNotEmpty) RemarksBox(remarks),
                    if (l['can_reapply'] == true)
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton.icon(onPressed: () => _request(from: l), icon: const Icon(Icons.refresh, size: 18), label: const Text('Reapply')),
                      ),
                  ]),
                ),
              );
            }).toList(),
          );
        },
      ),
    );
  }
}

class _LoanForm extends StatefulWidget {
  const _LoanForm({this.from});

  final Map<String, dynamic>? from;

  @override
  State<_LoanForm> createState() => _LoanFormState();
}

class _LoanFormState extends State<_LoanForm> {
  final _formKey = GlobalKey<FormState>();
  late String _type = widget.from?['type'] as String? ?? 'salary_advance';
  late final _amount = TextEditingController(text: widget.from == null ? '' : num2(widget.from!['requested_amount'] as num?));
  late final _reason = TextEditingController(text: widget.from?['reason'] as String?);
  bool _busy = false;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final res = await ApiClient.instance.post('/loans', {'type': _type, 'requested_amount': _amount.text.trim(), 'reason': _reason.text.trim()});
      if (!mounted) return;
      showSnack(context, res['message'] as String? ?? 'Submitted.');
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: Form(
        key: _formKey,
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text('Request loan / salary advance', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 16),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'salary_advance', label: Text('Salary advance')),
              ButtonSegment(value: 'loan', label: Text('Loan')),
            ],
            selected: {_type},
            onSelectionChanged: (s) => setState(() => _type = s.first),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Amount *', prefixText: '₹ ', border: OutlineInputBorder()),
            validator: (v) => (double.tryParse(v ?? '') ?? 0) <= 0 ? 'Enter amount' : null,
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _reason,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Reason *', border: OutlineInputBorder()),
            validator: (v) => v == null || v.trim().isEmpty ? 'Required' : null,
          ),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : _submit, child: _busy ? const CircularProgressIndicator() : const Text('Submit for approval')),
        ]),
      ),
    );
  }
}
