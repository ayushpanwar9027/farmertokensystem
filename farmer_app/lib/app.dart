import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/theme/app_theme.dart';
import 'core/constants/route_names.dart';
import 'l10n/app_localizations.dart';
import 'providers/locale_provider.dart';
import 'router.dart';
import 'widgets/common/offline_banner.dart';

final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();

class FarmerApp extends StatelessWidget {
  const FarmerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<LocaleProvider>(
      builder: (context, localeProvider, child) {
        return MaterialApp(
          title: 'Kisan',
          debugShowCheckedModeBanner: false,
          theme: AppTheme.lightTheme,
          navigatorKey: appNavigatorKey,
          locale: localeProvider.currentLocale,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          supportedLocales: AppLocalizations.supportedLocales,
          initialRoute: RouteNames.splash,
          onGenerateRoute: AppRouter.generateRoute,
          builder: (context, child) {
            return Stack(
              children: [
                ?child,
                const OfflineBanner(),
              ],
            );
          },
        );
      },
    );
  }
}
