import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

class _Line {
  _Line({this.existingId, this.categoryId, this.date, String? amount, String? vendor, String? bill, this.paymentMode, String? description, this.hasExistingReceipt = false})
      : amount = TextEditingController(text: amount),
        vendor = TextEditingController(text: vendor),
        bill = TextEditingController(text: bill),
        description = TextEditingController(text: description);

  final int? existingId;
  int? categoryId;
  DateTime? date;
  final TextEditingController amount;
  final TextEditingController vendor;
  final TextEditingController bill;
  String? paymentMode;
  final TextEditingController description;
  String? receipt;
  bool hasExistingReceipt;

  void dispose() {
    amount.dispose();
    vendor.dispose();
    bill.dispose();
    description.dispose();
  }
}

/// New expense claim with one or more items (each with an optional receipt photo / PDF),
/// or — with [existing] — correct and resubmit a sent-back claim. Pops `true` on success.
class ExpenseFormScreen extends StatefulWidget {
  const ExpenseFormScreen({super.key, required this.lookups, this.existing});

  /// From GET /expenses: categories + payment_modes.
  final Map<String, dynamic> lookups;
  final Map<String, dynamic>? existing;

  @override
  State<ExpenseFormScreen> createState() => _ExpenseFormScreenState();
}

class _ExpenseFormScreenState extends State<ExpenseFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late DateTime? _claimDate = parseDate(widget.existing?['claim_date'] as String?) ?? DateTime.now();
  late final _project = TextEditingController(text: widget.existing?['project_client'] as String?);
  final List<_Line> _lines = [];
  bool _busy = false;

  List<Map<String, dynamic>> get _categories => (widget.lookups['categories'] as List).cast<Map<String, dynamic>>();
  List<Map<String, dynamic>> get _modes => (widget.lookups['payment_modes'] as List).cast<Map<String, dynamic>>();

  @override
  void initState() {
    super.initState();
    final existing = (widget.existing?['lines'] as List?)?.cast<Map<String, dynamic>>();
    if (existing != null && existing.isNotEmpty) {
      _lines.addAll(existing.map((l) => _Line(
            existingId: l['id'] as int?,
            categoryId: l['category_id'] as int?,
            date: parseDate(l['expense_date'] as String?),
            amount: num2(l['requested_amount'] as num?),
            vendor: l['vendor'] as String?,
            bill: l['bill_number'] as String?,
            paymentMode: l['payment_mode'] as String?,
            description: l['description'] as String?,
            hasExistingReceipt: l['has_receipt'] == true,
          )));
    } else {
      _lines.add(_Line(date: DateTime.now()));
    }
  }

  @override
  void dispose() {
    _project.dispose();
    for (final l in _lines) {
      l.dispose();
    }
    super.dispose();
  }

  double get _total => _lines.fold(0, (sum, l) => sum + (double.tryParse(l.amount.text) ?? 0));

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    for (final l in _lines) {
      final cat = _categories.where((c) => c['id'] == l.categoryId).firstOrNull;
      if (cat?['requires_bill'] == true && l.receipt == null && !l.hasExistingReceipt) {
        showSnack(context, 'A bill/receipt is required for ${cat!['name']}.', error: true);
        return;
      }
    }

    setState(() => _busy = true);
    try {
      final fields = {
        'claim_date': ymd(_claimDate!),
        'project_client': _project.text.trim(),
        'lines': [
          for (final l in _lines)
            {
              'category_id': l.categoryId,
              'expense_date': ymd(l.date!),
              'requested_amount': l.amount.text.trim(),
              'vendor': l.vendor.text.trim(),
              'bill_number': l.bill.text.trim(),
              'payment_mode': l.paymentMode,
              'description': l.description.text.trim(),
              if (l.existingId != null && l.receipt == null && l.hasExistingReceipt) 'existing_line_id': l.existingId,
            },
        ],
      };
      final files = [
        for (var i = 0; i < _lines.length; i++)
          if (_lines[i].receipt != null) UploadFile('lines[$i][receipt]', _lines[i].receipt!),
      ];

      final path = widget.existing == null ? '/expenses' : '/expenses/${widget.existing!['id']}/resubmit';
      final res = await ApiClient.instance.postMultipart(path, fields, files);
      if (!mounted) return;
      showSnack(context, res['message'] as String? ?? 'Submitted.');
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _lineCard(int i) {
    final l = _lines[i];
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(children: [
          Row(children: [
            Text('Item ${i + 1}', style: Theme.of(context).textTheme.titleSmall),
            const Spacer(),
            if (_lines.length > 1)
              IconButton(
                tooltip: 'Remove item',
                icon: const Icon(Icons.delete_outline),
                onPressed: () => setState(() => _lines.removeAt(i).dispose()),
              ),
          ]),
          DropdownButtonFormField<int>(
            initialValue: l.categoryId,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Category *', border: OutlineInputBorder()),
            items: _categories.map((c) => DropdownMenuItem(value: c['id'] as int, child: Text(c['name'] as String))).toList(),
            onChanged: (v) => setState(() => l.categoryId = v),
            validator: (v) => v == null ? 'Required' : null,
          ),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: DateField(label: 'Date *', value: l.date, last: DateTime.now(), onChanged: (d) => setState(() => l.date = d))),
            const SizedBox(width: 12),
            Expanded(
              child: TextFormField(
                controller: l.amount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Amount *', prefixText: '₹ ', border: OutlineInputBorder()),
                onChanged: (_) => setState(() {}),
                validator: (v) => (double.tryParse(v ?? '') ?? 0) <= 0 ? 'Enter amount' : null,
              ),
            ),
          ]),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: TextFormField(controller: l.vendor, decoration: const InputDecoration(labelText: 'Vendor', border: OutlineInputBorder()))),
            const SizedBox(width: 12),
            Expanded(child: TextFormField(controller: l.bill, decoration: const InputDecoration(labelText: 'Bill no.', border: OutlineInputBorder()))),
          ]),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: l.paymentMode,
            decoration: const InputDecoration(labelText: 'Payment mode', border: OutlineInputBorder()),
            items: _modes.map((m) => DropdownMenuItem(value: m['value'] as String, child: Text(m['label'] as String))).toList(),
            onChanged: (v) => setState(() => l.paymentMode = v),
          ),
          const SizedBox(height: 12),
          TextFormField(controller: l.description, maxLines: 2, decoration: const InputDecoration(labelText: 'Description', border: OutlineInputBorder())),
          const SizedBox(height: 12),
          AttachmentField(
            label: l.hasExistingReceipt && l.receipt == null ? 'Receipt (already attached — add to replace)' : 'Receipt',
            path: l.receipt,
            onChanged: (p) => setState(() => l.receipt = p),
          ),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.existing == null ? 'New Expense Claim' : 'Correct & Resubmit')),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          child: Row(children: [
            Expanded(child: Text('Total ${money(_total)}', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700))),
            FilledButton(onPressed: _busy ? null : _submit, child: _busy ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Submit')),
          ]),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          DateField(label: 'Claim date', value: _claimDate, last: DateTime.now(), onChanged: (d) => setState(() => _claimDate = d)),
          const SizedBox(height: 12),
          TextFormField(controller: _project, decoration: const InputDecoration(labelText: 'Project / client', border: OutlineInputBorder())),
          const SizedBox(height: 8),
          for (var i = 0; i < _lines.length; i++) _lineCard(i),
          OutlinedButton.icon(
            onPressed: () => setState(() => _lines.add(_Line(date: DateTime.now()))),
            icon: const Icon(Icons.add),
            label: const Text('Add another item'),
          ),
        ]),
      ),
    );
  }
}
