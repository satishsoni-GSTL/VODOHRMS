import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../utils/format.dart';
import '../widgets/common.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Profile')),
      body: AsyncView<Map<String, dynamic>>(
        load: () => ApiClient.instance.get('/profile'),
        builder: (context, data, _) {
          final user = data['user'] as Map<String, dynamic>;
          final job = data['job'] as Map<String, dynamic>;
          final personal = data['personal'] as Map<String, dynamic>;
          final bank = data['bank'] as Map<String, dynamic>?;
          final weeklyOff = (job['weekly_off'] as List?)?.map((d) => '$d'[0].toUpperCase() + '$d'.substring(1)).join(', ');

          Widget card(List<Widget> rows) => Card(child: Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8), child: Column(children: rows)));

          return ListView(padding: const EdgeInsets.only(bottom: 24), children: [
            const SizedBox(height: 16),
            Center(
              child: CircleAvatar(
                radius: 36,
                child: Text(
                  (user['name'] as String).trim().split(RegExp(r'\s+')).map((p) => p.isEmpty ? '' : p[0]).take(2).join().toUpperCase(),
                  style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Center(child: Text(user['name'] as String, style: Theme.of(context).textTheme.titleLarge)),
            Center(child: Text([job['designation'], job['department']].whereType<String>().join(' · '))),
            const SectionTitle('Employment'),
            card([
              InfoRow('Employee code', job['employee_code'] as String?),
              InfoRow('Company', job['company'] as String?),
              InfoRow('Branch', job['branch'] as String?),
              InfoRow('Location', job['location'] as String?),
              InfoRow('Grade', job['grade'] as String?),
              InfoRow('Employment type', job['employment_type'] as String?),
              InfoRow('Date of joining', dateLabel(job['date_of_joining'] as String?)),
              InfoRow('Reporting manager', job['reporting_manager'] as String?),
              InfoRow('HR manager', job['hr_manager'] as String?),
              InfoRow('Official email', job['official_email'] as String?),
              InfoRow('Weekly off', weeklyOff),
            ]),
            const SectionTitle('Personal'),
            card([
              InfoRow('Date of birth', dateLabel(personal['date_of_birth'] as String?)),
              InfoRow('Gender', personal['gender'] as String?),
              InfoRow('Blood group', personal['blood_group'] as String?),
              InfoRow('Mobile', personal['mobile'] as String?),
              InfoRow('Personal email', personal['personal_email'] as String?),
              InfoRow('Address', personal['address'] as String?),
            ]),
            if (bank != null) ...[
              const SectionTitle('Salary account'),
              card([
                InfoRow('Bank', bank['bank_name'] as String?),
                InfoRow('Account', bank['account_number'] as String?),
                InfoRow('IFSC', bank['ifsc'] as String?),
              ]),
            ],
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('To correct any of these details, contact HR.', textAlign: TextAlign.center),
            ),
          ]);
        },
      ),
    );
  }
}
