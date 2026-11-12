import { axiosClientWithInterceptors } from '../../../../core/api';
import type { LiveVehicle, FleetKPIs } from '../types/tracking.types';

interface ApiEnvelope<T> {
  data: T;
  message: string;
}

interface LiveFleetVehicleApi {
  vehicle_id: string;
  assignment_id: string;
  shipment_id: string;
  driver_id: string | null;
  trailer_id: string | null;
  vehicle?: {
    id?: string;
    operational_code?: string;
    plate_number?: string;
    fleet_number?: string;
  };
  driver?: {
    id?: string;
    employee_number?: string;
    first_name?: string;
    last_name?: string;
    full_name?: string | null;
  };
  shipment?: {
    id?: string;
    shipment_order_id?: string;
    shipment_number?: string;
    pickup_city?: string;
    delivery_city?: string;
    status?: string;
  };
  assignment?: {
    id?: string | number;
    assignment_code?: string;
    is_active?: boolean;
  };
  tracking_state?: {
    id?: string;
    latitude?: number;
    longitude?: number;
    speed?: number;
    heading?: number | null;
    accuracy?: number | null;
    movement_status?: string;
    online_status?: string;
    signal_quality?: string | number;
    last_update_at?: string;
  } | null;
  telemetry?: {
    latitude?: number | null;
    longitude?: number | null;
    speed?: number;
    heading?: number | null;
    movement_status?: string;
    online_status?: string;
    signal_quality?: string | number;
  };
  route_progress?: {
    route_id?: string | null;
    completion_percentage?: number | null;
    distance_travelled_m?: number | null;
    remaining_distance_m?: number | null;
    segment_index?: number | null;
    completed?: boolean;
    provider?: string | null;
  } | null;
}

interface LiveFleetSnapshotApi {
  vehicles: LiveFleetVehicleApi[];
  meta: {
    count: number;
    generated_at: string;
  };
}

function mapVehicleStatus(movementStatus: string, onlineStatus: string): LiveVehicle['status'] {
  const online = onlineStatus.toLowerCase();
  const movement = movementStatus.toLowerCase();

  if (online === 'offline') return 'Offline';
  if (movement === 'stopped' || movement === 'idle') return 'Idle';
  if (movement === 'moving' || movement === 'in_transit') return 'In Transit';
  return 'Active';
}

function mapSignalQualityValue(signalQuality: string | number | undefined): number {
  if (typeof signalQuality === 'number') return signalQuality;
  const normalized = String(signalQuality ?? '').toLowerCase();
  if (normalized === 'good') return 90;
  if (normalized === 'weak') return 55;
  return 20;
}

function mapLiveFleetVehicle(api: LiveFleetVehicleApi): LiveVehicle {
  const vehicleId = api.vehicle_id;
  const telemetry = api.telemetry;
  const state = api.tracking_state;
  const movementStatus = telemetry?.movement_status || state?.movement_status || 'Idle';
  const onlineStatus = telemetry?.online_status || state?.online_status || 'Offline';

  return {
    // Stable React identity — NEVER tracking_state.id
    id: vehicleId,
    vehicleId,
    assignmentId: String(api.assignment_id),
    trackingStateId: state?.id ?? null,
    driverId: api.driver_id ? String(api.driver_id) : '',
    driverName:
      api.driver?.full_name ||
      `${api.driver?.first_name || ''} ${api.driver?.last_name || ''}`.trim() ||
      'Unknown',
    shipmentId: api.shipment_id,
    shipmentOrderId: api.shipment?.shipment_order_id ?? null,
    shipmentNumber: api.shipment?.shipment_number || 'N/A',
    pickupCity: api.shipment?.pickup_city,
    deliveryCity: api.shipment?.delivery_city,
    status: mapVehicleStatus(movementStatus, onlineStatus),
    speed: telemetry?.speed ?? state?.speed ?? 0,
    latitude: telemetry?.latitude ?? state?.latitude ?? 0,
    longitude: telemetry?.longitude ?? state?.longitude ?? 0,
    heading: telemetry?.heading ?? state?.heading ?? 0,
    movementStatus,
    onlineStatus,
    signalQuality: mapSignalQualityValue(telemetry?.signal_quality ?? state?.signal_quality),
    lastUpdateAt: state?.last_update_at || new Date().toISOString(),
    vehicleCode: api.vehicle?.operational_code || api.vehicle?.fleet_number || 'N/A',
    plateNumber: api.vehicle?.plate_number || 'N/A',
    routeProgress: api.route_progress
      ? {
          routeId: api.route_progress.route_id,
          completionPercentage: api.route_progress.completion_percentage,
          distanceTravelledM: api.route_progress.distance_travelled_m,
          remainingDistanceM: api.route_progress.remaining_distance_m,
          segmentIndex: api.route_progress.segment_index,
          completed: api.route_progress.completed,
          provider: api.route_progress.provider,
        }
      : null,
  };
}

export const trackingService = {
  /**
   * Dispatcher live fleet — dedicated snapshot endpoint.
   * Do not use /tracking-states (admin/history) for the map.
   */
  async getLiveVehicles(): Promise<{ vehicles: LiveVehicle[]; meta?: LiveFleetSnapshotApi['meta'] }> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<LiveFleetSnapshotApi>>(
        '/tracking/live-fleet'
      );
      const payload = response.data.data;
      const vehicles = (payload.vehicles || []).map(mapLiveFleetVehicle);
      return {
        vehicles,
        meta: payload.meta,
      };
    } catch (error) {
      console.error('Failed to fetch live fleet snapshot:', error);
      return { vehicles: [] };
    }
  },

  async getFleetKPIs(): Promise<FleetKPIs | null> {
    try {
      const response = await axiosClientWithInterceptors.get<ApiEnvelope<FleetKPIs>>('/vehicles/fleet-kpis');
      return response.data.data;
    } catch (error) {
      console.error('Failed to fetch fleet KPIs:', error);
      return null;
    }
  },
};
