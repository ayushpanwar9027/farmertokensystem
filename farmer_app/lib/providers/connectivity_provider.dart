import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';

class ConnectivityProvider extends ChangeNotifier {
  bool _isOnline = true;
  StreamSubscription? _subscription;

  bool get isOnline => _isOnline;

  ConnectivityProvider() {
    _checkInitial();
    _subscription = Connectivity()
        .onConnectivityChanged
        .listen((results) async {
      final hasConnection = results.any((r) => r != ConnectivityResult.none);
      if (_isOnline != hasConnection) {
        _isOnline = hasConnection;
        notifyListeners();
      }
    });
  }

  void _checkInitial() async {
    final results = await Connectivity().checkConnectivity();
    _isOnline = results.any((r) => r != ConnectivityResult.none);
    notifyListeners();
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }
}
