import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:opfin/accessibility/accessibility_state.dart';
import 'package:opfin/brand/opfin_theme.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/splash_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  validateApiConfiguration(apiUrl, release: kReleaseMode);
  await OpFinAccessibility.load();
  runApp(const MainApp());
}

class MainApp extends StatelessWidget {
  const MainApp({super.key});
  @override
  Widget build(BuildContext context) => ValueListenableBuilder<AccessibilitySettings>(
    valueListenable: OpFinAccessibility.settings,
    builder:(context,settings,_)=>MaterialApp(
      debugShowCheckedModeBanner:false,title:'OpFin',theme:OpFinTheme.light,
      builder:(context,child){
        final media=MediaQuery.of(context);
        final deviceScale=media.textScaler.scale(1);
        final scale=settings.largeText&&deviceScale<1.25?1.25:deviceScale;
        return MediaQuery(
          data:media.copyWith(
            textScaler:TextScaler.linear(scale),
            disableAnimations:settings.reducedMotion||media.disableAnimations),
          child:child??const SizedBox.shrink());
      },
      home:const SplashScreen(),
    ));
}
