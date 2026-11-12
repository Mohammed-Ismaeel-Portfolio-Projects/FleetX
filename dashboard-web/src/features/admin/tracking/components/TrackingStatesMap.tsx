import { Map } from 'lucide-react';
import type { TrackingState } from '../types/tracking.types';

interface TrackingStatesMapProps {
  trackingStates: TrackingState[];
  isLoading?: boolean;
}

function toCoordinate(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }
  return null;
}

function isValidCoordinate(lat: number, lng: number): boolean {
  return lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
}

function projectPoint(
  lat: number,
  lng: number,
  bounds: { minLat: number; maxLat: number; minLng: number; maxLng: number },
  width: number,
  height: number,
  padding: number
): { x: number; y: number } {
  const latSpan = Math.max(bounds.maxLat - bounds.minLat, 0.0001);
  const lngSpan = Math.max(bounds.maxLng - bounds.minLng, 0.0001);
  const innerWidth = width - padding * 2;
  const innerHeight = height - padding * 2;
  const x = padding + ((lng - bounds.minLng) / lngSpan) * innerWidth;
  const y = padding + ((bounds.maxLat - lat) / latSpan) * innerHeight;
  return { x, y };
}

export default function TrackingStatesMap({ trackingStates, isLoading = false }: TrackingStatesMapProps) {
  const points = trackingStates.flatMap((state) => {
    const latitude = toCoordinate(state.latitude);
    const longitude = toCoordinate(state.longitude);
    if (latitude === null || longitude === null || !isValidCoordinate(latitude, longitude)) {
      return [];
    }

    const label =
      state.vehicle?.fleet_number
      || state.shipment?.shipment_number
      || state.vehicle_id
      || state.id;

    return [{ id: state.id, latitude, longitude, label }];
  });

  if (isLoading) {
    return (
      <div className="flex h-[300px] items-center justify-center rounded-2xl border border-white/5 bg-[#0E1320]">
        <p className="text-sm text-slate-400">Loading map positions…</p>
      </div>
    );
  }

  if (points.length === 0) {
    return (
      <div className="flex h-[300px] items-center justify-center rounded-2xl border border-white/5 bg-[#0E1320]">
        <div className="text-center px-6">
          <Map className="mx-auto h-12 w-12 text-slate-600" />
          <p className="mt-3 text-sm font-semibold text-white">Map unavailable</p>
          <p className="mt-1 text-xs text-slate-500">
            No valid latitude/longitude values in the loaded tracking states.
          </p>
        </div>
      </div>
    );
  }

  const lats = points.map((point) => point.latitude);
  const lngs = points.map((point) => point.longitude);
  const bounds = {
    minLat: Math.min(...lats),
    maxLat: Math.max(...lats),
    minLng: Math.min(...lngs),
    maxLng: Math.max(...lngs),
  };

  const width = 800;
  const height = 280;
  const padding = 28;

  return (
    <div className="rounded-2xl border border-white/5 bg-[#0E1320] p-4">
      <div className="mb-3">
        <h2 className="text-sm font-semibold text-white">Tracking positions</h2>
        <p className="mt-1 text-[11px] text-slate-500">
          Coordinates from the currently loaded GET /tracking-states rows. No synthetic locations.
        </p>
      </div>
      <svg
        viewBox={`0 0 ${width} ${height}`}
        className="h-[260px] w-full rounded-xl border border-white/5 bg-[#0B0F19]"
        role="img"
        aria-label="Tracking state coordinates"
      >
        {points.map((point) => {
          const { x, y } = projectPoint(point.latitude, point.longitude, bounds, width, height, padding);
          return (
            <g key={point.id}>
              <circle cx={x} cy={y} r="6" fill="#22d3ee" opacity="0.9" />
              <text x={x + 10} y={y + 4} fill="#94a3b8" fontSize="11">
                {point.label}
              </text>
            </g>
          );
        })}
      </svg>
    </div>
  );
}
