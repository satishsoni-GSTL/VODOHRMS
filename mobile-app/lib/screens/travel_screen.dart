import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

/// Travel requests: list, request a trip (with optional advance), cancel a pending one.
class TravelScreen extends StatefulWidget {
  const TravelScreen({super.key});

  @override
  State<TravelScreen> createState() => _TravelScreenState();
}

class _TravelScreenState extends State<TravelScreen> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  List<Map<String, dynamic>> _modes = const [];

  Future<void> _request({Map<String, dynamic>? from}) async {
    final done = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _TravelForm(modes: _modes, from: from),
    );
    if (done == true) _key.currentState?.reload();
  }

  Future<void> _cancel(Map<String, dynamic> t) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Cancel travel request?'),
        content: Text('${t['request_number']} · ${t['from_location']} → ${t['to_location']}'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('No')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Cancel request')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      final res = await ApiClient.instance.post('/travel/${t['id']}/cancel');
      if (!mounted) return;
      showSnack(context, res['message'] as String? ?? 'Cancelled.');
      _key.currentState?.reload();
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Travel')),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => _request(), icon: const Icon(Icons.add), label: const Text('Request')),
      body: AsyncView<Map<String, dynamic>>(
        key: _key,
        load: () => ApiClient.instance.get('/travel'),
        builder: (context, data, _) {
          _modes = ((data['modes'] as List?) ?? const []).cast<Map<String, dynamic>>();
          final items = (data['items'] as List).cast<Map<String, dynamic>>();
          if (items.isEmpty) return const EmptyState(icon: Icons.flight_takeoff_outlined, message: 'No travel requests yet.');

          return ListView(
            padding: const EdgeInsets.only(top: 8, bottom: 96),
            children: items.map((t) {
              final remarks = t['remarks'] as String?;
              final advance = (t['advance_requested'] as num?) ?? 0;
              final paid = (t['advance_paid'] as num?) ?? 0;
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Expanded(child: Text('${t['from_location']} → ${t['to_location']}', style: const TextStyle(fontWeight: FontWeight.w600))),
                      StatusChip(status: t['status'] as String?, label: t['status_label'] as String? ?? ''),
                    ]),
                    const SizedBox(height: 4),
                    Text('${t['request_number']} · ${t['travel_mode_label']}'),
                    Text('${dateLabel(t['departure_date'] as String?)} – ${dateLabel(t['return_date'] as String?)}'),
                    if (advance > 0) Text('Advance ${money(advance)}${paid > 0 ? ' · paid ${money(paid)}' : ''}'),
                    if (remarks != null && remarks.isNotEmpty) RemarksBox(remarks),
                    Row(mainAxisAlignment: MainAxisAlignment.end, children: [
                      if (t['can_cancel'] == true)
                        TextButton.icon(onPressed: () => _cancel(t), icon: const Icon(Icons.close, size: 18), label: const Text('Cancel')),
                      if (t['status'] == 'rejected' || t['status'] == 'sent_back')
                        TextButton.icon(onPressed: () => _request(from: t), icon: const Icon(Icons.refresh, size: 18), label: const Text('Reapply')),
                    ]),
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

class _TravelForm extends StatefulWidget {
  const _TravelForm({required this.modes, this.from});

  final List<Map<String, dynamic>> modes;
  final Map<String, dynamic>? from;

  @override
  State<_TravelForm> createState() => _TravelFormState();
}

class _TravelFormState extends State<_TravelForm> {
  final _formKey = GlobalKey<FormState>();
  late final _fromLoc = TextEditingController(text: widget.from?['from_location'] as String?);
  late final _toLoc = TextEditingController(text: widget.from?['to_location'] as String?);
  late final _purpose = TextEditingController(text: widget.from?['purpose'] as String?);
  late final _estimate = TextEditingController(text: _amount(widget.from?['estimated_cost']));
  late final _advance = TextEditingController(text: _amount(widget.from?['advance_requested']));
  late String _mode = widget.from?['travel_mode'] as String? ?? 'train';
  DateTime? _depart;
  DateTime? _return;
  bool _busy = false;

  static String _amount(dynamic v) => v is num && v > 0 ? num2(v) : '';

  DateTime get _today {
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_depart == null) {
      showSnack(context, 'Pick the departure date.', error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final res = await ApiClient.instance.post('/travel', {
        'purpose': _purpose.text.trim(),
        'travel_mode': _mode,
        'from_location': _fromLoc.text.trim(),
        'to_location': _toLoc.text.trim(),
        'departure_date': ymd(_depart!),
        'return_date': ymd(_return ?? _depart!),
        'estimated_cost': _estimate.text.trim().isEmpty ? 0 : _estimate.text.trim(),
        'advance_requested': _advance.text.trim().isEmpty ? 0 : _advance.text.trim(),
      });
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
    final modes = widget.modes.isNotEmpty
        ? widget.modes
        : const [
            {'value': 'air', 'label': 'Air'},
            {'value': 'train', 'label': 'Train'},
            {'value': 'bus', 'label': 'Bus'},
            {'value': 'car', 'label': 'Car / Cab'},
            {'value': 'other', 'label': 'Other'},
          ];
    String? required(String? v) => v == null || v.trim().isEmpty ? 'Required' : null;

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text('Request travel', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 16),
            Row(children: [
              Expanded(child: TextFormField(controller: _fromLoc, decoration: const InputDecoration(labelText: 'From *', border: OutlineInputBorder()), validator: required)),
              const SizedBox(width: 8),
              Expanded(child: TextFormField(controller: _toLoc, decoration: const InputDecoration(labelText: 'To *', border: OutlineInputBorder()), validator: required)),
            ]),
            const SizedBox(height: 12),
            DateField(
              label: 'Departure',
              value: _depart,
              first: _today,
              onChanged: (d) => setState(() {
                _depart = d;
                if (_return == null || _return!.isBefore(d)) _return = d;
              }),
            ),
            const SizedBox(height: 12),
            DateField(label: 'Return', value: _return, first: _depart ?? _today, onChanged: (d) => setState(() => _return = d)),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _mode,
              decoration: const InputDecoration(labelText: 'Mode', border: OutlineInputBorder()),
              items: modes.map((m) => DropdownMenuItem(value: m['value'] as String, child: Text(m['label'] as String))).toList(),
              onChanged: (v) => setState(() => _mode = v ?? _mode),
            ),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: TextFormField(
                  controller: _estimate,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Estimated cost', prefixText: '₹ ', border: OutlineInputBorder()),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: TextFormField(
                  controller: _advance,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(labelText: 'Advance needed', prefixText: '₹ ', border: OutlineInputBorder()),
                ),
              ),
            ]),
            const SizedBox(height: 12),
            TextFormField(
              controller: _purpose,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Purpose *', border: OutlineInputBorder()),
              validator: required,
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _busy ? null : _submit,
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
              child: _busy ? const CircularProgressIndicator() : const Text('Submit for approval'),
            ),
          ]),
        ),
      ),
    );
  }
}
