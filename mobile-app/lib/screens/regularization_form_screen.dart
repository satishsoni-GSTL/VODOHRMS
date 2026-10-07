import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

/// Request an attendance correction. Pops `true` when submitted.
class RegularizationFormScreen extends StatefulWidget {
  const RegularizationFormScreen({super.key, this.date, this.firstIn, this.lastOut, this.requestType, this.reason});

  final DateTime? date;
  final String? firstIn;
  final String? lastOut;
  final String? requestType;
  final String? reason;

  @override
  State<RegularizationFormScreen> createState() => _RegularizationFormScreenState();
}

class _RegularizationFormScreenState extends State<RegularizationFormScreen> {
  static const _types = {
    'missing_punch': 'Missing Punch',
    'wrong_in': 'Wrong Check-In',
    'wrong_out': 'Wrong Check-Out',
    'wfh': 'Work From Home',
    'on_duty': 'On Duty',
    'client_visit': 'Client Visit',
    'other': 'Other',
  };

  final _formKey = GlobalKey<FormState>();
  late DateTime? _date = widget.date;
  late String _type = widget.requestType ?? 'missing_punch';
  TimeOfDay? _in;
  TimeOfDay? _out;
  late final _reason = TextEditingController(text: widget.reason);
  String? _attachment;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _in = _parseTime(widget.firstIn);
    _out = _parseTime(widget.lastOut);
  }

  TimeOfDay? _parseTime(String? t) {
    if (t == null || t.length < 5) return null;
    final p = t.split(':');
    return TimeOfDay(hour: int.parse(p[0]), minute: int.parse(p[1]));
  }

  String? _fmt(TimeOfDay? t) => t == null ? null : '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final res = await ApiClient.instance.postMultipart(
        '/regularizations',
        {
          'attendance_date': ymd(_date!),
          'request_type': _type,
          'first_in': _fmt(_in),
          'last_out': _fmt(_out),
          'reason': _reason.text.trim(),
        },
        [if (_attachment != null) UploadFile('attachment', _attachment!)],
      );
      if (!mounted) return;
      showSnack(context, res['message'] as String? ?? 'Submitted.');
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _timeField(String label, TimeOfDay? value, ValueChanged<TimeOfDay?> onChanged) => InkWell(
        onTap: () async {
          final t = await showTimePicker(context: context, initialTime: value ?? const TimeOfDay(hour: 9, minute: 30));
          if (t != null) onChanged(t);
        },
        child: InputDecorator(
          decoration: InputDecoration(labelText: label, border: const OutlineInputBorder(), suffixIcon: const Icon(Icons.schedule, size: 18)),
          child: Text(_fmt(value) ?? 'Select'),
        ),
      );

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Attendance Regularization')),
      body: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          DateField(label: 'Date', value: _date, last: DateTime.now(), onChanged: (d) => setState(() => _date = d)),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _type,
            decoration: const InputDecoration(labelText: 'Request type', border: OutlineInputBorder()),
            items: _types.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
            onChanged: (v) => setState(() => _type = v ?? _type),
          ),
          const SizedBox(height: 16),
          Row(children: [
            Expanded(child: _timeField('Correct check-in', _in, (t) => setState(() => _in = t))),
            const SizedBox(width: 12),
            Expanded(child: _timeField('Correct check-out', _out, (t) => setState(() => _out = t))),
          ]),
          const SizedBox(height: 16),
          TextFormField(
            controller: _reason,
            minLines: 2,
            maxLines: 5,
            decoration: const InputDecoration(labelText: 'Reason *', border: OutlineInputBorder()),
            validator: (v) => v == null || v.trim().isEmpty ? 'Please give a reason' : null,
          ),
          const SizedBox(height: 16),
          AttachmentField(path: _attachment, onChanged: (p) => setState(() => _attachment = p)),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _busy ? null : _submit,
            style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            child: _busy ? const CircularProgressIndicator() : const Text('Submit for approval'),
          ),
        ]),
      ),
    );
  }
}
