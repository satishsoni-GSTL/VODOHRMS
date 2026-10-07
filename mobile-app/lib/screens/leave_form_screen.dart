import 'dart:async';

import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

/// Apply for leave (optionally pre-filled from a rejected / sent-back application).
/// Shows the live balance and how many working days the range uses. Pops `true` on success.
class LeaveFormScreen extends StatefulWidget {
  const LeaveFormScreen({super.key, this.reapplyFrom});

  final Map<String, dynamic>? reapplyFrom;

  @override
  State<LeaveFormScreen> createState() => _LeaveFormScreenState();
}

class _LeaveFormScreenState extends State<LeaveFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _reason = TextEditingController();

  List<Map<String, dynamic>> _types = [];
  int? _typeId;
  DateTime? _from;
  DateTime? _to;
  bool _halfDay = false;
  String _session = 'first_half';
  String? _attachment;
  num? _days;
  String? _loadError;
  bool _busy = false;
  Timer? _debounce;

  Map<String, dynamic>? get _type => _types.where((t) => t['id'] == _typeId).firstOrNull;

  @override
  void initState() {
    super.initState();
    final r = widget.reapplyFrom;
    if (r != null) {
      _typeId = r['leave_type_id'] as int?;
      _from = parseDate(r['from_date'] as String?);
      _to = parseDate(r['to_date'] as String?);
      _halfDay = r['is_half_day'] == true;
      _session = (r['half_day_session'] as String?) ?? 'first_half';
      _reason.text = (r['reason'] as String?) ?? '';
    }
    _loadTypes();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _loadTypes() async {
    try {
      final data = await ApiClient.instance.get('/leave');
      setState(() {
        _types = (data['types'] as List).cast<Map<String, dynamic>>();
        _loadError = null;
      });
      _preview();
    } catch (e) {
      setState(() => _loadError = errorText(e));
    }
  }

  void _preview() {
    _debounce?.cancel();
    if (_from == null || (!_halfDay && _to == null)) {
      setState(() => _days = null);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 300), () async {
      try {
        final res = await ApiClient.instance.post('/leave/preview', {
          'from_date': ymd(_from!),
          'to_date': ymd(_halfDay ? _from! : _to!),
          'is_half_day': _halfDay,
        });
        if (mounted) setState(() => _days = res['days'] as num?);
      } catch (_) {
        if (mounted) setState(() => _days = null);
      }
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_type?['attachment_required'] == true && _attachment == null) {
      showSnack(context, 'An attachment is required for ${_type!['name']}.', error: true);
      return;
    }

    setState(() => _busy = true);
    try {
      final res = await ApiClient.instance.postMultipart(
        '/leave',
        {
          'leave_type_id': _typeId,
          'from_date': ymd(_from!),
          'to_date': ymd(_halfDay ? _from! : _to!),
          'is_half_day': _halfDay,
          if (_halfDay) 'half_day_session': _session,
          'reason': _reason.text.trim(),
        },
        [if (_attachment != null) UploadFile('attachment', _attachment!)],
      );
      if (!mounted) return;
      showSnack(context, res['message'] as String? ?? 'Leave applied.');
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final type = _type;
    final balance = type?['balance'] as Map<String, dynamic>?;
    final available = balance?['available'] as num?;
    final overBalance = type != null && type['is_paid'] == true && available != null && _days != null && _days! > available;

    return Scaffold(
      appBar: AppBar(title: Text(widget.reapplyFrom == null ? 'Apply Leave' : 'Reapply Leave')),
      body: _loadError != null
          ? ErrorState(message: _loadError!, onRetry: _loadTypes)
          : _types.isEmpty
              ? const Center(child: CircularProgressIndicator())
              : Form(
                  key: _formKey,
                  child: ListView(padding: const EdgeInsets.all(16), children: [
                    DropdownButtonFormField<int>(
                      initialValue: _typeId,
                      decoration: const InputDecoration(labelText: 'Leave type', border: OutlineInputBorder()),
                      items: _types
                          .map((t) => DropdownMenuItem(
                                value: t['id'] as int,
                                child: Text('${t['name']}  (${num2((t['balance'] as Map)['available'] as num)} left)'),
                              ))
                          .toList(),
                      onChanged: (v) => setState(() => _typeId = v),
                      validator: (v) => v == null ? 'Select a leave type' : null,
                    ),
                    const SizedBox(height: 12),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Half day'),
                      value: _halfDay,
                      onChanged: (v) {
                        setState(() => _halfDay = v);
                        _preview();
                      },
                    ),
                    if (_halfDay) ...[
                      SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(value: 'first_half', label: Text('First half')),
                          ButtonSegment(value: 'second_half', label: Text('Second half')),
                        ],
                        selected: {_session},
                        onSelectionChanged: (s) => setState(() => _session = s.first),
                      ),
                      const SizedBox(height: 12),
                    ],
                    DateField(
                      label: _halfDay ? 'Date' : 'From',
                      value: _from,
                      onChanged: (d) {
                        setState(() {
                          _from = d;
                          if (_to != null && _to!.isBefore(d)) _to = d;
                        });
                        _preview();
                      },
                    ),
                    if (!_halfDay) ...[
                      const SizedBox(height: 16),
                      DateField(
                        label: 'To',
                        value: _to,
                        first: _from,
                        onChanged: (d) {
                          setState(() => _to = d);
                          _preview();
                        },
                      ),
                    ],
                    if (_days != null) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: overBalance ? Theme.of(context).colorScheme.errorContainer : Theme.of(context).colorScheme.secondaryContainer,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          _days == 0
                              ? 'The selected dates are all weekly offs / holidays.'
                              : '${daysLabel(_days)} of leave (weekly offs and holidays are not counted).'
                                  '${overBalance ? '\nThis is more than your available balance (${num2(available)}).' : ''}',
                        ),
                      ),
                    ],
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _reason,
                      minLines: 2,
                      maxLines: 5,
                      decoration: const InputDecoration(labelText: 'Reason', border: OutlineInputBorder()),
                    ),
                    const SizedBox(height: 16),
                    AttachmentField(
                      path: _attachment,
                      required: type?['attachment_required'] == true,
                      onChanged: (p) => setState(() => _attachment = p),
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
