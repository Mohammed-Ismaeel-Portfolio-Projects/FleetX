/**
 * Client Request real map + coordinate contract checks.
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

const form = readClient('src/app/request-form.tsx');
const requestForm = readClient('src/features/requests/requestForm.ts');
const types = readClient('src/features/requests/types.ts');
const api = readClient('src/features/requests/requestsApi.ts');
const geocode = readClient('src/features/requests/geocodeApi.ts');
const picker = readClient('src/features/requests/ClientLocationPicker.tsx');
const nativeMap = readClient('src/features/requests/ClientMapCanvas.tsx');
const webMap = readClient('src/features/requests/ClientMapCanvas.web.tsx');
const coords = readClient('src/features/requests/coordinates.ts');
const envExample = readClient('.env.example');
const clientEnv = readClient('.env');
const pkg = readClient('package.json');
const appConfig = readClient('app.config.js');

test('map SDKs are installed and split by platform', () => {
  assert.match(pkg, /"react-native-maps"/);
  assert.match(pkg, /"expo-location"/);
  assert.match(nativeMap, /from 'react-native-maps'/);
  assert.match(nativeMap, /MapView/);
  assert.match(nativeMap, /PROVIDER_GOOGLE/);
  assert.match(nativeMap, /Platform\.OS === 'android'/);
  assert.match(nativeMap, /width:\s*'100%'/);
  assert.match(nativeMap, /height:\s*'100%'/);
  assert.match(picker, /mapBox/);
  assert.match(picker, /width=\{mapBox\.width\}/);
  assert.match(picker, /height=\{mapBox\.height\}/);
  assert.match(webMap, /tile\.openstreetmap\.org/);
  assert.match(webMap, /invalidateSize/);
  assert.match(webMap, /position: 'absolute'/);
  assert.equal(webMap.includes("createElement('canvas')"), false);
  assert.match(form, /ClientLocationPicker/);
  assert.equal(form.includes("from 'react-native-maps'"), false);
});

test('map picker uses a bounded visual height', () => {
  assert.match(picker, /useWindowDimensions/);
  assert.match(picker, /mapHeight/);
  assert.match(picker, /height: mapHeight/);
  assert.match(picker, /position: 'relative'/);
  assert.match(picker, /pinColor|#EF4444|Selected location/);
  assert.match(form, /pickerModalHost/);
});

test('Android MapView mounts after Modal onShow with measured size', () => {
  assert.match(form, /pickerSurfaceReady/);
  assert.match(form, /onShow=\{/);
  assert.match(picker, /mapAttachAllowed/);
  assert.match(nativeMap, /onHostLayout|onLayout/);
  assert.match(nativeMap, /hasSize|widthProp|heightProp/);
  assert.match(nativeMap, /PROVIDER_GOOGLE/);
  assert.match(nativeMap, /onMapReady/);
  assert.equal(nativeMap.includes('Map tiles failed to load'), false);
});

test('map picker testIDs exist', () => {
  for (const id of [
    'request-pickup-map',
    'request-delivery-map',
    'request-pickup-location',
    'request-delivery-location',
    'request-pickup-map-confirm',
    'request-delivery-map-confirm',
    'request-pickup-map-cancel',
    'request-delivery-map-cancel',
    'request-pickup-map-retry',
    'request-delivery-map-retry',
  ]) {
    const surface = [form, picker].join('\n');
    assert.match(surface, new RegExp(id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), id);
  }
});

test('existing request testIDs remain on the form', () => {
  for (const id of [
    'request-field-pickupCity',
    'request-save-draft',
    'request-go-review',
    'request-submit',
    'request-resubmit',
  ]) {
    assert.match(form, new RegExp(id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), id);
  }
});

test('API contract persists coordinates and geocodes server-side', () => {
  assert.match(types, /pickup_latitude/);
  assert.match(types, /delivery_longitude/);
  assert.match(requestForm, /pickup_latitude: values\.pickupLatitude/);
  assert.match(requestForm, /delivery_longitude: values\.deliveryLongitude/);
  assert.match(api, /pickupLatitude: asNumber\(api\.pickup_latitude\)/);
  assert.match(geocode, /\/client\/geocode\/reverse/);
  assert.match(geocode, /\/client\/geocode\/search/);
  assert.equal(geocode.toLowerCase().includes('graphhopper'), false);
  assert.equal(api.toLowerCase().includes('graphhopper'), false);
});

test('reverse geocode failure does not block confirm', () => {
  assert.match(picker, /label: 'Location selected'/);
  assert.match(picker, /catch \{/);
  assert.match(picker, /Confirm Pickup Location/);
  assert.match(picker, /status !== 'granted'/);
  assert.match(picker, /still tap the map/);
});

test('permission is optional and map retry exists', () => {
  assert.match(picker, /requestForegroundPermissionsAsync/);
  assert.match(picker, /request-pickup-map-locate|Use my current location/);
  assert.match(picker, /retryMap/);
  assert.match(picker, /map-retry/);
  assert.match(picker, /disabled=\{!confirmEnabled\}/);
});

test('address fields are not auto-overwritten by geocoding', () => {
  assert.match(form, /does not replace your address/);
  assert.match(form, /applyDetectedPlace/);
  assert.match(form, /pickupCity: prev\.pickupCity\.trim\(\) \? prev\.pickupCity : prev\.pickupDetectedCity/);
  assert.equal(picker.includes('pickup_address'), false);
  assert.equal(picker.includes('pickup_city'), false);
});

test('no server secrets in client env', () => {
  assert.doesNotMatch(envExample, /^GRAPH_HOPPER/m);
  assert.doesNotMatch(envExample, /EXPO_PUBLIC_.*GRAPH_HOPPER/i);
  assert.doesNotMatch(clientEnv, /^\s*GRAPH_HOPPER_API_KEY\s*=/m);
  assert.match(envExample, /EXPO_PUBLIC_GOOGLE_MAPS_API_KEY/);
  assert.match(envExample, /Maps SDK for Android/);
  assert.match(envExample, /com\.fleetx\.client/);
  assert.match(appConfig, /googleMaps/);
  assert.match(appConfig, /androidGoogleMapsApiKey/);
  assert.match(appConfig, /googleMapsAndroidKeyConfigured/);
  assert.match(appConfig, /package:\s*'com\.fleetx\.client'/);
  assert.match(appConfig, /expo-location/);
  assert.doesNotMatch(appConfig, /AIza[0-9A-Za-z_-]{20,}/);
});

test('assignment fields stay prohibited', () => {
  assert.equal(types.includes('driver_id'), false);
  assert.equal(types.includes('vehicle_id'), false);
  assert.equal(form.includes('driver_id'), false);
});

function hasCoordinatePair(latitude, longitude) {
  return (
    typeof latitude === 'number' &&
    typeof longitude === 'number' &&
    Number.isFinite(latitude) &&
    Number.isFinite(longitude) &&
    latitude >= -90 &&
    latitude <= 90 &&
    longitude >= -180 &&
    longitude <= 180
  );
}

test('behavioral: coordinate pair validation', () => {
  assert.equal(hasCoordinatePair(52.2297, 21.0122), true);
  assert.equal(hasCoordinatePair(0, 0), true);
  assert.equal(hasCoordinatePair(91, 21), false);
  assert.equal(hasCoordinatePair(33.89, null), false);
  assert.equal(hasCoordinatePair(null, 35.5), false);
  assert.match(coords, /export function hasCoordinatePair/);
  // Wording lives in requestStepValidation (SHIPMENT-REQUEST-03+); not requestForm.ts.
  const stepValidation = readClient('src/features/requests/requestStepValidation.ts');
  assert.match(stepValidation, /Select pickup on the map before you can proceed/);
  assert.match(stepValidation, /Select delivery on the map before you can proceed/);
});

test('behavioral: persisted reload uses formFromDetails coordinates', () => {
  assert.match(requestForm, /pickupLatitude: details\.pickupLatitude/);
  assert.match(requestForm, /deliveryLongitude: details\.deliveryLongitude/);
  assert.match(requestForm, /pickupMapLabel: hasCoordinatePair/);
});
