import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:opfin/brand/opfin_theme.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  validateApiConfiguration(apiUrl, release: kReleaseMode);
  runApp(const MainApp());
}

class MainApp extends StatelessWidget {
  const MainApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'OpFin',
      theme: OpFinTheme.light,
      home: const SplashScreen(),
    );
  }
}
