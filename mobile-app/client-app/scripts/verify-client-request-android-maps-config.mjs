/**
 * SHIPMENT-REQUEST-07D — Android Maps development-build configuration checks.
 * Does not invent or require a live API key. Asserts wiring only.
 */
import { strict as assert } from 'node:assert';
import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const CLIENT = resolve(__dirname, '..');

function readClient(rel) {
  const p = resolve(CLIENT, rel);
  if (!existsSync(p)) return '';
  return readFileSync(p, 'utf8');
}

const pkg = readClient('package.json');
const appConfig = readClient('app.config.js');
const envExample = readClient('.env.example');
const clientEnv = readClient('.env');
const gitignore = readClient('.gitignore');
const diagnostic = readClient('src/app/map-android-diagnostic.tsx');
const nativeMap = readClient('src/features/requests/ClientMapCanvas.tsx');
const webMap = readClient('src/features/requests/ClientMapCanvas.web.tsx');
const picker = readClient('src/features/requests/ClientLocationPicker.tsx');

test('Expo SDK 56 + react-native-maps versions are pinned without silent major upgrade', () => {
  assert.match(pkg, /"expo":\s*"~56/);
  assert.match(pkg, /"react-native-maps":\s*"1\.27\.2"/);
});

test('android package and Maps injection are configured for development builds', () => {
  assert.match(appConfig, /package:\s*'com\.fleetx\.client'/);
  assert.match(appConfig, /EXPO_PUBLIC_GOOGLE_MAPS_API_KEY/);
  assert.match(appConfig, /androidGoogleMapsApiKey/);
  assert.match(appConfig, /googleMapsAndroidKeyConfigured/);
  assert.match(appConfig, /react-native-maps/);
  assert.match(appConfig, /config:\s*\{\s*googleMaps:/s);
  // Key must come from env — never a hardcoded AIza-looking literal in config.
  assert.doesNotMatch(appConfig, /AIza[0-9A-Za-z_-]{20,}/);
});

test('npm scripts expose expo run:android / prebuild', () => {
  assert.match(pkg, /"android:dev":\s*"expo run:android"/);
  assert.match(pkg, /"android:prebuild":\s*"expo prebuild --platform android"/);
});

test('env example documents Google Cloud + package + rebuild requirement', () => {
  assert.match(envExample, /EXPO_PUBLIC_GOOGLE_MAPS_API_KEY=/);
  assert.match(envExample, /Maps SDK for Android/);
  assert.match(envExample, /com\.fleetx\.client/);
  assert.match(envExample, /SHA-1/);
  assert.match(envExample, /android:dev|expo run:android/);
  assert.match(envExample, /EXPO GO LIMITATION|Expo Go/i);
  assert.doesNotMatch(envExample, /AIza[0-9A-Za-z_-]{20,}/);
});

test('.env is gitignored and must not commit a real Maps key pattern in examples', () => {
  assert.match(gitignore, /^\.env$/m);
  assert.doesNotMatch(clientEnv, /^\s*EXPO_PUBLIC_GOOGLE_MAPS_API_KEY\s*=\s*AIza/m);
});

test('diagnostic route remains minimal and auth-free', () => {
  assert.match(diagnostic, /map-android-diagnostic/);
  assert.match(diagnostic, /PROVIDER_GOOGLE/);
  assert.match(diagnostic, /MAP_READY/);
  assert.match(diagnostic, /googleMapsAndroidKeyConfigured/);
  assert.doesNotMatch(diagnostic, /pickupLatitude|PricingEngine/);
  assert.doesNotMatch(diagnostic, /from ['"]axios['"]/);
  assert.doesNotMatch(diagnostic, /\bfetch\s*\(/);
});

test('Android canvas keeps PROVIDER_GOOGLE; web Leaflet untouched', () => {
  assert.match(nativeMap, /PROVIDER_GOOGLE/);
  assert.match(nativeMap, /onMapReady/);
  assert.equal(nativeMap.includes('Map tiles failed to load'), false);
  assert.match(webMap, /tile\.openstreetmap\.org/);
  assert.equal(webMap.includes('PROVIDER_GOOGLE'), false);
});

test('picker uses config-oriented Android unavailable copy; search/GPS remain', () => {
  assert.match(picker, /Android Maps configuration is required/);
  assert.match(picker, /Use my current location|requestForegroundPermissionsAsync/);
  assert.match(picker, /searchGeocodeLocations|Search/);
  assert.equal(picker.includes('Map tiles failed to load'), false);
});

test('evaluated app.config exposes boolean flag without requiring a live key', async () => {
  const { createRequire } = await import('node:module');
  const require = createRequire(import.meta.url);
  const cfg = require(resolve(CLIENT, 'app.config.js'));
  assert.equal(cfg.expo.android.package, 'com.fleetx.client');
  assert.equal(typeof cfg.expo.extra.googleMapsAndroidKeyConfigured, 'boolean');
  assert.equal(cfg.expo.extra.androidPackage, 'com.fleetx.client');
  if (!cfg.expo.extra.googleMapsAndroidKeyConfigured) {
    assert.equal(cfg.expo.android.config?.googleMaps?.apiKey, undefined);
    assert.equal(
      cfg.expo.plugins.some((p) => Array.isArray(p) && p[0] === 'react-native-maps'),
      false
    );
  } else {
    assert.ok(cfg.expo.android.config?.googleMaps?.apiKey);
    assert.ok(
      cfg.expo.plugins.some((p) => Array.isArray(p) && p[0] === 'react-native-maps')
    );
  }
});
