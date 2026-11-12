import { createElement, memo, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
  ActivityIndicator,
  LayoutChangeEvent,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { WebView } from 'react-native-webview';

import { DriverTheme } from '@/components/driver/theme';
import { phaseStatusLabel } from '@/features/driverApi/executionPhase';
import type { DeviceFix, DriverGeoPoint, DriverTripMap } from '@/features/driverApi/tripTypes';
import type { MapFocusMode } from '@/features/tracking/mapFocusMode';
import {
  buildTripTimelineMilestones,
  markGlyph,
} from '@/features/tracking/mapTimeline';

type Theme = { text: string; textSecondary: string; primary: string };

type MarkerKind = 'driver' | 'pickup' | 'delivery';

type Marker = {
  kind: MarkerKind;
  latitude: number;
  longitude: number;
  label: string;
};

const PICKUP_COLOR = '#0F766E';
const DELIVERY_COLOR = '#1D4ED8';
const ROUTE_COLOR = '#94A3B8';
const ROUTE_PROGRESS_COLOR = '#1B4F72';

function usable(lat: number, lng: number) {
  return (
    Number.isFinite(lat) &&
    Number.isFinite(lng) &&
    lat >= -90 &&
    lat <= 90 &&
    lng >= -180 &&
    lng <= 180 &&
    !(Math.abs(lat) < 0.00001 && Math.abs(lng) < 0.00001)
  );
}

function haversineMeters(a: DriverGeoPoint, b: DriverGeoPoint): number {
  const R = 6371000;
  const toRad = (d: number) => (d * Math.PI) / 180;
  const dLat = toRad(b.latitude - a.latitude);
  const dLon = toRad(b.longitude - a.longitude);
  const lat1 = toRad(a.latitude);
  const lat2 = toRad(b.latitude);
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
}

/** Nearest planned-route vertex to driver — display only; does not invent geometry. */
function nearestRouteIndex(
  route: DriverGeoPoint[],
  driver: DriverGeoPoint | null
): number {
  if (!driver || route.length < 2) return -1;
  let best = 0;
  let bestDist = Number.POSITIVE_INFINITY;
  for (let i = 0; i < route.length; i += 1) {
    const d = haversineMeters(driver, route[i]!);
    if (d < bestDist) {
      bestDist = d;
      best = i;
    }
  }
  return best;
}

function buildDriverMapDocument(): string {
  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    html, body, #map { height: 100%; width: 100%; margin: 0; padding: 0; background: #080B16; }
    .leaflet-container { background: #080B16; }
    .leaflet-control-attribution { display: none !important; }
  </style>
</head>
<body>
  <div id="map"></div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    (function () {
      var map = L.map('map', {
        zoomControl: false,
        dragging: true,
        scrollWheelZoom: true,
        doubleClickZoom: true,
        boxZoom: true,
        keyboard: true,
        tap: true
      });

      function setView(lat, lng, zoom) {
        map.setView([lat, lng], zoom || 13);
      }

      function handleSync(data) {
        map.invalidateSize({ animate: false });
        if (typeof data.latitude === 'number' && typeof data.longitude === 'number') {
          setView(data.latitude, data.longitude, data.zoom || 13);
        } else if (data.bounds && data.bounds.length >= 2) {
          map.fitBounds(L.latLngBounds(data.bounds), { padding: [30, 30] });
        }
      }

      window.addEventListener('message', function (event) {
        var data = event.data;
        if (typeof data === 'string') {
          try { data = JSON.parse(data); } catch(e) {}
        }
        data = data || {};
        if (data.type === 'fleetx-driver-map-sync') handleSync(data);
      });

      document.addEventListener('message', function (event) {
        var data = event.data;
        if (typeof data === 'string') {
          try { data = JSON.parse(data); } catch(e) {}
        }
        data = data || {};
        if (data.type === 'fleetx-driver-map-sync') handleSync(data);
      });

      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(map);

      map.whenReady(function () {
        setTimeout(function () {
          map.invalidateSize({ animate: false });
        }, 50);
      });
    })();
  </script>
</body>
</html>`;
}

function project(
  markers: Marker[],
  route: { latitude: number; longitude: number }[],
  width: number,
  height: number,
  focus: MapFocusMode
) {
  const pts = [
    ...markers.map((m) => ({ lat: m.latitude, lng: m.longitude, kind: m.kind })),
    ...route.map((r) => ({ lat: r.latitude, lng: r.longitude, kind: 'route' as const })),
  ];
  if (!pts.length) {
    return {
      markers: [] as (Marker & { x: number; y: number })[],
      route: [] as { x: number; y: number }[],
    };
  }

  const driver = markers.find((m) => m.kind === 'driver');
  let subset = pts;

  if (focus === 'driver' && driver) {
    subset = [
      { lat: driver.latitude - 0.06, lng: driver.longitude - 0.06, kind: 'driver' },
      { lat: driver.latitude + 0.06, lng: driver.longitude + 0.06, kind: 'driver' },
    ];
  } else if (focus === 'pickup') {
    const kinds = new Set(['driver', 'pickup', 'route']);
    const filtered = pts.filter((p) => kinds.has(p.kind));
    if (filtered.length) subset = filtered;
  } else if (focus === 'delivery') {
    const kinds = new Set(['driver', 'delivery', 'route']);
    const filtered = pts.filter((p) => kinds.has(p.kind));
    if (filtered.length) subset = filtered;
  }
  // overview → all pts

  let lats = subset.map((p) => p.lat);
  let lngs = subset.map((p) => p.lng);

  let minLat = Math.min(...lats);
  let maxLat = Math.max(...lats);
  let minLng = Math.min(...lngs);
  let maxLng = Math.max(...lngs);
  if (maxLat - minLat < 0.01) {
    minLat -= 0.05;
    maxLat += 0.05;
  }
  if (maxLng - minLng < 0.01) {
    minLng -= 0.05;
    maxLng += 0.05;
  }
  const pad = 36;
  const map = (lat: number, lng: number) => ({
    x: pad + ((lng - minLng) / (maxLng - minLng)) * (width - pad * 2),
    y: pad + (1 - (lat - minLat) / (maxLat - minLat)) * (height - pad * 2),
  });

  return {
    markers: markers.map((m) => ({ ...m, ...map(m.latitude, m.longitude) })),
    route: route.map((r) => map(r.latitude, r.longitude)),
  };
}

function colorFor(kind: MarkerKind, primary: string) {
  if (kind === 'pickup') return PICKUP_COLOR;
  if (kind === 'delivery') return DELIVERY_COLOR;
  return primary;
}

function placeLabel(place: {
  company: string | null;
  city: string | null;
  address: string | null;
} | null | undefined): string {
  if (!place) return 'Unavailable';
  const line = [place.company, place.city].filter(Boolean).join(' · ');
  return line || place.address || 'Unavailable';
}

function formatAge(fromIso: string | null | undefined, nowMs: number): string {
  if (!fromIso) return 'time unknown';
  const ageSec = Math.max(0, Math.floor((nowMs - new Date(fromIso).getTime()) / 1000));
  if (ageSec < 5) return 'just now';
  if (ageSec < 60) return `${ageSec} seconds ago`;
  const mins = Math.floor(ageSec / 60);
  return mins === 1 ? '1 minute ago' : `${mins} minutes ago`;
}

/**
 * Location confidence from existing device vs server sources only — no invented accuracy.
 */
function locationConfidence(
  deviceFix: DeviceFix | null,
  trip: DriverTripMap | null,
  nowMs: number,
  permissionDenied: boolean
): {
  title: string;
  subtitle: string;
  detail: string;
  trackingStatus: string;
  lastUpdateShort: string;
  source: 'device' | 'server' | 'none';
} {
  if (deviceFix) {
    const age = formatAge(deviceFix.recordedAt, nowMs);
    return {
      title: 'LIVE LOCATION',
      subtitle: 'Using device GPS',
      detail: `Updated ${age}`,
      trackingStatus: 'ACTIVE',
      lastUpdateShort: age,
      source: 'device',
    };
  }
  if (trip?.currentPosition) {
    const recorded =
      trip.currentPosition.recordedAt ??
      (trip.freshness.recordedAt || null);
    const age =
      trip.freshness?.ageSeconds != null && trip.freshness.ageSeconds > 0
        ? trip.freshness.ageSeconds < 60
          ? `${trip.freshness.ageSeconds} seconds ago`
          : `${Math.floor(trip.freshness.ageSeconds / 60)} minutes ago`
        : formatAge(recorded, nowMs);
    return {
      title: 'LAST KNOWN LOCATION',
      subtitle: 'Using server position',
      detail: `Updated ${age}`,
      trackingStatus: 'LIMITED',
      lastUpdateShort: age,
      source: 'server',
    };
  }
  return {
    title: 'LOCATION UNAVAILABLE',
    subtitle: permissionDenied
      ? 'Location services are disabled'
      : 'No position available yet',
    detail: permissionDenied
      ? 'Enable GPS for live driver tracking.'
      : 'Enable GPS to improve tracking',
    trackingStatus: 'UNAVAILABLE',
    lastUpdateShort: '—',
    source: 'none',
  };
}

/**
 * Prefer parent tracking badge when provided; confidence drives copy.
 */
function resolveTrackingBadge(
  trackingLabel: string,
  confidence: ReturnType<typeof locationConfidence>
): string {
  if (confidence.source === 'device') {
    return trackingLabel === 'LIMITED' ? 'LIMITED' : 'ACTIVE';
  }
  if (confidence.source === 'server') {
    return trackingLabel === 'UNAVAILABLE' ? 'UNAVAILABLE' : 'LIMITED';
  }
  return 'UNAVAILABLE';
}

export type DriverTripMapViewProps = {
  trip: DriverTripMap | null;
  deviceFix: DeviceFix | null;
  theme: Theme;
  trackingLabel: string;
  permissionLabel: string | null;
  focus?: MapFocusMode;
  onRecenter: () => void;
  onFitTrip: () => void;
  mapLoading?: boolean;
  locationLoading?: boolean;
  /** Existing execution phase — display only. */
  executionPhase?: string | null;
  assignmentStatusLabel?: string | null;
  /** Assignment status.key for timeline completion (e.g. completed). */
  assignmentStatusKey?: string | null;
  online?: boolean;
  onOpenInMaps?: () => void;
  mapsActionDisabled?: boolean;
  mapsActionHint?: string | null;
};

/**
 * Live schematic Active Trip map (D6 / MAP-02 / MAP-03).
 * OpenStreetMap tile layer provides visible geographic streets and labels.
 */
function DriverTripMapViewInner({
  trip,
  deviceFix,
  theme,
  trackingLabel,
  permissionLabel,
  focus = 'overview',
  onRecenter,
  onFitTrip,
  mapLoading = false,
  locationLoading = false,
  executionPhase = null,
  assignmentStatusLabel = null,
  assignmentStatusKey = null,
  online = true,
  onOpenInMaps,
  mapsActionDisabled = false,
  mapsActionHint = null,
}: DriverTripMapViewProps) {
  const [size, setSize] = useState({ w: 0, h: 0 });
  const [nowMs, setNowMs] = useState(() => Date.now());
  const webViewRef = useRef<WebView>(null);
  const htmlDoc = useMemo(() => buildDriverMapDocument(), []);

  // Local clock for freshness labels only — never hits the API.
  useEffect(() => {
    const id = setInterval(() => setNowMs(Date.now()), 1000);
    return () => clearInterval(id);
  }, []);

  const model = useMemo(() => {
    const markers: Marker[] = [];
    const pickup = trip?.shipment?.pickup.coordinates;
    const delivery = trip?.shipment?.delivery.coordinates;
    if (pickup && usable(pickup.latitude, pickup.longitude)) {
      markers.push({
        kind: 'pickup',
        latitude: pickup.latitude,
        longitude: pickup.longitude,
        label: 'Pickup',
      });
    }
    if (delivery && usable(delivery.latitude, delivery.longitude)) {
      markers.push({
        kind: 'delivery',
        latitude: delivery.latitude,
        longitude: delivery.longitude,
        label: 'Delivery',
      });
    }
    const server = trip?.currentPosition;
    const driverLat = deviceFix?.latitude ?? server?.latitude;
    const driverLng = deviceFix?.longitude ?? server?.longitude;
    let driverPoint: DriverGeoPoint | null = null;
    if (driverLat != null && driverLng != null && usable(driverLat, driverLng)) {
      driverPoint = { latitude: driverLat, longitude: driverLng };
      markers.push({
        kind: 'driver',
        latitude: driverLat,
        longitude: driverLng,
        label: deviceFix ? 'You (device)' : 'You (server)',
      });
    }
    const route =
      trip?.routeAvailable && (trip.route?.coordinates?.length ?? 0) >= 2
        ? trip.route!.coordinates
        : [];
    const progressIdx = nearestRouteIndex(route, driverPoint);
    return { markers, route, driverPoint, progressIdx };
  }, [trip, deviceFix]);

  const projected = useMemo(() => {
    if (size.w <= 0 || size.h <= 0) {
      return { markers: [] as (Marker & { x: number; y: number })[], route: [] as { x: number; y: number }[] };
    }
    return project(model.markers, model.route, size.w, size.h, focus);
  }, [model.markers, model.route, size.w, size.h, focus]);

  useEffect(() => {
    const center = model.driverPoint || model.markers[0] || { latitude: 33.54681, longitude: 36.30194 };
    const bounds = model.markers.map((m) => [m.latitude, m.longitude]);
    const payload = JSON.stringify({
      type: 'fleetx-driver-map-sync',
      latitude: center.latitude,
      longitude: center.longitude,
      zoom: focus === 'driver' ? 14 : 12,
      bounds,
    });
    const timer = setTimeout(() => {
      if (Platform.OS !== 'web') {
        webViewRef.current?.injectJavaScript(`
          (function() {
            var data = ${payload};
            var event = new MessageEvent('message', { data: data });
            window.dispatchEvent(event);
            document.dispatchEvent(event);
          })();
          true;
        `);
      }
    }, 100);
    return () => clearTimeout(timer);
  }, [model.markers, model.driverPoint, focus]);

  const onLayout = (e: LayoutChangeEvent) => {
    const { width, height } = e.nativeEvent.layout;
    setSize((prev) =>
      prev.w === Math.round(width) && prev.h === Math.round(height)
        ? prev
        : { w: Math.round(width), h: Math.round(height) }
    );
  };

  const hasGeo = model.markers.length > 0;
  const hasPickupOrDelivery = model.markers.some(
    (m) => m.kind === 'pickup' || m.kind === 'delivery'
  );
  const routeAvailable = Boolean(trip?.routeAvailable) && model.route.length >= 2;
  const permissionDenied = Boolean(permissionLabel);
  const confidence = locationConfidence(deviceFix, trip, nowMs, permissionDenied);
  const trackingBadge = resolveTrackingBadge(trackingLabel, confidence);
  const phaseLabel = phaseStatusLabel(executionPhase);
  const timeline = useMemo(
    () => buildTripTimelineMilestones(executionPhase, assignmentStatusKey),
    [executionPhase, assignmentStatusKey]
  );

  let canvasBody: ReactNode;
  if (mapLoading) {
    canvasBody = (
      <View style={styles.stateBox} testID="active-trip-map-loading">
        <ActivityIndicator color={theme.primary} />
        <Text style={styles.stateTitle}>Loading trip map…</Text>
        {locationLoading ? (
          <Text style={styles.stateBody}>Loading location…</Text>
        ) : (
          <Text style={styles.stateBody}>Preparing route…</Text>
        )}
      </View>
    );
  } else if (!trip) {
    canvasBody = (
      <View style={styles.stateBox} testID="active-trip-map-unavailable">
        <Text style={styles.stateTitle}>Trip map unavailable</Text>
        <Text style={styles.stateBody}>
          {online
            ? 'Map data could not be loaded. Trip execution can continue normally.'
            : 'Showing last available trip data. Trip execution can continue normally.'}
        </Text>
      </View>
    );
  } else if (!hasGeo) {
    canvasBody = (
      <View style={styles.stateBox} testID="active-trip-map-empty">
        <Text style={styles.stateTitle}>Location details are unavailable for this stop.</Text>
        <Text style={styles.stateBody}>
          Trip execution can continue normally using address details below.
        </Text>
      </View>
    );
  } else {
    canvasBody = (
      <>
        {Platform.OS === 'web' ? (
          createElement('iframe', {
            srcDoc: htmlDoc,
            style: {
              position: 'absolute',
              top: 0,
              left: 0,
              width: '100%',
              height: '100%',
              border: 0,
              display: 'block',
              backgroundColor: '#080B16',
            },
          })
        ) : (
          <WebView
            ref={webViewRef}
            originWhitelist={['*']}
            source={{ html: htmlDoc }}
            style={StyleSheet.absoluteFill}
            scrollEnabled={false}
            overScrollMode="never"
          />
        )}
        <View style={styles.gridOverlay} pointerEvents="none" />
        {projected.route.map((p, i) => {
          const next = projected.route[i + 1];
          if (!next) return null;
          const dx = next.x - p.x;
          const dy = next.y - p.y;
          const len = Math.sqrt(dx * dx + dy * dy);
          if (len < 1) return null;
          const angle = (Math.atan2(dy, dx) * 180) / Math.PI;
          const progressed = model.progressIdx >= 0 && i < model.progressIdx;
          return (
            <View
              key={`seg-${i}`}
              pointerEvents="none"
              style={[
                styles.seg,
                {
                  width: len,
                  left: (p.x + next.x) / 2 - len / 2,
                  top: (p.y + next.y) / 2 - 1.5,
                  transform: [{ rotate: `${angle}deg` }],
                  backgroundColor: progressed ? ROUTE_PROGRESS_COLOR : ROUTE_COLOR,
                  opacity: progressed ? 0.9 : 0.55,
                },
              ]}
            />
          );
        })}
        {projected.markers.map((m) => (
          <View
            key={m.kind === 'driver' ? 'driver' : `${m.kind}-${m.latitude}`}
            testID={`active-trip-marker-${m.kind}`}
            style={[styles.markerWrap, { left: m.x - 14, top: m.y - 14 }]}
            accessibilityLabel={m.label}>
            {m.kind === 'driver' ? <View style={styles.driverHalo} /> : null}
            <View
              style={[
                styles.marker,
                m.kind === 'driver' ? styles.markerDriver : styles.markerHub,
                { backgroundColor: colorFor(m.kind, theme.primary) },
              ]}
            />
            <View
              style={[
                styles.markerBadge,
                m.kind === 'pickup'
                  ? styles.badgePickup
                  : m.kind === 'delivery'
                    ? styles.badgeDelivery
                    : styles.badgeDriver,
              ]}>
              <Text style={styles.markerLabel}>
                {m.kind === 'pickup' ? 'Pickup' : m.kind === 'delivery' ? 'Delivery' : 'DRIVER'}
              </Text>
            </View>
          </View>
        ))}
      </>
    );
  }

  return (
    <View style={styles.wrap} testID="active-trip-map-slot">
      <View style={styles.headerRow}>
        <View style={styles.headerTitles}>
          <Text style={styles.title} accessibilityRole="header">
            Trip map
          </Text>
          <Text style={styles.subtitle} testID="active-trip-map-phase">
            {phaseLabel}
          </Text>
        </View>
        <View
          style={styles.trackingPill}
          testID="active-trip-tracking-status"
          accessibilityLabel={`Tracking ${trackingBadge}`}>
          <View
            style={[
              styles.trackingDot,
              trackingBadge === 'ACTIVE'
                ? styles.dotActive
                : trackingBadge === 'LIMITED'
                  ? styles.dotLimited
                  : styles.dotOff,
            ]}
          />
          <Text style={styles.trackingText}>{trackingBadge}</Text>
        </View>
      </View>

      <View
        style={styles.timelineCard}
        testID="active-trip-map-timeline"
        accessibilityRole="summary"
        accessibilityLabel="Trip progress">
        <Text style={styles.opsTitle}>TRIP PROGRESS</Text>
        {timeline.map((step) => (
          <View key={step.id} style={styles.timelineRow}>
            <Text
              style={[
                styles.timelineGlyph,
                step.mark === 'done'
                  ? styles.timelineDone
                  : step.mark === 'current'
                    ? styles.timelineCurrent
                    : styles.timelineTodo,
              ]}
              accessibilityLabel={`${step.label}, ${step.mark}`}>
              {markGlyph(step.mark)}
            </Text>
            <Text
              style={[
                styles.timelineLabel,
                step.mark === 'todo' && styles.timelineLabelTodo,
                step.mark === 'current' && styles.timelineLabelCurrent,
              ]}>
              {step.label}
            </Text>
          </View>
        ))}
      </View>

      {permissionLabel ? (
        <View style={styles.noticeCard} testID="active-trip-location-permission">
          <Text style={styles.noticeTitle}>Location services are disabled.</Text>
          <Text style={styles.noticeBody}>Enable GPS for live driver tracking.</Text>
        </View>
      ) : null}

      {!online ? (
        <Text testID="active-trip-map-offline" style={styles.offlineHint}>
          Showing last available trip data. Trip execution can continue normally.
        </Text>
      ) : null}

      <View
        style={[
          styles.driverPanel,
          confidence.source === 'none' && styles.driverPanelMuted,
        ]}
        testID="active-trip-map-driver-panel"
        accessibilityLabel={`${confidence.title}. ${confidence.subtitle}. ${confidence.detail}`}>
        <Text style={styles.driverPanelTitle}>{confidence.title}</Text>
        <Text style={styles.driverPanelStatus} testID="active-trip-map-live-status">
          {confidence.subtitle}
        </Text>
        <Text style={styles.driverPanelDetail} testID="active-trip-freshness">
          {confidence.detail}
        </Text>
      </View>

      <View style={styles.canvas} onLayout={onLayout} testID="active-trip-map-canvas">
        {canvasBody}
      </View>

      {!mapLoading && trip && !routeAvailable ? (
        <View style={styles.noticeCard} testID="active-trip-route-unavailable">
          <Text style={styles.noticeTitle}>Route preview is currently unavailable.</Text>
          <Text style={styles.noticeBody}>Trip execution can continue normally.</Text>
        </View>
      ) : null}

      {!mapLoading && trip && routeAvailable ? (
        <Text testID="active-trip-route-available" style={styles.routeHintOk}>
          Planned route shown
          {model.progressIdx >= 0 ? ' · progress from live position' : ''}
        </Text>
      ) : null}

      {!mapLoading && trip && !hasPickupOrDelivery ? (
        <Text style={styles.routeHint} testID="active-trip-map-endpoints-unavailable">
          Location details are unavailable for this stop.
        </Text>
      ) : null}

      <View style={styles.opsCard} testID="active-trip-map-ops-card">
        <Text style={styles.opsTitle}>CURRENT TRIP</Text>
        <Text style={styles.opsRow}>
          <Text style={styles.opsKey}>Pickup:{'\n'}</Text>
          {placeLabel(trip?.shipment?.pickup)}
        </Text>
        <Text style={styles.opsRow}>
          <Text style={styles.opsKey}>Delivery:{'\n'}</Text>
          {placeLabel(trip?.shipment?.delivery)}
        </Text>
        <Text style={styles.opsRow}>
          <Text style={styles.opsKey}>Current status: </Text>
          {(assignmentStatusLabel || phaseLabel || '—').toUpperCase()}
        </Text>
        <Text style={styles.opsRow}>
          <Text style={styles.opsKey}>Tracking: </Text>
          {trackingBadge}
        </Text>
        <Text style={styles.opsRow} testID="active-trip-map-ops-update">
          <Text style={styles.opsKey}>Last update: </Text>
          {confidence.lastUpdateShort}
        </Text>
      </View>

      <View style={styles.legend} testID="active-trip-map-legend">
        <Text style={[styles.legendItem, { color: theme.primary }]}>● DRIVER</Text>
        <Text style={[styles.legendItem, { color: PICKUP_COLOR }]}>● Pickup</Text>
        <Text style={[styles.legendItem, { color: DELIVERY_COLOR }]}>● Delivery</Text>
      </View>

      <View style={styles.controls}>
        <Pressable
          testID="active-trip-map-recenter"
          onPress={onRecenter}
          style={[styles.chip, focus === 'driver' && styles.chipActive]}
          accessibilityRole="button"
          accessibilityLabel="Focus driver on map">
          <Text style={[styles.chipText, { color: theme.primary }]}>Focus driver</Text>
        </Pressable>
        <Pressable
          testID="active-trip-map-fit"
          onPress={onFitTrip}
          style={[
            styles.chip,
            (focus === 'overview' || focus === 'pickup' || focus === 'delivery') &&
            styles.chipActive,
          ]}
          accessibilityRole="button"
          accessibilityLabel="Fit full trip on map">
          <Text style={[styles.chipText, { color: theme.primary }]}>Fit trip</Text>
        </Pressable>
        {onOpenInMaps ? (
          <Pressable
            testID="active-trip-open-maps"
            onPress={onOpenInMaps}
            disabled={mapsActionDisabled}
            style={[styles.chip, mapsActionDisabled && styles.chipDisabled]}
            accessibilityRole="button"
            accessibilityLabel="Open destination in external Maps"
            accessibilityState={{ disabled: mapsActionDisabled }}>
            <Text
              style={[
                styles.chipText,
                { color: mapsActionDisabled ? DriverTheme.colors.textMuted : theme.primary },
              ]}>
              Open in Maps
            </Text>
          </Pressable>
        ) : null}
      </View>

      {mapsActionHint ? (
        <Text testID="active-trip-maps-hint" style={styles.routeHint}>
          {mapsActionHint}
        </Text>
      ) : null}
    </View>
  );
}

export const DriverTripMapView = memo(DriverTripMapViewInner);

const styles = StyleSheet.create({
  wrap: {
    gap: DriverTheme.spacing.sm,
    padding: DriverTheme.spacing.lg,
    borderRadius: DriverTheme.radius.large,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
    backgroundColor: DriverTheme.colors.surface,
    ...DriverTheme.elevation.card,
    minHeight: 220,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: DriverTheme.spacing.sm,
  },
  headerTitles: { flex: 1, gap: 2 },
  title: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
  },
  subtitle: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
  trackingPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: DriverTheme.radius.pill,
    backgroundColor: DriverTheme.colors.infoSoft,
  },
  trackingDot: { width: 8, height: 8, borderRadius: 4 },
  dotActive: { backgroundColor: DriverTheme.colors.success },
  dotLimited: { backgroundColor: DriverTheme.colors.warning },
  dotOff: { backgroundColor: DriverTheme.colors.textMuted },
  trackingText: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 0.4,
    color: DriverTheme.colors.textPrimary,
  },
  permission: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.warning,
  },
  offlineHint: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.warning,
  },
  noticeCard: {
    gap: 4,
    padding: DriverTheme.spacing.md,
    borderRadius: DriverTheme.radius.medium,
    backgroundColor: DriverTheme.colors.warningSoft,
    borderWidth: 1,
    borderColor: '#FDE68A',
  },
  noticeTitle: {
    ...DriverTheme.typography.caption,
    fontWeight: '700',
    color: DriverTheme.colors.textPrimary,
  },
  noticeBody: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
  timelineCard: {
    gap: 6,
    padding: DriverTheme.spacing.md,
    borderRadius: DriverTheme.radius.medium,
    backgroundColor: DriverTheme.colors.background,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
  },
  timelineRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    minHeight: 22,
  },
  timelineGlyph: {
    width: 18,
    fontSize: 14,
    fontWeight: '700',
    textAlign: 'center',
  },
  timelineDone: { color: DriverTheme.colors.success },
  timelineCurrent: { color: DriverTheme.colors.primary },
  timelineTodo: { color: DriverTheme.colors.textMuted },
  timelineLabel: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textPrimary,
    fontWeight: '600',
    flex: 1,
  },
  timelineLabelCurrent: {
    color: DriverTheme.colors.primary,
    fontWeight: '700',
  },
  timelineLabelTodo: {
    color: DriverTheme.colors.textMuted,
    fontWeight: '500',
  },
  driverPanel: {
    padding: DriverTheme.spacing.md,
    borderRadius: DriverTheme.radius.medium,
    backgroundColor: DriverTheme.colors.navyElevated,
    gap: 2,
  },
  driverPanelMuted: {
    backgroundColor: '#334155',
  },
  driverPanelTitle: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.8,
    color: 'rgba(255,255,255,0.7)',
  },
  driverPanelStatus: {
    fontSize: 16,
    fontWeight: '700',
    color: DriverTheme.colors.textOnNavy,
  },
  driverPanelDetail: {
    ...DriverTheme.typography.caption,
    color: 'rgba(255,255,255,0.75)',
  },
  canvas: {
    height: 280,
    borderRadius: DriverTheme.radius.medium,
    backgroundColor: '#E8EEF5',
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
  },
  gridOverlay: {
    ...StyleSheet.absoluteFill,
    opacity: 0.12,
    backgroundColor: '#CBD5E1',
  },
  stateBox: {
    flex: 1,
    padding: DriverTheme.spacing.lg,
    justifyContent: 'center',
    alignItems: 'center',
    gap: DriverTheme.spacing.sm,
  },
  stateTitle: {
    ...DriverTheme.typography.bodyMedium,
    color: DriverTheme.colors.textPrimary,
    textAlign: 'center',
  },
  stateBody: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
    textAlign: 'center',
  },
  stateHint: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.warning,
    textAlign: 'center',
    marginTop: 4,
  },
  seg: {
    position: 'absolute',
    height: 3,
    borderRadius: 2,
  },
  markerWrap: { position: 'absolute', alignItems: 'center', width: 28 },
  driverHalo: {
    position: 'absolute',
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: 'rgba(30,117,255,0.22)',
    top: -7,
    left: -7,
  },
  marker: {
    borderColor: '#fff',
    borderWidth: 2,
  },
  markerDriver: {
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 3,
  },
  markerHub: {
    width: 14,
    height: 14,
    borderRadius: 7,
  },
  markerBadge: {
    marginTop: 4,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 6,
  },
  badgePickup: { backgroundColor: '#ECFDF5' },
  badgeDelivery: { backgroundColor: '#EFF6FF' },
  badgeDriver: { backgroundColor: '#DBEAFE' },
  markerLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 0.3,
    color: DriverTheme.colors.textPrimary,
  },
  routeHint: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textSecondary,
  },
  routeHintOk: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.success,
  },
  opsCard: {
    gap: 4,
    padding: DriverTheme.spacing.md,
    borderRadius: DriverTheme.radius.medium,
    backgroundColor: DriverTheme.colors.background,
    borderWidth: 1,
    borderColor: DriverTheme.colors.border,
  },
  opsTitle: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.7,
    color: DriverTheme.colors.textSecondary,
    marginBottom: 4,
  },
  opsRow: {
    ...DriverTheme.typography.caption,
    color: DriverTheme.colors.textPrimary,
  },
  opsKey: {
    fontWeight: '700',
    color: DriverTheme.colors.textSecondary,
  },
  legend: {
    flexDirection: 'row',
    gap: DriverTheme.spacing.md,
    flexWrap: 'wrap',
  },
  legendItem: { fontSize: 11, fontWeight: '700', opacity: 0.9 },
  controls: { flexDirection: 'row', gap: DriverTheme.spacing.sm, flexWrap: 'wrap' },
  chip: {
    minHeight: 44,
    paddingHorizontal: DriverTheme.spacing.md,
    justifyContent: 'center',
    borderRadius: DriverTheme.radius.medium,
    backgroundColor: DriverTheme.colors.infoSoft,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  chipActive: {
    borderColor: DriverTheme.colors.primary,
  },
  chipDisabled: {
    opacity: 0.55,
  },
  chipText: { fontSize: 14, fontWeight: '700' },
});
