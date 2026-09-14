import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';

class FarmerDetailsScreen extends StatefulWidget {
  const FarmerDetailsScreen({super.key});

  @override
  State<FarmerDetailsScreen> createState() => _FarmerDetailsScreenState();
}

class _FarmerDetailsScreenState extends State<FarmerDetailsScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _villageController = TextEditingController();
  final _districtController = TextEditingController();
  final _stateController = TextEditingController();
  final _landAreaController = TextEditingController();

  @override
  void dispose() {
    _nameController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _villageController.dispose();
    _districtController.dispose();
    _stateController.dispose();
    _landAreaController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final auth = context.read<AuthProvider>();
    final data = {
      'name': _nameController.text.trim(),
      'password': _passwordController.text,
      'password_confirmation': _confirmPasswordController.text,
      'village': _villageController.text.trim(),
      'district': _districtController.text.trim(),
      'state': _stateController.text.trim(),
      'land_area_acres': double.parse(_landAreaController.text.trim()),
    };

    final ok = await auth.completeRegistration(data);
    if (!mounted) return;

    if (ok) {
      final t = AppLocalizations.of(context).translate;
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => AlertDialog(
          icon: const Icon(Icons.check_circle, color: Colors.green, size: 48),
          content: Text(t('auth_registration_success'), textAlign: TextAlign.center),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(ctx).pop();
                Navigator.pushNamedAndRemoveUntil(
                    context, RouteNames.login, (route) => false);
              },
              child: Text(t('ok')),
            ),
          ],
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final t = AppLocalizations.of(context).translate;

    return Scaffold(
      appBar: AppBar(title: Text(t('farmer_details_title'))),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  t('farmer_details_subtitle'),
                  style: TextStyle(fontSize: 14, color: Colors.grey.shade600),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 24),
                TextFormField(
                  controller: _nameController,
                  decoration: InputDecoration(
                    labelText: t('farmer_name'),
                    prefixIcon: const Icon(Icons.person),
                    border: const OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? t('error_required') : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _passwordController,
                  obscureText: true,
                  decoration: InputDecoration(
                    labelText: t('farmer_password'),
                    prefixIcon: const Icon(Icons.lock),
                    border: const OutlineInputBorder(),
                  ),
                  validator: (v) {
                    if (v == null || v.isEmpty) return t('error_required');
                    if (v.length < 6) return t('error_password_too_short');
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _confirmPasswordController,
                  obscureText: true,
                  decoration: InputDecoration(
                    labelText: t('farmer_confirm_password'),
                    prefixIcon: const Icon(Icons.lock_outline),
                    border: const OutlineInputBorder(),
                  ),
                  validator: (v) {
                    if (v == null || v.isEmpty) return t('error_required');
                    if (v != _passwordController.text) {
                      return t('error_password_mismatch');
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _villageController,
                  decoration: InputDecoration(
                    labelText: t('farmer_village'),
                    prefixIcon: const Icon(Icons.location_city),
                    border: const OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? t('error_required') : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _districtController,
                  decoration: InputDecoration(
                    labelText: t('farmer_district'),
                    prefixIcon: const Icon(Icons.map),
                    border: const OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? t('error_required') : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _stateController,
                  decoration: InputDecoration(
                    labelText: t('farmer_state'),
                    prefixIcon: const Icon(Icons.public),
                    border: const OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? t('error_required') : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _landAreaController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(
                        RegExp(r'^\d*\.?\d{0,2}')),
                  ],
                  decoration: InputDecoration(
                    labelText: t('farmer_land_area'),
                    prefixIcon: const Icon(Icons.landscape),
                    border: const OutlineInputBorder(),
                  ),
                  validator: (v) {
                    if (v == null || v.trim().isEmpty) return t('error_required');
                    if (double.tryParse(v.trim()) == null) {
                      return t('error_invalid_number');
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 24),
                if (auth.error != null) ...[
                  Text(
                    auth.error!,
                    style: const TextStyle(color: Colors.red, fontSize: 13),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 12),
                ],
                ElevatedButton(
                  onPressed: auth.isLoading ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  child: auth.isLoading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(t('farmer_submit')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}