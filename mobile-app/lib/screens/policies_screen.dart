import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

class PoliciesScreen extends StatelessWidget {
  const PoliciesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('HR Policies')),
      body: AsyncView<Map<String, dynamic>>(
        load: () => ApiClient.instance.get('/policies'),
        builder: (context, data, _) {
          final items = (data['items'] as List).cast<Map<String, dynamic>>();
          if (items.isEmpty) return const EmptyState(icon: Icons.policy_outlined, message: 'No policy documents published.');

          return ListView(
            padding: const EdgeInsets.symmetric(vertical: 8),
            children: items
                .map((d) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.picture_as_pdf_outlined),
                        title: Text(d['title'] as String),
                        subtitle: Text([
                          if ((d['description'] as String?)?.isNotEmpty == true) d['description'] as String,
                          'Updated ${dateLabel((d['updated_at'] as String?)?.substring(0, 10))}',
                        ].join('\n')),
                        trailing: const Icon(Icons.download_outlined),
                        onTap: () => downloadAndOpen(context, '/policies/${d['id']}/download', (d['file_name'] as String?) ?? 'policy.pdf'),
                      ),
                    ))
                .toList(),
          );
        },
      ),
    );
  }
}
