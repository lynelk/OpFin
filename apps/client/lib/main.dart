import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:opfin/accessibility/accessibility_state.dart';
import 'package:opfin/brand/opfin_theme.dart';
import 'package:opfin/constants.dart';
import 'package:opfin/splash_screen.dart';
import 'package:opfin/biometric_unlock_screen.dart';
import 'package:opfin/services/device_biometrics.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  validateApiConfiguration(apiUrl, release: kReleaseMode);
  await OpFinAccessibility.load();
  runApp(const MainApp());
}

class MainApp extends StatefulWidget {
  const MainApp({super.key});
  @override
  State<MainApp> createState() => _MainAppState();
}

class _MainAppState extends State<MainApp> with WidgetsBindingObserver {
  bool _backgrounded = false;
  bool _needsUnlock = false;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.hidden ||
        state == AppLifecycleState.paused) {
      _backgrounded = true;
      // Conceal account content immediately; verify on return before removal.
      if (!_needsUnlock && mounted) setState(() => _needsUnlock = true);
    } else if (state == AppLifecycleState.resumed && _backgrounded) {
      _backgrounded = false;
      _checkOnResume();
    }
  }

  Future<void> _checkOnResume() async {
    if (_checking) return;
    _checking = true;
    try {
      final enabled = await DeviceBiometrics().isEnabledForCurrentUser();
      if (!mounted) return;
      if (!enabled) {
        setState(() => _needsUnlock = false);
        return;
      }
      final accepted = await DeviceBiometrics().verify(
        reason: 'Unlock OpFin after leaving the app',
      );
      if (!mounted) return;
      setState(() => _needsUnlock = !accepted);
    } catch (_) {
      if (mounted) setState(() => _needsUnlock = true);
    } finally {
      _checking = false;
    }
  }

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<AccessibilitySettings>(
    valueListenable: OpFinAccessibility.settings,
    builder:(context,settings,_)=>MaterialApp(
      debugShowCheckedModeBanner:false,title:'OpFin',theme:settings.highContrast?OpFinTheme.highContrastLight:OpFinTheme.light,
      builder:(context,child){
        final media=MediaQuery.of(context);
        final deviceScale=media.textScaler.scale(1);
        final scale=settings.largeText&&deviceScale<1.25?1.25:deviceScale;
        return MediaQuery(
          data:media.copyWith(
            textScaler:TextScaler.linear(scale),
            disableAnimations:settings.reducedMotion||media.disableAnimations),
          child:Stack(children:[
            child??const SizedBox.shrink(),
            if (_needsUnlock) Positioned.fill(child:Material(
              color:Theme.of(context).scaffoldBackgroundColor,
              child:Center(child:Padding(padding:const EdgeInsets.all(24),child:Column(
                mainAxisSize:MainAxisSize.min,
                children:[
                  const Icon(Icons.lock_outline,size:52),
                  const SizedBox(height:12),
                  const Text('OpFin is locked',style:TextStyle(fontSize:22,fontWeight:FontWeight.w700)),
                  const SizedBox(height:12),
                  const Text('Your financial information is hidden while device verification is pending.',textAlign:TextAlign.center),
                  const SizedBox(height:20),
                  FilledButton(onPressed:_checking?null:_checkOnResume,child:const Text('Unlock with biometrics')),
                ],
              )))),
            )),
          ]));
      },
      home:const SplashScreen(),
    ));
}
