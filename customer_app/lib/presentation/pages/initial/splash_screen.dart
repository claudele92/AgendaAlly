import 'dart:io';

import 'package:demand/presentation/route/app_route_setting.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_native_splash/flutter_native_splash.dart';
import 'package:demand/infrastructure/local_storage/local_storage.dart';
import 'package:demand/presentation/app_assets.dart';
import 'package:demand/presentation/route/app_route.dart';

class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  @override
  void initState() {
    getDeviceInfo();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _navigateToNextScreen();
    });
    super.initState();
  }

  void _navigateToNextScreen() {
    FlutterNativeSplash.remove();
    if (LocalStorage.getUiType() == null) {
      AppRouteSetting.goSelectUIType(context, hasBackButton: true);
      return;
    }

    if (LocalStorage.getLocation() == null) {
      AppRouteSetting.goLocationAccess(context);

      return;
    }
    AppRoute.goMain(context);
  }

  Future<void> getDeviceInfo() async {
    DeviceInfoPlugin deviceInfo = DeviceInfoPlugin();
    if (Platform.isAndroid) {
      AndroidDeviceInfo androidInfo = await deviceInfo.androidInfo;
      debugPrint('==> ${androidInfo.model}');
    } else if (Platform.isIOS) {
      IosDeviceInfo iosInfo = await deviceInfo.iosInfo;
      debugPrint('==> ${iosInfo.modelName}');
      debugPrint('==> ${iosInfo.identifierForVendor}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Image.asset(Assets.imagesSplash, fit: BoxFit.cover);
  }
}
