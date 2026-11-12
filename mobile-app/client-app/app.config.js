/**
 * Expo config (C5.10 release hardening + SHIPMENT-REQUEST-07D Android Maps).
 *
 * Cleartext HTTP / iOS ATS arbitrary loads are NOT always-on.
 * - Local/dev: omit EXPO_PUBLIC_ENV or set EXPO_PUBLIC_ALLOW_INSECURE_HTTP=true
 * - Production: EXPO_PUBLIC_ENV=production → secure transport defaults (HTTPS only)
 *
 * Android Google Maps (react-native-maps + PROVIDER_GOOGLE):
 * - Requires EXPO_PUBLIC_GOOGLE_MAPS_API_KEY at *native build* time.
 * - Injected via android.config.googleMaps.apiKey and the react-native-maps config plugin.
 * - Expo Go cannot embed this project's key — use `npm run android:dev` (expo run:android).
 * - Never hardcode the key in this file.
 */

const isProduction = process.env.EXPO_PUBLIC_ENV === 'production';

const allowInsecureHttp =
  process.env.EXPO_PUBLIC_ALLOW_INSECURE_HTTP === 'true'
    ? true
    : process.env.EXPO_PUBLIC_ALLOW_INSECURE_HTTP === 'false'
      ? false
      : !isProduction;

const googleMapsAndroidApiKey = (process.env.EXPO_PUBLIC_GOOGLE_MAPS_API_KEY || '').trim();
const googleMapsAndroidKeyConfigured = googleMapsAndroidApiKey.length > 0;

if (!googleMapsAndroidKeyConfigured) {
  console.warn(
    '[FleetX] EXPO_PUBLIC_GOOGLE_MAPS_API_KEY is unset. ' +
      'Android Google Maps imagery requires this key in a development/standalone build ' +
      '(npm run android:dev). Expo Go / Metro reload cannot inject the key.'
  );
}

/** @type {import('expo/config').ExpoConfig} */
const expoConfig = {
  name: 'FleetX Customer',
  slug: 'fleetx-client-app',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './assets/images/icon.png',
  scheme: 'fleetxclient',
  userInterfaceStyle: 'automatic',
  ios: {
    icon: './assets/expo.icon',
    infoPlist: {
      NSLocationWhenInUseUsageDescription:
        'FleetX uses your location only when you choose Use my current location while selecting a pickup or delivery point. You can still pick a location on the map without sharing your current location.',
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
    package: 'com.fleetx.client',
    adaptiveIcon: {
      backgroundColor: '#E6F4FE',
      foregroundImage: './assets/images/android-icon-foreground.png',
      backgroundImage: './assets/images/android-icon-background.png',
      monochromeImage: './assets/images/android-icon-monochrome.png',
    },
    predictiveBackGestureEnabled: false,
    usesCleartextTraffic: allowInsecureHttp,
    ...(googleMapsAndroidKeyConfigured
      ? {
          config: {
            googleMaps: {
              apiKey: googleMapsAndroidApiKey,
            },
          },
        }
      : {}),
  },
  web: {
    output: 'static',
    favicon: './assets/images/favicon.png',
  },
  plugins: [
    'expo-router',
    'expo-secure-store',
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
    [
      'expo-location',
      {
        locationWhenInUsePermission:
          'FleetX uses your location only when you choose Use my current location while selecting a pickup or delivery point. You can still pick a location on the map without sharing your current location.',
      },
    ],
    // Writes com.google.android.geo.API_KEY into AndroidManifest during prebuild/run:android.
    // No effect inside Expo Go (cannot rewrite Expo Go's binary).
    ...(googleMapsAndroidKeyConfigured
      ? [
          [
            'react-native-maps',
            {
              androidGoogleMapsApiKey: googleMapsAndroidApiKey,
            },
          ],
        ]
      : []),
  ],
  experiments: {
    typedRoutes: true,
    reactCompiler: true,
  },
  extra: {
    allowInsecureHttp,
    expoPublicEnv: process.env.EXPO_PUBLIC_ENV || 'development',
    /** Boolean only — never put the actual API key in Constants.extra. */
    googleMapsAndroidKeyConfigured,
    androidPackage: 'com.fleetx.client',
  },
};

module.exports = { expo: expoConfig };
