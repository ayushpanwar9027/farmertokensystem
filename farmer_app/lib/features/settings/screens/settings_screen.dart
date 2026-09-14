import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/route_names.dart';
import '../../../l10n/app_localizations.dart';
import '../../../providers/auth_provider.dart';
import '../../../providers/locale_provider.dart';
import '../../../core/theme/colors.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final authProvider = context.watch<AuthProvider>();
    final localeProvider = context.watch<LocaleProvider>();

    return Scaffold(
      appBar: AppBar(title: Text(l10n.translate('settings_title'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _sectionHeader(l10n.translate('settings_language'), theme),
          const SizedBox(height: 8),
          _languageSection(context, localeProvider, l10n, theme),
          const Divider(height: 32),
          _sectionHeader(l10n.translate('settings_profile'), theme),
          const SizedBox(height: 8),
          _profileSection(authProvider, l10n, theme),
          const Divider(height: 32),
          _sectionHeader(l10n.translate('settings_2fa'), theme),
          const SizedBox(height: 8),
          _twoFactorSection(context, authProvider, l10n, theme),
          const Divider(height: 32),
          const SizedBox(height: 8),
          _logoutSection(context, authProvider, l10n, theme),
        ],
      ),
    );
  }

  Widget _sectionHeader(String title, ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.only(left: 8),
      child: Text(
        title,
        style: theme.textTheme.titleSmall?.copyWith(
          color: AppColors.primary,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Widget _languageSection(BuildContext context, LocaleProvider localeProvider, AppLocalizations l10n, ThemeData theme) {
    final currentCode = localeProvider.currentLocale.languageCode;

    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'en', label: Text('English')),
            ButtonSegment(value: 'hi', label: Text('हिंदी')),
          ],
          selected: {currentCode},
          onSelectionChanged: (Set<String> selected) {
            if (selected.isNotEmpty) {
              localeProvider.setLocale(selected.first);
            }
          },
        ),
      ),
    );
  }

  Widget _profileSection(AuthProvider authProvider, AppLocalizations l10n, ThemeData theme) {
    final user = authProvider.user;

    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ListTile(
        leading: const Icon(Icons.person, color: AppColors.primary),
        title: Text(
          user?.name ?? l10n.translate('common_no_data'),
          style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600),
        ),
        subtitle: Text(
          user?.mobile ?? '',
          style: theme.textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
        ),
      ),
    );
  }

  Widget _twoFactorSection(BuildContext context, AuthProvider authProvider, AppLocalizations l10n, ThemeData theme) {
    final is2FAEnabled = authProvider.user?.twoFactorEnabled == true;

    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: SwitchListTile(
        secondary: const Icon(Icons.security, color: AppColors.primary),
        title: Text(
          is2FAEnabled ? l10n.translate('enable_2fa') : l10n.translate('disable_2fa'),
          style: theme.textTheme.bodyLarge,
        ),
        subtitle: Text(
          is2FAEnabled ? l10n.translate('2fa_enabled') : l10n.translate('2fa_disabled'),
          style: theme.textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
        ),
        value: is2FAEnabled,
        onChanged: (value) {
          if (value) {
            _enable2FA(context, authProvider, l10n);
          } else {
            _showOTPDialog(context, authProvider, l10n, isEnabling: false);
          }
        },
      ),
    );
  }

  Future<void> _enable2FA(BuildContext context, AuthProvider authProvider, AppLocalizations l10n) async {
    final success = await authProvider.enable2fa();
    if (!context.mounted) return;
    if (success) {
      _showOTPDialog(context, authProvider, l10n, isEnabling: true);
    } else if (authProvider.error != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(authProvider.error!), backgroundColor: AppColors.error),
      );
    }
  }

  void _showOTPDialog(BuildContext context, AuthProvider authProvider, AppLocalizations l10n, {required bool isEnabling}) {
    final controller = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(isEnabling ? l10n.translate('enable_2fa') : l10n.translate('disable_2fa')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(isEnabling ? l10n.translate('2fa_confirm_enable') : l10n.translate('2fa_confirm_disable')),
            const SizedBox(height: 16),
            TextField(
              controller: controller,
              keyboardType: TextInputType.number,
              maxLength: 6,
              decoration: InputDecoration(
                labelText: l10n.translate('auth_otp'),
                border: const OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(l10n.translate('common_cancel')),
          ),
          ElevatedButton(
            onPressed: () async {
              final otp = controller.text.trim();
              if (otp.length != 6) return;
              bool success;
              if (isEnabling) {
                success = await authProvider.enable2faConfirm(otp);
              } else {
                success = await authProvider.disable2fa(otp);
              }
              if (!ctx.mounted) return;
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(
                    success
                        ? (isEnabling ? l10n.translate('2fa_enabled') : l10n.translate('2fa_disabled'))
                        : (authProvider.error ?? l10n.translate('common_error')),
                  ),
                  backgroundColor: success ? AppColors.success : AppColors.error,
                ),
              );
            },
            child: Text(l10n.translate('common_confirm')),
          ),
        ],
      ),
    );
  }

  Widget _logoutSection(BuildContext context, AuthProvider authProvider, AppLocalizations l10n, ThemeData theme) {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      color: AppColors.error.withValues(alpha: 0.05),
      child: ListTile(
        leading: const Icon(Icons.logout, color: AppColors.error),
        title: Text(
          l10n.translate('settings_logout'),
          style: theme.textTheme.bodyLarge?.copyWith(color: AppColors.error),
        ),
        onTap: () => _showLogoutDialog(context, authProvider, l10n),
      ),
    );
  }

  void _showLogoutDialog(BuildContext context, AuthProvider authProvider, AppLocalizations l10n) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.translate('settings_logout')),
        content: Text(l10n.translate('booking_cancel_confirm')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text(l10n.translate('common_cancel')),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error),
            onPressed: () async {
              await authProvider.logout();
              if (!ctx.mounted) return;
              Navigator.of(ctx).pop();
              Navigator.pushNamedAndRemoveUntil(
                context,
                RouteNames.login,
                (route) => false,
              );
            },
            child: Text(l10n.translate('settings_logout')),
          ),
        ],
      ),
    );
  }
}
