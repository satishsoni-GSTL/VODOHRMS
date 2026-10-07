import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

class PayslipsScreen extends StatelessWidget {
  const PayslipsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Salary Slips')),
      body: AsyncView<Map<String, dynamic>>(
        load: () => ApiClient.instance.get('/payslips'),
        builder: (context, data, _) {
          final items = (data['items'] as List).cast<Map<String, dynamic>>();
          if (items.isEmpty) return const EmptyState(icon: Icons.description_outlined, message: 'No salary slips yet. They appear once payroll is finalized.');

          return ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: items.length,
            itemBuilder: (context, i) {
              final p = items[i];
              final lop = (p['lop_days'] as num?) ?? 0;
              return Card(
                child: ListTile(
                  leading: const CircleAvatar(child: Icon(Icons.payments_outlined)),
                  title: Text(monthLabel(p['payroll_month'] as String?)),
                  subtitle: Text('Gross ${money(p['gross'] as num?)}${lop > 0 ? ' · LOP ${num2(lop)} day(s)' : ''}'),
                  trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text(money(p['net_pay'] as num?), style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                    Text('Net pay', style: Theme.of(context).textTheme.bodySmall),
                  ]),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PayslipDetailScreen(id: p['id'] as int, month: p['payroll_month'] as String))),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class PayslipDetailScreen extends StatelessWidget {
  const PayslipDetailScreen({super.key, required this.id, required this.month});

  final int id;
  final String month;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(monthLabel(month))),
      body: AsyncView<Map<String, dynamic>>(
        load: () => ApiClient.instance.get('/payslips/$id'),
        builder: (context, p, _) {
          final earnings = (p['earnings'] as List).cast<Map<String, dynamic>>();
          final deductions = (p['deductions'] as List).cast<Map<String, dynamic>>();
          final theme = Theme.of(context);

          Widget lines(List<Map<String, dynamic>> rows) => Column(
                children: rows
                    .map((l) => ListTile(
                          dense: true,
                          title: Text(l['label'] as String),
                          trailing: Text(money(l['amount'] as num?)),
                        ))
                    .toList(),
              );

          return ListView(padding: const EdgeInsets.only(bottom: 24), children: [
            Card(
              color: theme.colorScheme.primaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(children: [
                  Text('Net pay (in hand)', style: theme.textTheme.bodySmall),
                  Text(money(p['net_pay'] as num?), style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  Text('Paid days ${num2(p['paid_days'] as num?)} · LOP ${num2(p['lop_days'] as num?)} day(s)'),
                ]),
              ),
            ),
            const SectionTitle('Earnings'),
            Card(child: Column(children: [
              lines(earnings),
              const Divider(height: 1),
              ListTile(title: const Text('Gross salary', style: TextStyle(fontWeight: FontWeight.w700)), trailing: Text(money(p['gross'] as num?), style: const TextStyle(fontWeight: FontWeight.w700))),
            ])),
            const SectionTitle('Deductions'),
            Card(child: Column(children: [
              if (deductions.isEmpty) const ListTile(dense: true, title: Text('No deductions')) else lines(deductions),
              const Divider(height: 1),
              ListTile(
                title: const Text('Total deductions', style: TextStyle(fontWeight: FontWeight.w700)),
                trailing: Text(money(p['total_deductions'] as num?), style: const TextStyle(fontWeight: FontWeight.w700)),
              ),
            ])),
            if (((p['lop_amount'] as num?) ?? 0) > 0)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Text('Loss of pay this month: ${money(p['lop_amount'] as num?)} (already reflected in earnings).', style: theme.textTheme.bodySmall),
              ),
            if (p['has_pdf'] == true)
              Padding(
                padding: const EdgeInsets.all(16),
                child: FilledButton.icon(
                  onPressed: () => downloadAndOpen(context, '/payslips/$id/pdf', 'payslip-$month.pdf'),
                  icon: const Icon(Icons.picture_as_pdf_outlined),
                  label: const Text('Download PDF'),
                  style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                ),
              ),
          ]);
        },
      ),
    );
  }
}
