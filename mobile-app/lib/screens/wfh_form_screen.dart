import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

/// Request Work From Home (must be at least one day in advance). Pops `true` on success.
class WfhFormScreen extends StatefulWidget {
  const WfhFormScreen({super.key, this.reapplyFrom});

  final Map<String, dynamic>? reapplyFrom;

  @override
  State<WfhFormScreen> createState() => _WfhFormScreenState();
}

class _WfhFormScreenState extends State<WfhFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final _reason = TextEditingController(text: widget.reapplyFrom?['reason'] as String?);
  DateTime? _from;
  DateTime? _to;
  bool _busy = false;

  DateTime get _tomorrow {
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day + 1);
  }

  @override
  void initState() {
    super.initState();
    // Reapplying for past dates isn't allowed, so only keep them if still in the future.
    final from = parseDate(widget.reapplyFrom?['from_date'] as String?);
    final to = parseDate(widget.reapplyFrom?['to_date'] as String?);
    if (from != null && !from.isBefore(_tomorrow)) {
      _from = from;
      _to = to;
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final res = await ApiClient.instance.post('/wfh', {
        'from_date': ymd(_from!),
        'to_date': ymd(_to ?? _from!),
        'reason': _reason.text.trim(),
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
    return Scaffold(
      appBar: AppBar(title: const Text('Work From Home')),
      body: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          Text('Work From Home must be requested at least one day in advance. On an approved day you can clock in and out from the Home screen.',
              style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 16),
          DateField(
            label: 'From',
            value: _from,
            first: _tomorrow,
            onChanged: (d) => setState(() {
              _from = d;
              if (_to == null || _to!.isBefore(d)) _to = d;
            }),
          ),
          const SizedBox(height: 16),
          DateField(label: 'To', value: _to, first: _from ?? _tomorrow, onChanged: (d) => setState(() => _to = d)),
          const SizedBox(height: 16),
          TextFormField(
            controller: _reason,
            minLines: 2,
            maxLines: 5,
            decoration: const InputDecoration(labelText: 'Reason *', border: OutlineInputBorder()),
            validator: (v) => v == null || v.trim().isEmpty ? 'Please give a reason' : null,
          ),
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
