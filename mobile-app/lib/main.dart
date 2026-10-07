import 'package:flutter/material.dart';

import 'config.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'services/auth_service.dart';
import 'services/push_service.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AuthService.instance.load();
  await PushService.instance.init(); // no-op unless Firebase keys were provided at build time
  runApp(const VodoHrmsApp());
}

class VodoHrmsApp extends StatelessWidget {
  const VodoHrmsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: buildTheme(Brightness.light),
      darkTheme: buildTheme(Brightness.dark),
      // Signed in once on this phone → straight into the app. If the token is revoked or
      // signed out anywhere, AuthService notifies and we fall back to the login screen.
      home: ListenableBuilder(
        listenable: AuthService.instance,
        builder: (context, _) => AuthService.instance.isSignedIn
            ? const HomeShell()
            : LoginScreen(message: AuthService.instance.takeSignedOutMessage()),
      ),
    );
  }
}
