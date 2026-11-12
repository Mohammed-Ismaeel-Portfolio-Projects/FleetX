/**
 * Expo config (D1 release hardening).
 *
 * Cleartext HTTP / iOS ATS arbitrary loads are NOT always-on.
 * - Local/dev: omit EXPO_PUBLIC_ENV or set EXPO_PUBLIC_ALLOW_INSECURE_HTTP=true
 * - Production: EXPO_PUBLIC_ENV=production → secure transport defaults (HTTPS only)
 */

const isProduction = process.env.EXPO_PUBLIC_ENV === 'production';

/** Production builds never allow cleartext, regardless of override env vars. */
const allowInsecureHttp = isProduction
  ? false
  : process.env.EXPO_PUBLIC_ALLOW_INSECURE_HTTP === 'true'
    ? true
    : process.env.EXPO_PUBLIC_ALLOW_INSECURE_HTTP === 'false'
      ? false
      : true;

/** @type {import('expo/config').ExpoConfig} */
const expoConfig = {
  name: 'FleetX Driver',
  slug: 'fleetx-driver-app',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './assets/images/icon.png',
  scheme: 'fleetxdriver',
  userInterfaceStyle: 'automatic',
  ios: {
    icon: './assets/expo.icon',
    infoPlist: {
      NSLocationWhenInUseUsageDescription:
        'FleetX Driver uses your location to show your position on the active trip map and share progress with your dispatcher while a trip is In Operation.',
      ...(allowInsecureHttp
        ? {
            NSAppTransportSecurity: {
              NSAllowsArbitraryLoads: true,
            },
          }
        : {}),
    },
  },
  android: {
    adaptiveIcon: {
      backgroundColor: '#E6F4FE',
      foregroundImage: './assets/images/android-icon-foreground.png',
      backgroundImage: './assets/images/android-icon-background.png',
      monochromeImage: './assets/images/android-icon-monochrome.png',
    },
    predictiveBackGestureEnabled: false,
    usesCleartextTraffic: allowInsecureHttp,
    permissions: ['ACCESS_COARSE_LOCATION', 'ACCESS_FINE_LOCATION', 'POST_NOTIFICATIONS'],
  },
  web: {
    output: 'static',
    favicon: './assets/images/favicon.png',
  },
  plugins: [
    'expo-router',
    'expo-secure-store',
    [
      'expo-location',
      {
        locationWhenInUsePermission:
          'Allow FleetX Driver to use your location for active trip tracking.',
      },
    ],
    [
      'expo-camera',
      {
        cameraPermission:
          'FleetX Driver needs camera access to scan pickup and delivery verification QR codes.',
        recordAudioAndroid: false,
        barcodeScannerEnabled: true,
      },
    ],
    [
      'expo-image-picker',
      {
        cameraPermission:
          'FleetX Driver needs camera access to capture proof-of-delivery photos.',
        photosPermission:
          'FleetX Driver may use your photo library only if you choose an existing delivery photo.',
      },
    ],
    [
      'expo-notifications',
      {
        color: '#1B4F72',
        defaultChannel: 'driver-alerts',
      },
    ],
    [
      'expo-splash-screen',
      {
        backgroundColor: '#208AEF',
        android: {
          image: './assets/images/splash-icon.png',
          imageWidth: 76,
        },
      },
    ],
  ],
  experiments: {
    typedRoutes: true,
    reactCompiler: true,
  },
  extra: {
    allowInsecureHttp,
    expoPublicEnv: process.env.EXPO_PUBLIC_ENV || 'development',
    easProjectId: process.env.EXPO_PUBLIC_EAS_PROJECT_ID || '',
  },
};

module.exports = { expo: expoConfig };
