import 'package:flutter/material.dart';
import '../core/storage/preferences_storage.dart';

class LocaleProvider extends ChangeNotifier {
  Locale _locale;

  LocaleProvider(String localeCode) : _locale = Locale(localeCode);

  Locale get currentLocale => _locale;

  Future<void> setLocale(String code) async {
    _locale = Locale(code);
    await PreferencesStorage.saveLocale(code);
    notifyListeners();
  }
}
