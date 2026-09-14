import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:farmer_app/l10n/app_localizations.dart';

Future<Map<String, dynamic>> loadArb(String languageCode) async {
  final jsonString =
      await rootBundle.loadString('lib/l10n/app_$languageCode.arb');
  return json.decode(jsonString) as Map<String, dynamic>;
}

class ArbDelegate extends LocalizationsDelegate<AppLocalizations> {
  const ArbDelegate();

  @override
  bool isSupported(Locale locale) => ['en', 'hi'].contains(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async {
    final map = await loadArb(locale.languageCode);
    final al = AppLocalizations(locale);
    al.loadFromMap(map);
    return al;
  }

  @override
  bool shouldReload(covariant LocalizationsDelegate<AppLocalizations> old) =>
      false;
}