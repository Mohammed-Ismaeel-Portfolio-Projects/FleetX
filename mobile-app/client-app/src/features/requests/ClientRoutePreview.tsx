import { StyleSheet, View } from 'react-native';

import { ClientMapCanvas } from './ClientMapCanvas';
import { hasCoordinatePair, type GeoCoordinate } from './coordinates';

export function ClientRoutePreview({
  pickup,
  delivery,
  testID,
}: {
  pickup: GeoCoordinate | null;
  delivery: GeoCoordinate | null;
  testID: string;
}) {
  if (!pickup || !delivery) return null;
  if (!hasCoordinatePair(pickup.latitude, pickup.longitude)) return null;
  if (!hasCoordinatePair(delivery.latitude, delivery.longitude)) return null;

  const latitude = (pickup.latitude + delivery.latitude) / 2;
  const longitude = (pickup.longitude + delivery.longitude) / 2;

  return (
    <View style={styles.wrap} testID={testID}>
      <ClientMapCanvas
        latitude={latitude}
        longitude={longitude}
        latitudeDelta={Math.max(Math.abs(pickup.latitude - delivery.latitude) * 1.8, 0.08)}
        longitudeDelta={Math.max(Math.abs(pickup.longitude - delivery.longitude) * 1.8, 0.08)}
        interactive={false}
        markers={[
          { id: 'pickup', latitude: pickup.latitude, longitude: pickup.longitude, title: 'Pickup' },
          { id: 'delivery', latitude: delivery.latitude, longitude: delivery.longitude, title: 'Delivery' },
        ]}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    height: 180,
    borderRadius: 16,
    overflow: 'hidden',
    marginBottom: 12,
    position: 'relative',
    backgroundColor: '#E8EEF5',
  },
});
