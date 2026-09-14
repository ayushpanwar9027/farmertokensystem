import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/storage/preferences_storage.dart';
import '../../../core/constants/route_names.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _navigate();
  }

  Future<void> _navigate() async {
    final auth = context.read<AuthProvider>();
    await auth.checkAuth();
    if (!mounted) return;

    if (auth.isAuthenticated) {
      _go(RouteNames.home);
      return;
    }

    final hasLanguage = await PreferencesStorage.hasSelectedLanguage();
    if (!mounted) return;

    if (!hasLanguage) {
      _go(RouteNames.languageSelection);
    } else {
      _go(RouteNames.login);
    }
  }

  void _go(String route) {
    Navigator.pushReplacementNamed(context, route);
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: CircularProgressIndicator(),
      ),
    );
  }
}
