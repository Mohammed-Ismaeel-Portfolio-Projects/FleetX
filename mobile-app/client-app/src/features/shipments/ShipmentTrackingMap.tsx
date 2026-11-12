import { useMemo } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import type { ClientShipmentTracking } from './types';
import {
  buildTrackingMapModel,
  formatPlannedDeliveryLabel,
  isUsableGeoPoint,
  locationFreshnessAccessibilityLabel,
  locationFreshnessLabel,
  locationFreshnessTone,
  trackingMapModeHint,
  trackingMapModeSummary,
  type TrackingMapMarkerKind,
} from './shipmentTrackingMap';
import { ClientMapCanvas } from '../requests/ClientMapCanvas';
import type { ClientMapMarker } from '../requests/mapCanvasTypes';

type ThemeColors = {
  text: string;
  textSecondary: string;
  border: string;
  backgroundElement: string;
  primary: string;
};

function markerColor(kind: TrackingMapMarkerKind, primary: string): string {
  switch (kind) {
    case 'origin':
      return '#0F766E';
    case 'destination':
      return '#1D4ED8';
    case 'lastKnown':
      return primary;
    default:
      return primary;
  }
}

function freshnessColors(
  tone: ReturnType<typeof locationFreshnessTone>,
  theme: ThemeColors
): { bg: string; text: string } {
  switch (tone) {
    case 'ok':
      return { bg: 'rgba(15, 118, 110, 0.16)', text: '#0F766E' };
    case 'caution':
      return { bg: 'rgba(217, 119, 6, 0.16)', text: '#B45309' };
    case 'warning':
      return { bg: 'rgba(185, 28, 28, 0.14)', text: '#B91C1C' };
    case 'neutral':
    default:
      return { bg: 'rgba(100, 116, 139, 0.14)', text: theme.textSecondary };
  }
}

/**
 * C5.5-C4 / C5.5-C4.1 / C5.5-C4.2 — Real Geographic Shipment Tracking Map.
 * Consumes authoritative backend route coordinates (tracking.route.coordinates) directly.
 * No client-side route calculation, interpolation, or downsampling.
 */
export function ShipmentTrackingMap({
  tracking,
  theme,
}: {
  tracking: ClientShipmentTracking;
  theme: ThemeColors;
}) {
  const model = useMemo(() => buildTrackingMapModel(tracking), [tracking]);
  const plannedDelivery = useMemo(
    () => formatPlannedDeliveryLabel(tracking.plannedDeliveryDate),
    [tracking.plannedDeliveryDate]
  );

  const rawRouteCoordinates = useMemo(
    () => tracking.route?.coordinates ?? [],
    [tracking.route?.coordinates]
  );

  const canvasMarkers: ClientMapMarker[] = useMemo(() => {
    const list: ClientMapMarker[] = [];
    const origin = tracking.origin?.coordinates;
    if (origin && isUsableGeoPoint(origin.latitude, origin.longitude)) {
      list.push({
        id: 'pickup',
        latitude: origin.latitude,
        longitude: origin.longitude,
        title: 'Pickup',
        pinColor: '#0F766E',
      });
    }
    const destination = tracking.destination?.coordinates;
    if (destination && isUsableGeoPoint(destination.latitude, destination.longitude)) {
      list.push({
        id: 'delivery',
        latitude: destination.latitude,
        longitude: destination.longitude,
        title: 'Delivery',
        pinColor: '#1D4ED8',
      });
    }
    const lastKnown = tracking.lastKnownPosition;
    if (lastKnown && isUsableGeoPoint(lastKnown.latitude, lastKnown.longitude)) {
      list.push({
        id: 'lastKnown',
        latitude: lastKnown.latitude,
        longitude: lastKnown.longitude,
        title: 'Last known position',
        pinColor: theme.primary,
      });
    }
    return list;
  }, [tracking.origin?.coordinates, tracking.destination?.coordinates, tracking.lastKnownPosition, theme.primary]);

  const initialRegion = useMemo(() => {
    const allPoints = [
      ...canvasMarkers.map((m) => ({ latitude: m.latitude, longitude: m.longitude })),
      ...rawRouteCoordinates.filter((c) => isUsableGeoPoint(c.latitude, c.longitude)),
    ];
    if (allPoints.length === 0) {
      return { latitude: 33.54681, longitude: 36.30194, latitudeDelta: 0.1, longitudeDelta: 0.1 };
    }
    let minLat = allPoints[0].latitude;
    let maxLat = allPoints[0].latitude;
    let minLng = allPoints[0].longitude;
    let maxLng = allPoints[0].longitude;
    for (const p of allPoints) {
      if (p.latitude < minLat) minLat = p.latitude;
      if (p.latitude > maxLat) maxLat = p.latitude;
      if (p.longitude < minLng) minLng = p.longitude;
      if (p.longitude > maxLng) maxLng = p.longitude;
    }
    const latSpan = Math.max(maxLat - minLat, 0.05);
    const lngSpan = Math.max(maxLng - minLng, 0.05);
    return {
      latitude: (minLat + maxLat) / 2,
      longitude: (minLng + maxLng) / 2,
      latitudeDelta: latSpan * 1.4,
      longitudeDelta: lngSpan * 1.4,
    };
  }, [canvasMarkers, rawRouteCoordinates]);

  const tone = locationFreshnessTone(model.locationFreshness.state);
  const freshnessPalette = freshnessColors(tone, theme);
  const freshnessText = locationFreshnessLabel(model.locationFreshness.state);
  const freshnessA11y = locationFreshnessAccessibilityLabel(model.locationFreshness.state);
  const modeSummary = trackingMapModeSummary(model.geoMode);
  const modeHint = trackingMapModeHint(model.geoMode);
  const hasPlannedRoute = rawRouteCoordinates.length >= 2;

  const header = (
    <>
      <Text
        style={[styles.title, { color: theme.text }]}
        accessibilityRole="header"
        testID="shipment-progress-map-title">
        Shipment journey
      </Text>
      <Text
        style={[styles.summary, { color: theme.text }]}
        testID="shipment-progress-map-summary"
        numberOfLines={2}>
        {modeSummary}
      </Text>
      <Text style={[styles.hint, { color: theme.textSecondary }]} numberOfLines={3}>
        {modeHint}
      </Text>
    </>
  );

  const freshnessChip = (
    <View
      style={[styles.freshnessChip, { backgroundColor: freshnessPalette.bg }]}
      testID="shipment-progress-freshness"
      accessibilityRole="text"
      accessibilityLabel={freshnessA11y}>
      <Text style={[styles.freshnessText, { color: freshnessPalette.text }]} numberOfLines={2}>
        {freshnessText}
      </Text>
    </View>
  );

  const plannedDeliveryRow =
    plannedDelivery != null ? (
      <View
        style={styles.plannedDeliveryRow}
        testID="shipment-progress-map-planned-delivery"
        accessible
        accessibilityRole="text"
        accessibilityLabel={`Planned delivery ${plannedDelivery}`}>
        <Text style={[styles.plannedDeliveryLabel, { color: theme.textSecondary }]}>
          Planned delivery
        </Text>
        <Text style={[styles.plannedDeliveryValue, { color: theme.text }]} numberOfLines={1}>
          {plannedDelivery}
        </Text>
      </View>
    ) : null;

  const legendItems: Array<{
    key: string;
    label: string;
    color: string;
    kind: 'dot' | 'line';
    a11y: string;
    testID?: string;
  }> = [];

  if (model.markers.some((m) => m.kind === 'origin')) {
    legendItems.push({
      key: 'origin',
      label: 'Pickup',
      color: markerColor('origin', theme.primary),
      kind: 'dot',
      a11y: 'Pickup location',
    });
  }
  if (hasPlannedRoute) {
    legendItems.push({
      key: 'route',
      label: 'Planned route',
      color: 'rgba(30, 117, 255, 0.65)',
      kind: 'line',
      a11y: 'Planned shipment route',
      testID: 'shipment-progress-map-route-legend',
    });
  }
  if (model.markers.some((m) => m.kind === 'lastKnown')) {
    legendItems.push({
      key: 'lastKnown',
      label: 'Last known',
      color: markerColor('lastKnown', theme.primary),
      kind: 'dot',
      a11y: 'Last known shipment location',
    });
  }
  if (model.markers.some((m) => m.kind === 'destination')) {
    legendItems.push({
      key: 'destination',
      label: 'Delivery',
      color: markerColor('destination', theme.primary),
      kind: 'dot',
      a11y: 'Delivery location',
    });
  }

  const legend = (
    <View style={styles.legend} testID="shipment-progress-map-legend">
      {legendItems.map((item) => (
        <View
          key={item.key}
          style={styles.legendChip}
          accessible
          accessibilityRole="text"
          accessibilityLabel={item.a11y}
          testID={item.testID}>
          {item.kind === 'line' ? (
            <View style={[styles.legendLine, { backgroundColor: item.color }]} />
          ) : (
            <View
              style={[
                styles.legendDot,
                {
                  backgroundColor: item.color,
                  width: item.key === 'lastKnown' ? 11 : 9,
                  height: item.key === 'lastKnown' ? 11 : 9,
                  borderRadius: item.key === 'lastKnown' ? 6 : 5,
                },
              ]}
            />
          )}
          <Text style={[styles.legendLabel, { color: theme.textSecondary }]} numberOfLines={1}>
            {item.label}
          </Text>
        </View>
      ))}
      {!hasPlannedRoute && model.geoMode !== 'none' ? (
        <Text
          style={[styles.routeMissing, { color: theme.textSecondary }]}
          testID="shipment-progress-map-route-missing"
          numberOfLines={2}>
          Planned route is not available.
        </Text>
      ) : null}
    </View>
  );

  if (!model.hasGeographicData) {
    return (
      <View
        style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
        testID="shipment-progress-map-unavailable"
        accessibilityRole="summary"
        accessibilityLabel={`Shipment journey. ${modeSummary}. ${freshnessA11y}.`}>
        {header}
        {freshnessChip}
        {plannedDeliveryRow}
        <View
          style={[styles.emptyCanvas, { borderColor: theme.border }]}
          testID="shipment-progress-map-empty">
          <Text style={[styles.emptyTitle, { color: theme.text }]}>No map details yet</Text>
          <Text style={[styles.emptyBody, { color: theme.textSecondary }]}>
            Location details are currently unavailable for this shipment.
          </Text>
        </View>
      </View>
    );
  }

  return (
    <View
      style={[styles.card, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}
      testID="shipment-progress-map"
      accessibilityRole="summary"
      accessibilityLabel={`Shipment journey. ${modeSummary}. ${freshnessA11y}.`}>
      {header}
      {freshnessChip}
      {plannedDeliveryRow}

      <View
        style={[styles.canvas, { borderColor: theme.border, backgroundColor: 'rgba(8, 11, 22, 0.035)' }]}
        testID="shipment-progress-map-canvas"
        accessibilityLabel={
          hasPlannedRoute ? 'Planned shipment route' : 'Shipment locations without a planned route'
        }>
        <ClientMapCanvas
          testID="shipment-progress-geo-map"
          latitude={initialRegion.latitude}
          longitude={initialRegion.longitude}
          latitudeDelta={initialRegion.latitudeDelta}
          longitudeDelta={initialRegion.longitudeDelta}
          markers={canvasMarkers}
          routeCoordinates={rawRouteCoordinates}
          interactive
        />
        {/* Hidden marker nodes for accessibility & UI test selection */}
        <View style={styles.hiddenTestWrap} pointerEvents="none">
          {model.markers.map((marker) => (
            <View
              key={marker.kind}
              testID={`shipment-progress-map-marker-${marker.kind}`}
              accessibilityLabel={marker.accessibilityLabel}
            />
          ))}
        </View>
      </View>

      {legend}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
    width: '100%',
  },
  title: { fontSize: 16, fontWeight: '700', marginBottom: 4 },
  summary: { fontSize: 14, fontWeight: '600', lineHeight: 20, marginBottom: 4 },
  hint: { fontSize: 12, lineHeight: 17, marginBottom: 12 },
  freshnessChip: {
    alignSelf: 'flex-start',
    maxWidth: '100%',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
    marginBottom: 10,
  },
  freshnessText: { fontSize: 12, fontWeight: '700' },
  plannedDeliveryRow: {
    marginBottom: 12,
    gap: 2,
  },
  plannedDeliveryLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.3,
    textTransform: 'uppercase',
  },
  plannedDeliveryValue: {
    fontSize: 15,
    fontWeight: '600',
    lineHeight: 20,
  },
  canvas: {
    minHeight: 200,
    height: 220,
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
    position: 'relative',
    width: '100%',
  },
  emptyCanvas: {
    minHeight: 132,
    borderRadius: 14,
    borderWidth: 1,
    borderStyle: 'dashed',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
    paddingVertical: 20,
    gap: 6,
  },
  emptyTitle: { fontSize: 14, fontWeight: '700', textAlign: 'center' },
  emptyBody: { fontSize: 12, lineHeight: 17, textAlign: 'center' },
  hiddenTestWrap: { opacity: 0, position: 'absolute', width: 0, height: 0 },
  canvasPlaceholder: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
  },
  canvasPlaceholderText: { fontSize: 12, fontWeight: '500', textAlign: 'center' },
  segment: {
    position: 'absolute',
    height: 2,
    borderRadius: 2,
  },
  markerWrap: {
    position: 'absolute',
    alignItems: 'center',
    justifyContent: 'center',
  },
  markerDot: {},
  legend: {
    marginTop: 12,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    alignItems: 'center',
  },
  legendChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    maxWidth: '100%',
    paddingRight: 4,
  },
  legendDot: { flexShrink: 0 },
  legendLine: { width: 14, height: 2, borderRadius: 2, flexShrink: 0 },
  legendLabel: { fontSize: 12, fontWeight: '500', flexShrink: 1 },
  routeMissing: { fontSize: 12, fontWeight: '500', width: '100%', marginTop: 2 },
});
