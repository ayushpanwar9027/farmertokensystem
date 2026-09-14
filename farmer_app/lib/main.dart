import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'app.dart';
import 'providers/auth_provider.dart';
import 'providers/locale_provider.dart';
import 'providers/connectivity_provider.dart';
import 'core/storage/secure_storage.dart';
import 'core/storage/preferences_storage.dart';
import 'core/constants/route_names.dart';
import 'services/push_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SecureStorage.initialize();
  await PushService.initialize();

  PushService.onDeepLink = (event, entityId) {
    final navigator = appNavigatorKey.currentState;
    if (navigator == null) return;
    int? toInt(Object? value) =>
        value is num ? value.toInt() : int.tryParse('$value');
    switch (event) {
      case 'booking_closing':
        if (entityId != null) {
          navigator.pushNamed(
            RouteNames.bookingDetail,
            arguments: {'booking_id': toInt(entityId)},
          );
        }
        break;
      case 'token_closing':
        navigator.pushNamed(
          RouteNames.liveQueue,
          arguments: {
            'bookingId': toInt(entityId),
            'centreId': null,
            'date': null,
          },
        );
        break;
    }
  };

  final savedLocale = await PreferencesStorage.getLocaleCode();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => LocaleProvider(savedLocale)),
        ChangeNotifierProvider(create: (_) => ConnectivityProvider()),
      ],
      child: const FarmerApp(),
    ),
  );
}
