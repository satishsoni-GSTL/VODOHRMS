import 'package:flutter/material.dart';

import '../config.dart';
import '../services/auth_service.dart';
import '../theme.dart';
import '../widgets/common.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, this.message});

  /// Shown above the form, e.g. "You were signed out".
  final String? message;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _login = TextEditingController();
  final _password = TextEditingController();
  late final TextEditingController _server = TextEditingController(text: AuthService.instance.serverUrl);

  bool _busy = false;
  bool _obscure = true;
  bool _showServer = false;
  String? _error;

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    _server.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      // On success AuthService notifies and main.dart swaps to the app.
      await AuthService.instance.login(login: _login.text, password: _password.text, serverUrl: _server.text);
    } catch (e) {
      if (mounted) setState(() => _error = errorText(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
                      child: const BrandLogo(height: 44),
                    ),
                    const SizedBox(height: 10),
                    const ClipRRect(borderRadius: BorderRadius.all(Radius.circular(2)), child: BrandStrip()),
                    const SizedBox(height: 16),
                    Text('HRMS',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800, color: theme.colorScheme.primary, letterSpacing: 2)),
                    const SizedBox(height: 4),
                    Text('Sign in once — you will stay signed in on this phone.',
                        textAlign: TextAlign.center, style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
                    const SizedBox(height: 28),
                    if (widget.message != null) ...[
                      _Banner(text: widget.message!, color: theme.colorScheme.secondaryContainer),
                      const SizedBox(height: 16),
                    ],
                    if (_error != null) ...[
                      _Banner(text: _error!, color: theme.colorScheme.errorContainer),
                      const SizedBox(height: 16),
                    ],
                    TextFormField(
                      controller: _login,
                      decoration: const InputDecoration(
                        labelText: 'Employee Code or Email',
                        prefixIcon: Icon(Icons.person_outline),
                        border: OutlineInputBorder(),
                      ),
                      textInputAction: TextInputAction.next,
                      autocorrect: false,
                      enabled: !_busy,
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter your employee code or email' : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _password,
                      obscureText: _obscure,
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        border: const OutlineInputBorder(),
                        suffixIcon: IconButton(
                          tooltip: _obscure ? 'Show password' : 'Hide password',
                          icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                          onPressed: () => setState(() => _obscure = !_obscure),
                        ),
                      ),
                      textInputAction: TextInputAction.done,
                      enabled: !_busy,
                      onFieldSubmitted: (_) => _submit(),
                      validator: (v) => (v == null || v.isEmpty) ? 'Enter your password' : null,
                    ),
                    if (AppConfig.allowServerChange) ...[
                      const SizedBox(height: 8),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton.icon(
                          onPressed: _busy ? null : () => setState(() => _showServer = !_showServer),
                          icon: Icon(_showServer ? Icons.expand_less : Icons.dns_outlined, size: 18),
                          label: const Text('Server address'),
                        ),
                      ),
                      if (_showServer)
                        TextFormField(
                          controller: _server,
                          keyboardType: TextInputType.url,
                          autocorrect: false,
                          enabled: !_busy,
                          decoration: const InputDecoration(
                            labelText: 'HRMS server',
                            hintText: 'https://hrms.yourcompany.com',
                            border: OutlineInputBorder(),
                          ),
                        ),
                    ],
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                      child: _busy
                          ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                          : const Text('Sign in'),
                    ),
                    const SizedBox(height: 12),
                    Text('Forgot your password? Use "Forgot password" on the HRMS website or contact HR.',
                        textAlign: TextAlign.center, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(8)),
        child: Text(text),
      );
}
