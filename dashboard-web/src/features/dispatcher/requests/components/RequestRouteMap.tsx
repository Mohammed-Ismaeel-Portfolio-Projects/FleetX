/**
 * Pickup → Delivery route map for the dispatcher request review.
 * Reuses the existing Leaflet/react-leaflet stack from the dashboard.
 * Coordinates come from the persisted request — never invented.
 */
import { MapContainer, TileLayer, Marker, Popup, Polyline, useMap } from 'react-leaflet';
import L from 'leaflet';
import { useEffect, useMemo } from 'react';
import type { ShipmentRequestDetail } from '../types/request.types';

const pickupIcon = new L.Icon({
  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

const deliveryIcon = new L.Icon({
  iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

function FitBounds({ bounds }: { bounds: L.LatLngBoundsExpression }) {
  const map = useMap();
  useEffect(() => {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
  }, [map, bounds]);
  return null;
}

interface RequestRouteMapProps {
  request: ShipmentRequestDetail;
}

export default function RequestRouteMap({ request }: RequestRouteMapProps) {
  const hasPickup = request.pickupLatitude != null && request.pickupLongitude != null;
  const hasDelivery = request.deliveryLatitude != null && request.deliveryLongitude != null;
  const plannedRoute = request.plannedRoute;
  const isAvailable = plannedRoute?.status === 'AVAILABLE';
  const geometry = plannedRoute?.geometry;

  const polylinePositions = useMemo(() => {
    if (!isAvailable || !Array.isArray(geometry) || geometry.length < 2) return [];
    return geometry as [number, number][];
  }, [isAvailable, geometry]);

  const bounds = useMemo(() => {
    const points: [number, number][] = [];
    if (hasPickup) points.push([request.pickupLatitude!, request.pickupLongitude!]);
    if (hasDelivery) points.push([request.deliveryLatitude!, request.deliveryLongitude!]);
    if (polylinePositions.length > 0) {
      points.push(...polylinePositions);
    }
    if (points.length === 0) return null;
    return L.latLngBounds(points);
  }, [hasPickup, hasDelivery, polylinePositions, request.pickupLatitude, request.pickupLongitude, request.deliveryLatitude, request.deliveryLongitude]);

  if (!hasPickup && !hasDelivery) return null;

  const center: L.LatLngExpression = hasPickup
    ? [request.pickupLatitude!, request.pickupLongitude!]
    : [request.deliveryLatitude!, request.deliveryLongitude!];

  const pickupLabel = [request.pickupCity, request.pickupCountry].filter(Boolean).join(', ') || 'Pickup';
  const deliveryLabel = [request.deliveryCity, request.deliveryCountry].filter(Boolean).join(', ') || 'Delivery';

  return (
    <div data-testid="dispatcher-request-map" className="relative h-72 overflow-hidden rounded-xl border border-white/10">
      {plannedRoute?.status === 'UNAVAILABLE' ? (
        <div className="absolute top-3 left-1/2 -translate-x-1/2 z-[1000] bg-amber-950/90 border border-amber-500/30 text-amber-200 text-xs px-3 py-1.5 rounded-lg shadow-lg">
          Planned route is temporarily unavailable
        </div>
      ) : null}
      <MapContainer
        center={center}
        zoom={6}
        scrollWheelZoom={false}
        dragging
        zoomControl
        style={{ height: '100%', width: '100%' }}
      >
        <TileLayer
          url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        />
        {bounds ? <FitBounds bounds={bounds} /> : null}
        {polylinePositions.length >= 2 ? (
          <Polyline
            positions={polylinePositions}
            pathOptions={{ color: '#1E75FF', weight: 5, opacity: 0.85 }}
          />
        ) : null}
        {hasPickup ? (
          <Marker position={[request.pickupLatitude!, request.pickupLongitude!]} icon={pickupIcon}>
            <Popup>
              <strong>Pickup</strong><br />{pickupLabel}
            </Popup>
          </Marker>
        ) : null}
        {hasDelivery ? (
          <Marker position={[request.deliveryLatitude!, request.deliveryLongitude!]} icon={deliveryIcon}>
            <Popup>
              <strong>Delivery</strong><br />{deliveryLabel}
            </Popup>
          </Marker>
        ) : null}
      </MapContainer>
    </div>
  );
}
