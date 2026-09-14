import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/connectivity_provider.dart';
import '../../l10n/app_localizations.dart';

class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<ConnectivityProvider>(
      builder: (context, connectivity, _) {
        if (connectivity.isOnline) return const SizedBox.shrink();
        return Positioned(
          top: 0,
          left: 0,
          right: 0,
          child: MaterialBanner(
            content: Text(
              AppLocalizations.of(context).translate('offline_message'),
              style: const TextStyle(color: Colors.white),
            ),
            backgroundColor: Colors.red.shade700,
            leading: const Icon(Icons.wifi_off, color: Colors.white),
            actions: [
              TextButton(
                onPressed: () {},
                child: const Text('OK', style: TextStyle(color: Colors.white)),
              ),
            ],
          ),
        );
      },
    );
  }
}
