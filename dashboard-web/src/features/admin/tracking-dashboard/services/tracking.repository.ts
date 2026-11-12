import { trackingService } from '../../tracking/services/tracking.service';
import type { TrackingState } from '../../tracking/types/tracking.types';
import type { FleetVehicle, TrackingStats, TrackingEvent, TrackingDataResponse } from '../types/tracking.types';

/**
 * Maps backend MovementStatus to frontend VehicleStatus
 */
function mapMovementStatus(movementStatus: string): FleetVehicle['status'] {
  const statusMap: Record<string, FleetVehicle['status']> = {
    'Moving': 'moving',
    'Idle': 'idle',
    'Stopped': 'idle',
  };
  return statusMap[movementStatus] || 'idle';
}

/**
 * Maps backend SignalQuality to frontend SignalQuality
 */
function mapSignalQuality(signalQuality: string): FleetVehicle['signalQuality'] {
  const qualityMap: Record<string, FleetVehicle['signalQuality']> = {
    'Good': 'good',
    'Weak': 'weak',
    'Lost': 'lost',
  };
  return qualityMap[signalQuality] || 'weak';
}

/**
 * Maps backend OnlineStatus to determine if vehicle is offline
 */
function mapOnlineStatus(onlineStatus: string): boolean {
  return onlineStatus === 'Online';
}

/**
 * Maps backend TrackingState to frontend FleetVehicle
 */
function mapTrackingStateToFleetVehicle(state: TrackingState): FleetVehicle {
  const isOnline = mapOnlineStatus(state.online_status);
  const status = isOnline ? mapMovementStatus(state.movement_status) : 'offline';
  
  return {
    id: state.id,
    fleetNumber: state.vehicle?.fleet_number || `VT-${state.vehicle_id.substring(0, 4)}`,
    plateNumber: state.vehicle?.plate_number || 'UNKNOWN',
    driver: state.driver?.full_name || 'Unknown Driver',
    driverId: state.driver_id.toString(),
    assignmentId: state.assignment_id != null ? String(state.assignment_id) : undefined,
    shipment: state.shipment?.shipment_number || `SH-${state.shipment_id.substring(0, 5)}`,
    shipmentId: state.shipment_id,
    status,
    speed: state.speed || 0,
    heading: state.heading || 0,
    signalQuality: mapSignalQuality(state.signal_quality),
    lastUpdate: state.last_update_at || state.updated_at,
    latitude: state.latitude,
    longitude: state.longitude,
  };
}

/**
 * Calculates statistics from fleet vehicles
 */
function calculateStats(vehicles: FleetVehicle[]): TrackingStats {
  const movingVehicles = vehicles.filter(v => v.status === 'moving').length;
  const idleVehicles = vehicles.filter(v => v.status === 'idle').length;
  const offlineVehicles = vehicles.filter(v => v.status === 'offline').length;
  
  const activeVehicles = vehicles.filter(v => v.status !== 'offline');
  const averageSpeed = activeVehicles.length > 0
    ? activeVehicles.reduce((sum, v) => sum + v.speed, 0) / activeVehicles.length
    : 0;
  
  return {
    movingVehicles,
    idleVehicles,
    offlineVehicles,
    activeAssignments: vehicles.length,
    openAlerts: 0, // TODO: Calculate from alerts when available
    averageSpeed: Math.round(averageSpeed * 10) / 10,
  };
}



/**
 * Tracking Repository
 * Isolates API implementation and provides data mapping
 */
export const trackingRepository = {
  /**
   * Fetch all fleet vehicles with their tracking states
   */
  async getFleet(): Promise<TrackingDataResponse> {
    try {
      const liveSnapshot = await trackingService.getLiveVehicles();
      if (liveSnapshot.vehicles && liveSnapshot.vehicles.length > 0) {
        const vehicles: FleetVehicle[] = liveSnapshot.vehicles.map((v) => ({
          id: v.id,
          fleetNumber: v.vehicleCode,
          plateNumber: v.plateNumber,
          driver: v.driverName,
          driverId: v.driverId,
          assignmentId: v.assignmentId,
          shipment: v.shipmentNumber,
          shipmentId: v.shipmentId,
          status: v.status === 'In Transit' || v.status === 'Active' ? 'moving' : v.status === 'Idle' ? 'idle' : 'offline',
          speed: v.speed,
          heading: v.heading,
          signalQuality: v.signalQuality > 75 ? 'good' : v.signalQuality > 30 ? 'weak' : 'lost',
          lastUpdate: v.lastUpdateAt,
          latitude: v.latitude,
          longitude: v.longitude,
        }));
        const stats = calculateStats(vehicles);
        return {
          vehicles,
          stats,
          events: [],
        };
      }
    } catch {
      // Fallback to tracking-states if live-fleet unavailable
    }

    const response = await trackingService.getTrackingStates({
      per_page: 100,
      sort_by: 'last_update_at',
      sort_direction: 'desc',
    });
    
    const vehicles = response.data.map(mapTrackingStateToFleetVehicle);
    const stats = calculateStats(vehicles);
    
    return {
      vehicles,
      stats,
      events: [],
    };
  },

  /**
   * Fetch a single vehicle by ID
   */
  async getVehicle(id: string): Promise<FleetVehicle> {
    const state = await trackingService.getTrackingState(id);
    return mapTrackingStateToFleetVehicle(state);
  },

  /**
   * Refresh fleet data
   */
  async refreshFleet(): Promise<TrackingDataResponse> {
    return this.getFleet();
  },

  /**
   * Search fleet by query
   */
  async searchFleet(query: string): Promise<FleetVehicle[]> {
    const response = await trackingService.getTrackingStates({
      search: query,
      per_page: 100,
    });
    
    return response.data.map(mapTrackingStateToFleetVehicle);
  },
};
