import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';

class TwoFAScreen extends StatefulWidget {
  const TwoFAScreen({super.key});

  @override
  State<TwoFAScreen> createState() => _TwoFAScreenState();
}

class _TwoFAScreenState extends State<TwoFAScreen> {
  final List<TextEditingController> _controllers =
      List.generate(6, (_) => TextEditingController());
  final List<FocusNode> _focusNodes = List.generate(6, (_) => FocusNode());
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  void _startTimer() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      final auth = context.read<AuthProvider>();
      auth.decrementResendCooldown();
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    for (final c in _controllers) {
      c.dispose();
    }
    for (final f in _focusNodes) {
      f.dispose();
    }
    super.dispose();
  }

  String get _otpCode => _controllers.map((c) => c.text).join();

  String _formatTime(int seconds) {
    final m = seconds ~/ 60;
    final s = seconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  String _getErrorMessage(String? code, String? error, BuildContext context) {
    if (error == null) return '';
    final t = AppLocalizations.of(context).translate;
    if (code != null &&
        (code.contains('INVALID_2FA') || code.contains('INVALID_OTP'))) {
      return t('twofa_invalid');
    }
    if (code != null && code.contains('OTP_EXPIRED')) {
      return t('twofa_expired');
    }
    if (error.contains('INVALID_2FA') || error.contains('INVALID_OTP')) {
      return t('twofa_invalid');
    }
    if (error.contains('OTP_EXPIRED')) {
      return t('twofa_expired');
    }
    return error;
  }

  Future<void> _verify() async {
    final auth = context.read<AuthProvider>();
    final code = _otpCode;
    if (code.length != 6) return;

    final ok = await auth.verify2fa(code);
    if (!mounted) return;

    if (ok) {
      Navigator.pushReplacementNamed(context, RouteNames.home);
    }
  }

  Future<void> _resend() async {
    final auth = context.read<AuthProvider>();
    await auth.resend2fa();
    for (final c in _controllers) {
      c.clear();
    }
    if (mounted) {
      setState(() {});
      _focusNodes[0].requestFocus();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final t = AppLocalizations.of(context).translate;

    return Scaffold(
      appBar: AppBar(
        title: Text(t('twofa_title')),
        actions: [
          if (auth.resendAfter > 0)
            Padding(
              padding: const EdgeInsets.only(right: 16),
              child: Center(
                child: Text(
                  _formatTime(auth.resendAfter),
                  style: const TextStyle(
                      fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ),
            ),
        ],
      ),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.security, size: 64),
              const SizedBox(height: 16),
              Text(
                t('twofa_subtitle'),
                style: const TextStyle(fontSize: 16),
                textAlign: TextAlign.center,
              ),
              if (auth.pending2faMobile != null) ...[
                const SizedBox(height: 4),
                Text(
                  auth.pending2faMobile!,
                  style: const TextStyle(
                      fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ],
              const SizedBox(height: 32),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(6, (i) {
                  return Container(
                    width: 48,
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    child: TextField(
                      controller: _controllers[i],
                      focusNode: _focusNodes[i],
                      keyboardType: TextInputType.number,
                      textAlign: TextAlign.center,
                      maxLength: 1,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      style: const TextStyle(
                          fontSize: 24, fontWeight: FontWeight.bold),
                      decoration: const InputDecoration(
                        counterText: '',
                        border: OutlineInputBorder(),
                      ),
                      onChanged: (value) {
                        if (value.isNotEmpty && i < 5) {
                          _focusNodes[i + 1].requestFocus();
                        } else if (value.isEmpty && i > 0) {
                          _focusNodes[i - 1].requestFocus();
                        }
                        if (_otpCode.length == 6) {
                          _verify();
                        }
                      },
                    ),
                  );
                }),
              ),
              const SizedBox(height: 24),
              if (auth.error != null) ...[
                Text(
                  _getErrorMessage(auth.errorCode, auth.error, context),
                  style: const TextStyle(color: Colors.red, fontSize: 13),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),
              ],
              ElevatedButton(
                onPressed: _otpCode.length == 6
                    ? (auth.isLoading ? null : _verify)
                    : null,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 48, vertical: 16),
                ),
                child: auth.isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(t('twofa_verify')),
              ),
              const SizedBox(height: 16),
              TextButton(
                onPressed: auth.resendAfter > 0 ? null : _resend,
                child: Text(
                  auth.resendAfter > 0
                      ? '${t('twofa_resend_in')} ${auth.resendAfter}s'
                      : t('twofa_resend'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
