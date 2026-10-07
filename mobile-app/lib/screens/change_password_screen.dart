import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../services/auth_service.dart';
import '../widgets/common.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key, this.forced = false});

  /// Shown right after first sign-in with a temporary password.
  final bool forced;

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _new = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;
  Map<String, String> _fieldErrors = {};

  @override
  void dispose() {
    _current.dispose();
    _new.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _fieldErrors = {});
    if (!_formKey.currentState!.validate()) return;

    setState(() => _busy = true);
    try {
      final res = await ApiClient.instance.post('/password', {
        'current_password': _current.text,
        'password': _new.text,
        'password_confirmation': _confirm.text,
      });
      await AuthService.instance.refreshUser();
      if (!mounted) return;
      showSnack(context, res['message'] as String? ?? 'Password changed.');
      Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _fieldErrors = e.fieldErrors);
        if (e.fieldErrors.isEmpty) showSnack(context, e.message, error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  InputDecoration _decoration(String label, String field) =>
      InputDecoration(labelText: label, border: const OutlineInputBorder(), errorText: _fieldErrors[field], errorMaxLines: 3);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Change Password'), automaticallyImplyLeading: !widget.forced),
      body: Form(
        key: _formKey,
        child: ListView(padding: const EdgeInsets.all(16), children: [
          if (widget.forced)
            const Padding(
              padding: EdgeInsets.only(bottom: 16),
              child: Text('You signed in with a temporary password. Please set your own password to continue.'),
            ),
          TextFormField(
            controller: _current,
            obscureText: true,
            decoration: _decoration('Current password', 'current_password'),
            validator: (v) => v == null || v.isEmpty ? 'Required' : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _new,
            obscureText: true,
            decoration: _decoration('New password', 'password'),
            validator: (v) => v == null || v.length < 8 ? 'At least 8 characters' : null,
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _confirm,
            obscureText: true,
            decoration: _decoration('Confirm new password', 'password_confirmation'),
            validator: (v) => v != _new.text ? 'Passwords do not match' : null,
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _busy ? null : _submit,
            style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            child: _busy ? const CircularProgressIndicator() : const Text('Change password'),
          ),
        ]),
      ),
    );
  }
}
