/**
 * Route monitoring status types for dispatcher / map overlays.
 */

export type DispatcherRouteStatus =
  | 'ON_ROUTE'
  | 'DEVIATED'
  | 'REROUTING'
  | 'ARRIVED'
  | 'OFFLINE';

export interface RouteDeviationMessage {
  vehicle_id: string;
  shipment_id: string;
  assignment_id: string | null;
  deviation_distance: number;
  location: {
    latitude: number;
    longitude: number;
  };
  timestamp: string;
}

export const ROUTE_STATUS_META: Record<
  DispatcherRouteStatus,
  { label: string; color: string; bg: string; border: string; dot: string }
> = {
  ON_ROUTE: {
    label: 'ON ROUTE',
    color: '#86efac',
    bg: 'rgba(34, 197, 94, 0.14)',
    border: 'rgba(34, 197, 94, 0.35)',
    dot: '#22c55e',
  },
  DEVIATED: {
    label: 'DEVIATED',
    color: '#fca5a5',
    bg: 'rgba(239, 68, 68, 0.14)',
    border: 'rgba(239, 68, 68, 0.4)',
    dot: '#ef4444',
  },
  REROUTING: {
    label: 'REROUTING',
    color: '#fde68a',
    bg: 'rgba(245, 158, 11, 0.14)',
    border: 'rgba(245, 158, 11, 0.4)',
    dot: '#f59e0b',
  },
  ARRIVED: {
    label: 'ARRIVED',
    color: '#93c5fd',
    bg: 'rgba(59, 130, 246, 0.14)',
    border: 'rgba(59, 130, 246, 0.4)',
    dot: '#3b82f6',
  },
  OFFLINE: {
    label: 'OFFLINE',
    color: '#cbd5e1',
    bg: 'rgba(100, 116, 139, 0.18)',
    border: 'rgba(148, 163, 184, 0.35)',
    dot: '#64748b',
  },
};
