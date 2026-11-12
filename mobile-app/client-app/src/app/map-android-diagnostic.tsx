/**
 * DEV-ONLY: minimal Android MapView isolation (SHIPMENT-REQUEST-07C/07D).
 * Open via: /map-android-diagnostic
 *
 * CASE A — MapView never ready / no imagery → native Google Maps / build / API key
 * CASE B — diagnostic works, picker fails → ClientLocationPicker / layout hierarchy
 *
 * Does not use auth, shipment form, pricing, or backend APIs.
 */
import { useState } from 'react';
import { Platform, StyleSheet, Text, View } from 'react-native';
import MapView, { PROVIDER_GOOGLE } from 'react-native-maps';
import { Redirect } from 'expo-router';
import Constants from 'expo-constants';

export default function MapAndroidDiagnosticScreen() {
  const [mounted] = useState(true);
  const [ready, setReady] = useState(false);
  const [loaded, setLoaded] = useState(false);
  const [press, setPress] = useState<string>('none');
  const execution = Constants.executionEnvironment ?? 'unknown';
  const extra = (Constants.expoConfig?.extra ?? {}) as {
    googleMapsAndroidKeyConfigured?: boolean;
    androidPackage?: string;
  };
  const keyConfigured = extra.googleMapsAndroidKeyConfigured === true ? 'YES' : 'NO';
  const androidPackage = extra.androidPackage ?? 'unknown';
  const providerLabel = Platform.OS === 'android' ? 'PROVIDER_GOOGLE' : 'n/a';

  if (!__DEV__) {
    return <Redirect href="/" />;
  }

  return (
    <View style={styles.root} testID="map-android-diagnostic">
      <Text style={styles.meta} testID="map-android-diagnostic-meta">
        OS={Platform.OS} env={execution} package={androidPackage} provider={providerLabel}{' '}
        keyConfigured={keyConfigured} mounted={mounted ? 'YES' : 'NO'} ready=
        {ready ? 'YES' : 'NO'} loaded={loaded ? 'YES' : 'NO'} press={press}
      </Text>
      <MapView
        style={styles.map}
        provider={PROVIDER_GOOGLE}
        initialRegion={{
          latitude: 33.54681,
          longitude: 36.30194,
          latitudeDelta: 0.05,
          longitudeDelta: 0.05,
        }}
        onMapReady={() => {
          console.log('[map-android-diagnostic] MAP_READY');
          setReady(true);
        }}
        onMapLoaded={() => {
          console.log('[map-android-diagnostic] MAP_LOADED');
          setLoaded(true);
        }}
        onPress={(event) => {
          const coordinate = event.nativeEvent.coordinate;
          console.log('[map-android-diagnostic] MAP_PRESS', coordinate);
          if (coordinate) {
            setPress(`${coordinate.latitude.toFixed(5)},${coordinate.longitude.toFixed(5)}`);
          }
        }}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#111' },
  meta: {
    paddingHorizontal: 12,
    paddingTop: 48,
    paddingBottom: 8,
    color: '#fff',
    fontSize: 11,
  },
  map: { flex: 1 },
});
