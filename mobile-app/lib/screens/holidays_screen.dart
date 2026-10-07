import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

/// Holiday calendar; optional holidays can be claimed (up to the yearly limit) or cancelled.
class HolidaysScreen extends StatefulWidget {
  const HolidaysScreen({super.key});

  @override
  State<HolidaysScreen> createState() => _HolidaysScreenState();
}

class _HolidaysScreenState extends State<HolidaysScreen> {
  final _key = GlobalKey<AsyncViewState<Map<String, dynamic>>>();
  int _year = DateTime.now().year;

  Future<void> _claim(Map<String, dynamic> h) async {
    final ok = await confirmDialog(context, 'Claim optional holiday?', '${h['name']} on ${dateLabel(h['date'] as String?)} will be a holiday for you.', confirm: 'Claim');
    if (!ok) return;
    await _run(() => ApiClient.instance.post('/holidays/${h['id']}/claim'));
  }

  Future<void> _cancel(Map<String, dynamic> h) async {
    final ok = await confirmDialog(context, 'Cancel claim?', '${h['name']} will be a normal working day for you again.', confirm: 'Cancel claim');
    if (!ok) return;
    await _run(() => ApiClient.instance.post('/optional-holiday-claims/${h['claim_id']}/cancel'));
  }

  Future<void> _run(Future<Map<String, dynamic>> Function() call) async {
    try {
      final res = await call();
      if (!mounted) return;
      showSnack(context, res['message'] as String? ?? 'Done.');
      _key.currentState?.reload();
    } catch (e) {
      if (mounted) showSnack(context, errorText(e), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Holidays $_year'),
        actions: [
          IconButton(icon: const Icon(Icons.chevron_left), onPressed: () => setState(() {
                _year--;
                _key.currentState?.reload();
              })),
          IconButton(icon: const Icon(Icons.chevron_right), onPressed: () => setState(() {
                _year++;
                _key.currentState?.reload();
              })),
        ],
      ),
      body: AsyncView<Map<String, dynamic>>(
        key: _key,
        load: () => ApiClient.instance.get('/holidays', query: {'year': _year}),
        builder: (context, data, _) {
          final holidays = (data['holidays'] as List).cast<Map<String, dynamic>>();
          final hasOptional = holidays.any((h) => h['is_optional'] == true);
          final today = ymd(DateTime.now());

          if (holidays.isEmpty) return const EmptyState(icon: Icons.celebration_outlined, message: 'No holidays published for this year.');

          return ListView(padding: const EdgeInsets.only(bottom: 24), children: [
            if (hasOptional)
              Card(
                color: Theme.of(context).colorScheme.secondaryContainer,
                child: ListTile(
                  leading: const Icon(Icons.info_outline),
                  title: Text('Optional holidays used: ${data['optional_used']} of ${data['optional_limit']}'),
                  subtitle: const Text('An optional holiday is a working day unless you claim it.'),
                ),
              ),
            ...holidays.map((h) {
              final date = parseDate(h['date'] as String?)!;
              final past = (h['date'] as String).compareTo(today) < 0;
              return ListTile(
                enabled: !past,
                leading: Container(
                  width: 48,
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHighest, borderRadius: BorderRadius.circular(8)),
                  child: Column(children: [
                    Text(DateFormat('dd').format(date), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                    Text(DateFormat('MMM').format(date), style: const TextStyle(fontSize: 11)),
                  ]),
                ),
                title: Text(h['name'] as String),
                subtitle: Text('${DateFormat('EEEE').format(date)} · ${h['type_label']}${h['claimed'] == true ? ' · Claimed' : ''}'),
                trailing: h['can_claim'] == true
                    ? FilledButton.tonal(onPressed: () => _claim(h), child: const Text('Claim'))
                    : h['can_cancel_claim'] == true
                        ? TextButton(onPressed: () => _cancel(h), child: const Text('Cancel'))
                        : null,
              );
            }),
          ]);
        },
      ),
    );
  }
}
